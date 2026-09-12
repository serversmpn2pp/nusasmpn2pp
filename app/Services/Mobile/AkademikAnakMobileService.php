<?php

namespace App\Services\Mobile;

use App\Models\AnggotaKelas;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\OrangTuaWali;
use App\Models\Pengguna;
use App\Models\Siswa;
use Illuminate\Support\Collection;

class AkademikAnakMobileService
{
    public function __construct(private readonly NilaiSayaMobileService $nilaiSaya) {}

    public function tampilkan(Pengguna $pengguna, array $filter): array
    {
        $orangTua = $this->orangTua($pengguna);
        $pilihanSiswa = $orangTua?->siswa ?? collect();
        $siswa = $this->pilihSiswa(
            $orangTua,
            $pilihanSiswa,
            isset($filter['siswa_id']) ? (int) $filter['siswa_id'] : null,
        );
        $tab = $filter['tab'] ?? 'jadwal';
        $nilai = $this->nilaiSaya->tampilkanUntukSiswa($siswa, $filter);
        $tahunPelajaranId = $nilai['filter']['tahun_pelajaran_id'] ?? null;
        $anggotaKelas = $siswa && $tahunPelajaranId
            ? AnggotaKelas::query()
                ->with('kelas:id,nama,tingkat')
                ->where('siswa_id', $siswa->id)
                ->where('tahun_pelajaran_id', $tahunPelajaranId)
                ->latest('id')
                ->first()
            : null;

        return [
            'mode' => 'orang_tua',
            'tab' => $tab,
            'siswa_id' => $siswa ? (int) $siswa->id : null,
            'pilihan_siswa' => $pilihanSiswa
                ->map(fn (Siswa $item) => $this->ringkasSiswa($item))
                ->values(),
            'jadwal' => $this->jadwal($anggotaKelas, $tahunPelajaranId),
            'nilai' => $nilai,
            'catatan_nilai' => 'Nilai yang masih terkunci hanya dapat dibuka setelah anak mengisi survei pembelajaran melalui akun siswa.',
        ];
    }

    private function orangTua(Pengguna $pengguna): ?OrangTuaWali
    {
        abort_unless($pengguna->akunOrangTua(), 403);

        return $pengguna->orangTuaWali()
            ->with(['siswa' => fn ($query) => $query->orderBy('nama_lengkap')])
            ->first();
    }

    private function pilihSiswa(
        ?OrangTuaWali $orangTua,
        Collection $pilihan,
        ?int $siswaId,
    ): ?Siswa {
        if (! $orangTua || $pilihan->isEmpty()) {
            abort_if($siswaId !== null, 403);

            return null;
        }

        $siswa = $siswaId !== null
            ? $pilihan->firstWhere('id', $siswaId)
            : ($pilihan->firstWhere('id', $orangTua->siswa_acuan_username_id) ?: $pilihan->first());
        abort_unless($siswa, 403);

        return $siswa;
    }

    private function jadwal(?AnggotaKelas $anggotaKelas, ?int $tahunPelajaranId): array
    {
        $daftarHari = collect(JamPelajaran::DAFTAR_HARI)->except('minggu');
        $jamPelajaran = JamPelajaran::query()
            ->where('aktif', true)
            ->whereIn('hari', $daftarHari->keys())
            ->orderByRaw("case hari when 'senin' then 1 when 'selasa' then 2 when 'rabu' then 3 when 'kamis' then 4 when 'jumat' then 5 when 'sabtu' then 6 else 7 end")
            ->orderBy('nomor_jam')
            ->get();
        $jadwalKelas = JadwalPelajaran::query()
            ->with([
                'mataPelajaran:id,kode,nama',
                'guruMataPelajaran.mataPelajaran:id,kode,nama',
                'guruMataPelajaran.pegawai:id,nama_lengkap',
            ])
            ->when(
                $anggotaKelas && $tahunPelajaranId,
                fn ($query) => $query
                    ->where('tahun_pelajaran_id', $tahunPelajaranId)
                    ->where('kelas_id', $anggotaKelas->kelas_id),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->where('aktif', true)
            ->get()
            ->keyBy('jam_pelajaran_id');
        $hariIni = $this->kodeHari(now()->dayOfWeekIso);
        $jamSekarang = now()->format('H:i:s');

        return [
            'hari_hari_ini' => $hariIni,
            'ringkasan' => [
                'kelas' => $anggotaKelas?->kelas?->nama,
                'jam_terjadwal' => $jadwalKelas->count(),
                'mata_pelajaran' => $jadwalKelas
                    ->map(fn (JadwalPelajaran $item) => $item->mataPelajaranTerjadwal()?->id)
                    ->filter()
                    ->unique()
                    ->count(),
            ],
            'hari' => $daftarHari
                ->map(function (string $label, string $kode) use ($jamPelajaran, $jadwalKelas, $hariIni, $jamSekarang) {
                    return [
                        'kode' => $kode,
                        'label' => $label,
                        'hari_ini' => $kode === $hariIni,
                        'items' => $jamPelajaran
                            ->where('hari', $kode)
                            ->map(fn (JamPelajaran $jam) => $this->ringkasJam(
                                $jam,
                                $jadwalKelas->get($jam->id),
                                $kode === $hariIni && $jam->jam_mulai <= $jamSekarang && $jam->jam_selesai >= $jamSekarang,
                            ))
                            ->values(),
                    ];
                })
                ->values(),
            'pesan_kosong' => ! $anggotaKelas
                ? 'Kelas anak belum tersedia pada tahun pelajaran yang dipilih.'
                : ($jamPelajaran->isEmpty()
                    ? 'Jam pelajaran belum diatur.'
                    : null),
        ];
    }

    private function ringkasJam(JamPelajaran $jam, ?JadwalPelajaran $jadwal, bool $sedangBerlangsung): array
    {
        $mataPelajaran = $jadwal?->mataPelajaranTerjadwal();

        return [
            'id' => (int) $jam->id,
            'nomor_jam' => (int) $jam->nomor_jam,
            'jam_mulai' => $jam->formatJam($jam->jam_mulai),
            'jam_selesai' => $jam->formatJam($jam->jam_selesai),
            'jenis' => $jam->jenis,
            'label' => $jam->jenis === 'pelajaran'
                ? ($mataPelajaran?->nama ?? 'Kosong')
                : ($jam->label ?: $jam->labelJenis()),
            'sedang_berlangsung' => $sedangBerlangsung,
            'terjadwal' => (bool) $jadwal,
            'mata_pelajaran' => $mataPelajaran ? [
                'id' => (int) $mataPelajaran->id,
                'kode' => $mataPelajaran->kode,
                'nama' => $mataPelajaran->nama,
            ] : null,
            'guru' => $jadwal?->guruMataPelajaran?->pegawai ? [
                'id' => (int) $jadwal->guruMataPelajaran->pegawai->id,
                'nama' => $jadwal->guruMataPelajaran->pegawai->nama_lengkap,
            ] : null,
        ];
    }

    private function ringkasSiswa(Siswa $siswa): array
    {
        return [
            'id' => (int) $siswa->id,
            'nama' => $siswa->nama_lengkap,
            'nis' => $siswa->nis,
            'nisn' => $siswa->nisn,
        ];
    }

    private function kodeHari(int $nomorHari): string
    {
        return [
            1 => 'senin',
            2 => 'selasa',
            3 => 'rabu',
            4 => 'kamis',
            5 => 'jumat',
            6 => 'sabtu',
            7 => 'minggu',
        ][$nomorHari];
    }
}
