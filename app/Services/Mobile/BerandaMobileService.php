<?php

namespace App\Services\Mobile;

use App\Models\AbsensiPegawai;
use App\Models\JadwalPiketGuru;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\PenugasanGuruWaliSiswa;
use App\Models\TahunPelajaran;
use Illuminate\Support\Carbon;

class BerandaMobileService
{
    public function siapkan(Pengguna $pengguna): array
    {
        $hariIni = now();
        $tahunPelajaran = TahunPelajaran::query()
            ->where('aktif', true)
            ->latest('tanggal_mulai')
            ->first();
        $pegawai = $pengguna->pegawai()
            ->first([
                'id',
                'nama_lengkap',
                'nip',
                'foto',
                'email',
                'no_hp',
                'jenis_pegawai',
                'jabatan_utama',
            ]);

        return [
            'dihasilkan_pada' => $hariIni->toISOString(),
            'salam' => $this->salam($hariIni),
            'tanggal' => [
                'iso' => $hariIni->toDateString(),
                'hari' => $hariIni->copy()->locale('id')->translatedFormat('l'),
                'label' => $hariIni->copy()->locale('id')->translatedFormat('d F Y'),
                'bulan' => $hariIni->copy()->locale('id')->translatedFormat('F Y'),
            ],
            'tahun_pelajaran' => $tahunPelajaran ? [
                'id' => (int) $tahunPelajaran->id,
                'nama' => $tahunPelajaran->nama,
            ] : null,
            'pegawai' => $this->profilPegawai($pegawai),
            'presensi' => $this->presensiPegawai($pegawai, $hariIni),
            'piket_hari_ini' => $this->piketHariIni($pegawai, $tahunPelajaran, $hariIni),
            'perwalian' => $this->ringkasanPerwalian($pegawai, $tahunPelajaran, $hariIni),
            'notifikasi' => $this->notifikasi($pengguna),
        ];
    }

    private function salam(Carbon $waktu): string
    {
        return match (true) {
            $waktu->hour < 11 => 'Selamat pagi',
            $waktu->hour < 15 => 'Selamat siang',
            $waktu->hour < 18 => 'Selamat sore',
            default => 'Selamat malam',
        };
    }

    private function profilPegawai(?Pegawai $pegawai): ?array
    {
        if (! $pegawai) {
            return null;
        }

        return [
            'id' => (int) $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'nip' => $pegawai->nip,
            'jabatan' => $pegawai->jabatan_utama ?: $pegawai->jenis_pegawai,
            'email' => $pegawai->email,
            'no_hp' => $pegawai->no_hp,
            'foto_url' => $pegawai->foto ? asset('storage/'.$pegawai->foto) : null,
        ];
    }

    private function presensiPegawai(?Pegawai $pegawai, Carbon $hariIni): ?array
    {
        if (! $pegawai) {
            return null;
        }

        $awalBulan = $hariIni->copy()->startOfMonth()->toDateString();
        $akhirBulan = $hariIni->copy()->endOfMonth()->toDateString();
        $queryBulan = AbsensiPegawai::query()
            ->where('pegawai_id', $pegawai->id)
            ->whereBetween('tanggal', [$awalBulan, $akhirBulan]);
        $jumlahStatus = (clone $queryBulan)
            ->selectRaw('status_kehadiran, count(*) as jumlah')
            ->groupBy('status_kehadiran')
            ->pluck('jumlah', 'status_kehadiran');
        $presensiHariIni = (clone $queryBulan)
            ->whereDate('tanggal', $hariIni->toDateString())
            ->first();

        return [
            'hari_ini' => [
                'tercatat' => $presensiHariIni !== null,
                'status' => $presensiHariIni?->status_kehadiran,
                'label_status' => $presensiHariIni?->labelStatusKehadiran() ?: 'Belum tercatat',
                'jam_masuk' => $this->formatJam($presensiHariIni?->jam_masuk),
                'jam_pulang' => $this->formatJam($presensiHariIni?->jam_pulang),
                'menit_terlambat' => (int) ($presensiHariIni?->menit_terlambat ?? 0),
                'menit_pulang_cepat' => (int) ($presensiHariIni?->menit_pulang_cepat ?? 0),
            ],
            'bulan_ini' => [
                'label' => $hariIni->copy()->locale('id')->translatedFormat('F Y'),
                'total_catatan' => (int) $jumlahStatus->sum(),
                'hadir' => (int) ($jumlahStatus['hadir'] ?? 0),
                'sakit' => (int) ($jumlahStatus['sakit'] ?? 0),
                'izin' => (int) ($jumlahStatus['izin'] ?? 0),
                'dinas_luar' => (int) ($jumlahStatus['dinas_luar'] ?? 0),
                'cuti' => (int) ($jumlahStatus['cuti'] ?? 0),
                'alfa' => (int) ($jumlahStatus['alfa'] ?? 0),
                'terlambat' => (clone $queryBulan)->where('menit_terlambat', '>', 0)->count(),
                'pulang_cepat' => (clone $queryBulan)->where('menit_pulang_cepat', '>', 0)->count(),
            ],
        ];
    }

