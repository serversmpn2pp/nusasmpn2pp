<?php

namespace App\Services\Mobile;

use App\Models\Pengguna;
use App\Models\PesertaUjianCbt;
use App\Models\RuangUjianCbt;
use App\Models\Siswa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PresensiUjianMobileService
{
    public function daftar(Pengguna $pengguna): array
    {
        $dapatKelolaSemua = $this->dapatKelolaSemua($pengguna);

        abort_unless($dapatKelolaSemua || filled($pengguna->pegawai_id), 403);

        $ruang = RuangUjianCbt::query()
            ->with([
                'ujianCbt.jenisUjianCbt',
                'ujianCbt.mataPelajaran',
                'jadwalUjianCbt.kegiatanUjianCbt.jenisUjianCbt',
                'jadwalUjianCbt.mataPelajaran',
                'ruangKegiatanUjianCbt',
                'sesiUjianCbt',
                'pengawasUtama',
                'pengawasPendamping',
            ])
            ->withCount([
                'pesertaUjianCbt as jumlah_peserta',
                'pesertaUjianCbt as jumlah_hadir' => fn ($query) => $query->whereIn('status_kehadiran_ujian', ['hadir', 'terlambat']),
                'pesertaUjianCbt as jumlah_belum_absen' => fn ($query) => $query->where('status_kehadiran_ujian', 'belum_absen'),
                'pesertaUjianCbt as jumlah_tidak_hadir' => fn ($query) => $query->whereIn('status_kehadiran_ujian', ['sakit', 'izin', 'alfa']),
            ])
            ->when(! $dapatKelolaSemua, fn ($query) => $query->ditugaskanKepada((int) $pengguna->pegawai_id))
            ->get()
            ->sortBy(fn (RuangUjianCbt $item) => sprintf(
                '%s|%s|%s',
                $item->jadwalUjianCbt?->tanggal?->format('Ymd') ?? '99999999',
                substr((string) $item->jadwalUjianCbt?->waktu_mulai, 0, 5),
                $item->kode,
            ))
            ->values();

        $hariIni = now()->toDateString();
        $items = $ruang->map(fn (RuangUjianCbt $item) => $this->ringkasRuang($item));
        $ruangHariIni = $items->where('tanggal', $hariIni)->values();
        $ruangLain = $items->where('tanggal', '!=', $hariIni)->values();

        return [
            'ringkasan' => [
                'jumlah_ruang' => $items->count(),
                'jumlah_peserta' => $items->sum('jumlah_peserta'),
                'jumlah_hadir' => $items->sum('jumlah_hadir'),
            ],
            'ruang_hari_ini' => $ruangHariIni,
            'ruang_lain' => $ruangLain,
            'dapat_kelola_semua' => $dapatKelolaSemua,
            'dihasilkan_pada' => now()->toISOString(),
        ];
    }

    public function detail(Pengguna $pengguna, RuangUjianCbt $ruang): array
    {
        $this->pastikanDapatMengelolaRuang($pengguna, $ruang);

        $ruang->load([
            'ujianCbt.jenisUjianCbt',
            'ujianCbt.mataPelajaran',
            'jadwalUjianCbt.kegiatanUjianCbt.jenisUjianCbt',
            'jadwalUjianCbt.mataPelajaran',
            'ruangKegiatanUjianCbt',
            'sesiUjianCbt',
            'pengawasUtama',
            'pengawasPendamping',
        ]);

        $peserta = $this->daftarPeserta($ruang);
        $terbaru = $peserta
            ->whereIn('status_kehadiran_ujian', ['hadir', 'terlambat'])
            ->sortByDesc(fn (PesertaUjianCbt $item) => $item->absen_ujian_pada?->getTimestamp() ?? 0)
            ->take(8)
            ->map(fn (PesertaUjianCbt $item) => $this->dataPeserta($item))
            ->values();

        return [
            'ruang' => $this->detailRuang($pengguna, $ruang),
            'ringkasan' => $this->ringkasanRuang($ruang),
            'status_kehadiran' => collect(PesertaUjianCbt::DAFTAR_STATUS_KEHADIRAN)
                ->map(fn (string $label, string $kode) => ['kode' => $kode, 'label' => $label])
                ->values(),
            'presensi_terbaru' => $terbaru,
            'peserta' => $peserta->map(fn (PesertaUjianCbt $item) => $this->dataPeserta($item))->values(),
            'waktu_server' => now()->toISOString(),
        ];
    }

    public function scan(Pengguna $pengguna, RuangUjianCbt $ruang, string $isiScan): array
    {
        $this->pastikanDapatMengelolaRuang($pengguna, $ruang);
        $nisn = $this->nisnDariIsiScan($isiScan);

        if (! $nisn) {
            return $this->hasilGagal('tidak_dikenali', 'QR tidak berisi NISN yang dapat dikenali.');
        }

        $siswa = Siswa::query()->where('nisn', $nisn)->first();

        if (! $siswa) {
            return $this->hasilGagal('tidak_dikenali', 'NISN pada kartu tidak ditemukan di data siswa NUSA.');
        }

        $peserta = DB::transaction(function () use ($pengguna, $ruang, $siswa) {
            $peserta = PesertaUjianCbt::query()
                ->where('ujian_cbt_id', $ruang->ujian_cbt_id)
                ->where('ruang_ujian_cbt_id', $ruang->id)
                ->whereHas('anggotaKelas', fn ($query) => $query->where('siswa_id', $siswa->id))
                ->lockForUpdate()
                ->first();

            if (! $peserta) {
                return null;
            }

            if (! in_array($peserta->status_kehadiran_ujian, ['hadir', 'terlambat'], true)) {
                $peserta->update([
                    'status_kehadiran_ujian' => 'hadir',
                    'absen_ujian_pada' => now(),
                    'absen_ujian_oleh_pengguna_id' => $pengguna->id,
                ]);
                $peserta->setAttribute('presensi_baru', true);
            } else {
                $peserta->setAttribute('presensi_baru', false);
            }

            return $peserta;
        });

        if (! $peserta) {
            $pesertaLain = PesertaUjianCbt::query()
                ->with(['ruangUjianCbt', 'kelasUjianCbt.kelas', 'anggotaKelas.siswa'])
                ->where('ujian_cbt_id', $ruang->ujian_cbt_id)
                ->whereHas('anggotaKelas', fn ($query) => $query->where('siswa_id', $siswa->id))
                ->first();

            if ($pesertaLain?->ruangUjianCbt) {
                return [
                    'berhasil' => false,
                    'baru' => false,
                    'status' => 'salah_ruang',
                    'pesan' => 'Siswa terdaftar di '.$pesertaLain->ruangUjianCbt->kode.' - '.$pesertaLain->ruangUjianCbt->nama.', bukan di ruang ini.',
                    'siswa' => $this->dataSiswa($siswa, $pesertaLain),
                    'ruang_seharusnya' => $pesertaLain->ruangUjianCbt->nama,
                    'waktu_server' => now()->format('H:i:s'),
                ];
            }

            return [
                'berhasil' => false,
                'baru' => false,
                'status' => 'bukan_peserta',
                'pesan' => 'Siswa ini tidak terdaftar sebagai peserta paket ujian tersebut.',
                'siswa' => $this->dataSiswa($siswa),
                'waktu_server' => now()->format('H:i:s'),
            ];
        }

        $peserta->load(['ruangUjianCbt', 'kelasUjianCbt.kelas', 'anggotaKelas.siswa']);
        $baru = (bool) $peserta->getAttribute('presensi_baru');

        return [
            'berhasil' => true,
            'baru' => $baru,
            'status' => $baru ? 'hadir' : 'sudah_hadir',
            'pesan' => $baru
                ? 'Presensi ujian berhasil dicatat. Silakan menuju meja nomor '.($peserta->nomor_meja ?: '-').'.'
                : 'Siswa sudah tercatat hadir di ruang ini.',
            'peserta' => $this->dataPeserta($peserta),
            'siswa' => $this->dataSiswa($siswa, $peserta),
            'ringkasan' => $this->ringkasanRuang($ruang),
            'waktu_server' => now()->format('H:i:s'),
        ];
    }

    public function ubahManual(
        Pengguna $pengguna,
        RuangUjianCbt $ruang,
        PesertaUjianCbt $peserta,
        string $status,
        ?string $catatan,
    ): array {
        $this->pastikanDapatMengelolaRuang($pengguna, $ruang);
        abort_unless(
            (int) $peserta->ujian_cbt_id === (int) $ruang->ujian_cbt_id
            && (int) $peserta->ruang_ujian_cbt_id === (int) $ruang->id,
            404,
        );

        $peserta = DB::transaction(function () use ($pengguna, $peserta, $status, $catatan) {
            $terkunci = PesertaUjianCbt::query()->lockForUpdate()->findOrFail($peserta->id);
            $statusBerubah = $terkunci->status_kehadiran_ujian !== $status;
            $perubahan = [
                'status_kehadiran_ujian' => $status,
                'catatan_kehadiran_ujian' => filled($catatan) ? trim((string) $catatan) : null,
            ];

            if ($status === 'belum_absen') {
                $perubahan['absen_ujian_pada'] = null;
                $perubahan['absen_ujian_oleh_pengguna_id'] = null;
            } elseif ($statusBerubah || ! $terkunci->absen_ujian_pada) {
                $perubahan['absen_ujian_pada'] = now();
                $perubahan['absen_ujian_oleh_pengguna_id'] = $pengguna->id;
            }

            $terkunci->update($perubahan);

            return $terkunci;
        });

        $peserta->load(['ruangUjianCbt', 'kelasUjianCbt.kelas', 'anggotaKelas.siswa']);

        return [
            'peserta' => $this->dataPeserta($peserta),
            'ringkasan' => $this->ringkasanRuang($ruang),
            'detail' => $this->detail($pengguna, $ruang->fresh()),
        ];
    }

    private function ringkasRuang(RuangUjianCbt $ruang): array
    {
        $jadwal = $ruang->jadwalUjianCbt;
        $mataPelajaran = $jadwal?->mataPelajaran?->nama ?: $ruang->ujianCbt?->mataPelajaran?->nama;
        $jumlahPeserta = (int) ($ruang->jumlah_peserta ?? 0);
        $jumlahHadir = (int) ($ruang->jumlah_hadir ?? 0);

        return [
            'id' => (int) $ruang->id,
            'ujian_id' => (int) $ruang->ujian_cbt_id,
            'kode' => $ruang->kode,
            'nama' => $ruang->nama,
            'lokasi' => $ruang->lokasi ?: $ruang->ruangKegiatanUjianCbt?->lokasi,
            'kegiatan' => $jadwal?->kegiatanUjianCbt?->nama ?? $ruang->ujianCbt?->nama ?? 'Ujian CBT',
            'mata_pelajaran' => $mataPelajaran ?: '-',
            'tanggal' => $jadwal?->tanggal?->toDateString(),
            'tanggal_label' => $jadwal?->tanggal?->locale('id')->translatedFormat('l, d F Y') ?? 'Jadwal belum ditentukan',
            'waktu' => $jadwal?->labelWaktu(),
            'sesi' => $ruang->sesiUjianCbt?->nama,
            'status' => $ruang->status,
            'label_status' => $ruang->labelStatus(),
            'pengawas_utama' => $ruang->pengawasUtama?->nama_lengkap,
            'pengawas_pendamping' => $ruang->pengawasPendamping?->nama_lengkap,
            'jumlah_peserta' => $jumlahPeserta,
            'jumlah_hadir' => $jumlahHadir,
            'jumlah_belum_absen' => (int) ($ruang->jumlah_belum_absen ?? 0),
            'jumlah_tidak_hadir' => (int) ($ruang->jumlah_tidak_hadir ?? 0),
            'persentase_hadir' => $jumlahPeserta > 0 ? (int) round(($jumlahHadir / $jumlahPeserta) * 100) : 0,
        ];
    }

    private function detailRuang(Pengguna $pengguna, RuangUjianCbt $ruang): array
    {
        $jadwal = $ruang->jadwalUjianCbt;

        return [
            'id' => (int) $ruang->id,
            'ujian_id' => (int) $ruang->ujian_cbt_id,
            'kode' => $ruang->kode,
            'nama' => $ruang->nama,
            'lokasi' => $ruang->lokasi ?: $ruang->ruangKegiatanUjianCbt?->lokasi,
            'kegiatan' => $jadwal?->kegiatanUjianCbt?->nama ?? $ruang->ujianCbt?->nama ?? 'Ujian CBT',
            'jenis_ujian' => $jadwal?->kegiatanUjianCbt?->jenisUjianCbt?->nama ?? $ruang->ujianCbt?->jenisUjianCbt?->nama,
            'mata_pelajaran' => $jadwal?->mataPelajaran?->nama ?? $ruang->ujianCbt?->mataPelajaran?->nama ?? '-',
            'tanggal' => $jadwal?->tanggal?->toDateString(),
            'tanggal_label' => $jadwal?->tanggal?->locale('id')->translatedFormat('l, d F Y') ?? 'Jadwal belum ditentukan',
            'waktu' => $jadwal?->labelWaktu(),
            'sesi' => $ruang->sesiUjianCbt?->nama,
            'status' => $ruang->status,
            'label_status' => $ruang->labelStatus(),
            'pengawas_utama' => $ruang->pengawasUtama?->nama_lengkap,
            'pengawas_pendamping' => $ruang->pengawasPendamping?->nama_lengkap,
            'peran_saya' => $this->dapatKelolaSemua($pengguna)
                ? 'Pengelola CBT'
                : ((int) $pengguna->pegawai_id === (int) $ruang->pengawas_utama_pegawai_id ? 'Pengawas utama' : 'Pengawas pendamping'),
            'dapat_mengubah' => true,
        ];
    }

    private function daftarPeserta(RuangUjianCbt $ruang)
    {
        return $ruang->pesertaUjianCbt()
            ->with(['kelasUjianCbt.kelas', 'anggotaKelas.siswa'])
            ->get()
            ->sortBy(fn (PesertaUjianCbt $item) => sprintf(
                '%05d|%s',
                $item->nomor_meja ?? 999,
                $item->anggotaKelas?->siswa?->nama_lengkap ?? '',
            ))
            ->values();
    }

    private function ringkasanRuang(RuangUjianCbt $ruang): array
    {
        $query = $ruang->pesertaUjianCbt();

        return [
            'peserta' => (clone $query)->count(),
            'hadir' => (clone $query)->whereIn('status_kehadiran_ujian', ['hadir', 'terlambat'])->count(),
            'belum_absen' => (clone $query)->where('status_kehadiran_ujian', 'belum_absen')->count(),
            'tidak_hadir' => (clone $query)->whereIn('status_kehadiran_ujian', ['sakit', 'izin', 'alfa'])->count(),
        ];
    }

    private function dataPeserta(PesertaUjianCbt $peserta): array
    {
        $siswa = $peserta->anggotaKelas?->siswa;

        return [
            'id' => (int) $peserta->id,
            'nama_lengkap' => $siswa?->nama_lengkap ?? 'Siswa',
            'nisn' => $siswa?->nisn,
            'kelas' => $peserta->kelasUjianCbt?->kelas?->nama ?? '-',
            'foto_url' => $this->fotoUrl($siswa),
            'nomor_peserta' => $peserta->nomor_peserta,
            'nomor_meja' => $peserta->nomor_meja,
            'status' => $peserta->status_kehadiran_ujian ?: 'belum_absen',
            'label_status' => $peserta->labelStatusKehadiranUjian(),
            'waktu_scan' => $peserta->absen_ujian_pada?->format('H:i:s'),
            'catatan' => $peserta->catatan_kehadiran_ujian,
        ];
    }

    private function dataSiswa(?Siswa $siswa, ?PesertaUjianCbt $peserta = null): ?array
    {
        if (! $siswa) {
            return null;
        }

        return [
            'nama_lengkap' => $siswa->nama_lengkap,
            'nisn' => $siswa->nisn,
            'foto_url' => $this->fotoUrl($siswa),
            'kelas' => $peserta?->kelasUjianCbt?->kelas?->nama,
            'nomor_meja' => $peserta?->nomor_meja,
        ];
    }

    private function fotoUrl(?Siswa $siswa): ?string
    {
        return $siswa?->foto && Storage::disk('public')->exists($siswa->foto)
            ? asset('storage/'.$siswa->foto)
            : null;
    }

    private function nisnDariIsiScan(string $isiScan): ?string
    {
        $isiScan = trim($isiScan);

        if (preg_match('/^\d{8,20}$/', $isiScan)) {
            return $isiScan;
        }

        if (preg_match('/(?:NISN\s*[:=-]?\s*)(\d{8,20})/i', $isiScan, $cocok)) {
            return $cocok[1];
        }

        return null;
    }

    private function hasilGagal(string $status, string $pesan): array
    {
        return [
            'berhasil' => false,
            'baru' => false,
            'status' => $status,
            'pesan' => $pesan,
            'waktu_server' => now()->format('H:i:s'),
        ];
    }

    private function pastikanDapatMengelolaRuang(Pengguna $pengguna, RuangUjianCbt $ruang): void
    {
        if ($this->dapatKelolaSemua($pengguna)) {
            return;
        }

        abort_unless(
            filled($pengguna->pegawai_id)
            && in_array((int) $pengguna->pegawai_id, [
                (int) $ruang->pengawas_utama_pegawai_id,
                (int) $ruang->pengawas_pendamping_pegawai_id,
            ], true),
            403,
        );
    }

    private function dapatKelolaSemua(Pengguna $pengguna): bool
    {
        return $pengguna->administrator() || $pengguna->memilikiIzin('cbt.kelola');
    }
}