    private function piketHariIni(
        ?Pegawai $pegawai,
        ?TahunPelajaran $tahunPelajaran,
        Carbon $hariIni,
    ): ?array {
        if (! $pegawai || ! $tahunPelajaran || $hariIni->isSunday()) {
            return null;
        }

        $kodeHari = array_keys(JadwalPiketGuru::DAFTAR_HARI)[$hariIni->dayOfWeekIso - 1] ?? null;
        $jadwal = JadwalPiketGuru::query()
            ->where('pegawai_id', $pegawai->id)
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->where('hari', $kodeHari)
            ->where('aktif', true)
            ->first();

        return $jadwal ? [
            'id' => (int) $jadwal->id,
            'hari' => $jadwal->hari,
            'label_hari' => $jadwal->labelHari(),
            'keterangan' => $jadwal->keterangan,
        ] : null;
    }

    private function ringkasanPerwalian(
        ?Pegawai $pegawai,
        ?TahunPelajaran $tahunPelajaran,
        Carbon $hariIni,
    ): ?array {
        if (! $pegawai) {
            return null;
        }

        $kelas = Kelas::query()
            ->withCount([
                'anggotaKelas as jumlah_siswa' => fn ($query) => $query
                    ->where('status_keanggotaan', 'aktif')
                    ->when($tahunPelajaran, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaran->id)),
            ])
            ->where('wali_kelas_id', $pegawai->id)
            ->where('aktif', true)
            ->when($tahunPelajaran, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaran->id))
            ->orderBy('tingkat')
            ->orderBy('nama')
            ->get(['id', 'tahun_pelajaran_id', 'nama', 'tingkat']);
        $jumlahSiswaGuruWali = PenugasanGuruWaliSiswa::query()
            ->where('guru_wali_pegawai_id', $pegawai->id)
            ->where('aktif', true)
            ->where(function ($query) use ($hariIni) {
                $query->whereNull('tanggal_mulai')
                    ->orWhereDate('tanggal_mulai', '<=', $hariIni->toDateString());
            })
            ->where(function ($query) use ($hariIni) {
                $query->whereNull('tanggal_selesai')
                    ->orWhereDate('tanggal_selesai', '>=', $hariIni->toDateString());
            })
            ->distinct('siswa_id')
            ->count('siswa_id');

        return [
            'jumlah_kelas' => $kelas->count(),
            'jumlah_siswa_kelas' => (int) $kelas->sum('jumlah_siswa'),
            'jumlah_siswa_guru_wali' => $jumlahSiswaGuruWali,
            'kelas' => $kelas->map(fn (Kelas $item) => [
                'id' => (int) $item->id,
                'nama' => $item->nama,
                'tingkat' => $item->tingkat,
                'jumlah_siswa' => (int) $item->jumlah_siswa,
            ])->values(),
        ];
    }

    private function notifikasi(Pengguna $pengguna): array
    {
        $query = $pengguna->notifikasiPengguna();

        return [
            'jumlah_belum_dibaca' => (clone $query)->belumDibaca()->count(),
            'terbaru' => (clone $query)
                ->latest('created_at')
                ->latest('id')
                ->limit(10)
                ->get()
                ->map(fn ($item) => [
                    'id' => (int) $item->id,
                    'jenis' => $item->jenis,
                    'label_jenis' => $item->labelJenis(),
                    'judul' => $item->judul,
                    'pesan' => $item->pesan,
                    'belum_dibaca' => $item->masihBelumDibaca(),
                    'dibuat_pada' => $item->created_at->toISOString(),
                    'waktu_relatif' => $item->created_at->locale('id')->diffForHumans(),
                ])
                ->values(),
        ];
    }

    private function formatJam(?string $jam): ?string
    {
        return $jam ? substr($jam, 0, 5) : null;
    }
}
