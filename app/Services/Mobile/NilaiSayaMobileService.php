<?php

namespace App\Services\Mobile;

use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Nilai\RingkasanNilaiSiswaService;
use Illuminate\Support\Collection;

class NilaiSayaMobileService
{
    public function __construct(private readonly RingkasanNilaiSiswaService $ringkasanNilai) {}

    public function tampilkan(Pengguna $pengguna, array $filter): array
    {
        /** @var Siswa|null $siswa */
        $siswa = $pengguna->siswa()->first();
        $hasil = $this->ringkasanNilai->siapkan(
            $siswa,
            isset($filter['tahun_pelajaran_id']) ? (int) $filter['tahun_pelajaran_id'] : null,
            $filter['semester'] ?? null,
        );
        /** @var AnggotaKelas|null $anggotaKelas */
        $anggotaKelas = $hasil['anggotaKelas'];
        $labelNilaiAkhir = (int) $anggotaKelas?->kelas?->tingkat === 9 ? 'SAJ' : 'SAS';

        return [
            'siswa' => $siswa ? $this->ringkasSiswa($siswa) : null,
            'tahun_pelajaran' => $hasil['daftarTahunPelajaran']
                ->map(fn (TahunPelajaran $item) => $this->ringkasTahunPelajaran($item))
                ->values(),
            'tahun_pelajaran_dipilih' => $hasil['tahunPelajaranDipilih']
                ? $this->ringkasTahunPelajaran($hasil['tahunPelajaranDipilih'])
                : null,
            'kelas' => $anggotaKelas ? [
                'id' => (int) $anggotaKelas->kelas?->id,
                'nama' => $anggotaKelas->kelas?->nama ?? '-',
                'tingkat' => (int) $anggotaKelas->kelas?->tingkat,
                'nomor_absen' => $anggotaKelas->nomor_absen,
                'status_keanggotaan' => $anggotaKelas->status_keanggotaan,
            ] : null,
            'filter' => [
                'tahun_pelajaran_id' => $hasil['tahunPelajaranId'] ?: null,
                'semester' => $hasil['semester'],
            ],
            'ringkasan' => $hasil['ringkasan'],
            'mata_pelajaran' => $hasil['daftarNilai']
                ->map(fn (array $item) => $this->ringkasNilai($item, $labelNilaiAkhir))
                ->values(),
            'label_nilai_akhir' => $labelNilaiAkhir,
            'pesan_kosong' => $this->pesanKosong($siswa, $anggotaKelas, $hasil['daftarNilai']),
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

    private function ringkasTahunPelajaran(TahunPelajaran $tahun): array
    {
        return [
            'id' => (int) $tahun->id,
            'nama' => $tahun->nama,
            'aktif' => (bool) $tahun->aktif,
        ];
    }

    private function ringkasNilai(array $item, string $labelNilaiAkhir): array
    {
        /** @var GuruMataPelajaran|null $penugasan */
        $penugasan = $item['guru_mata_pelajaran'];
        $terbuka = (bool) $item['survei_diisi'];

        return [
            'guru_mata_pelajaran_id' => (int) $penugasan?->id,
            'mata_pelajaran' => [
                'id' => (int) $item['mata_pelajaran']?->id,
                'kode' => $item['mata_pelajaran']?->kode,
                'nama' => $item['mata_pelajaran']?->nama ?? 'Mata pelajaran',
            ],
            'guru' => [
                'id' => (int) $penugasan?->pegawai?->id,
                'nama' => $penugasan?->pegawai?->nama_lengkap ?? 'Guru belum dicantumkan',
            ],
            'publikasi' => [
                'dipublikasikan_pada' => $item['publikasi']->dipublikasikan_pada?->toIso8601String(),
                'dipublikasikan_pada_label' => $item['publikasi']->dipublikasikan_pada
                    ? $item['publikasi']->dipublikasikan_pada->locale('id')->translatedFormat('d F Y')
                    : null,
            ],
            'survei' => [
                'diisi' => $terbuka,
                'diperlukan' => ! $terbuka,
                'semester' => $item['publikasi']->semester,
            ],
            'terbuka' => $terbuka,
            'menggunakan_predikat' => (bool) $item['menggunakan_predikat'],
            'label_nilai_akhir' => $labelNilaiAkhir,
            'nilai_akhir' => $terbuka ? $item['nilai_akhir'] : null,
            'lengkap' => $terbuka && (bool) $item['lengkap'],
            'kkm' => $terbuka ? $item['kkm'] : null,
            'tuntas' => $terbuka ? $item['tuntas'] : null,
            'kategori' => $terbuka && ! $item['menggunakan_predikat']
                ? $this->ringkasKategori($item['kategori'], $labelNilaiAkhir)
                : [],
            'komponen' => $terbuka
                ? $item['komponen']->map(fn (array $komponen) => $this->ringkasKomponen($komponen))->values()
                : [],
            'status' => $terbuka
                ? $this->statusNilai($item)
                : 'survei_diperlukan',
        ];
    }

    private function ringkasKategori(array $kategori, string $labelNilaiAkhir): Collection
    {
        $label = [
            'formatif' => 'Formatif',
            'sumatif' => 'Sumatif',
            'sts' => 'STS',
            'sas_saj' => $labelNilaiAkhir,
        ];

        return collect($label)->map(fn (string $nama, string $kode) => [
            'kode' => $kode,
            'label' => $nama,
            'rata_rata' => $kategori[$kode]['rata'] ?? null,
            'jumlah_terisi' => (int) ($kategori[$kode]['terisi'] ?? 0),
            'jumlah_target' => (int) ($kategori[$kode]['target'] ?? 0),
            'bobot' => (int) ($kategori[$kode]['bobot'] ?? 0),
        ])->values();
    }

    private function ringkasKomponen(array $komponen): array
    {
        return [
            'id' => (int) $komponen['id'],
            'nama' => $komponen['nama'],
            'jenis' => $komponen['jenis'],
            'label_jenis' => $komponen['label_jenis'],
            'tanggal' => $komponen['tanggal']?->toDateString(),
            'tanggal_label' => $komponen['tanggal']
                ? $komponen['tanggal']->locale('id')->translatedFormat('d F Y')
                : null,
            'nilai' => $komponen['nilai'],
            'predikat' => $komponen['predikat'],
            'predikat_label' => $this->labelPredikat($komponen['predikat']),
            'catatan' => $komponen['catatan'],
        ];
    }

    private function statusNilai(array $item): string
    {
        if ($item['menggunakan_predikat']) {
            return $item['komponen']->contains(fn (array $komponen) => filled($komponen['predikat']))
                ? 'tersedia'
                : 'belum_lengkap';
        }

        if (! $item['lengkap'] || $item['nilai_akhir'] === null) {
            return 'belum_lengkap';
        }

        return $item['tuntas'] === false ? 'belum_tuntas' : 'tuntas';
    }

    private function labelPredikat(?string $predikat): ?string
    {
        return match ($predikat) {
            'SB' => 'SB - Sangat Baik',
            'B' => 'B - Baik',
            'C' => 'C - Cukup',
            'K' => 'K - Kurang',
            default => null,
        };
    }

    private function pesanKosong(?Siswa $siswa, ?AnggotaKelas $anggotaKelas, Collection $daftarNilai): ?string
    {
        return match (true) {
            ! $siswa => 'Akun belum terhubung ke data siswa. Hubungi administrator sekolah.',
            ! $anggotaKelas => 'Anda belum tercatat sebagai anggota kelas pada tahun pelajaran yang dipilih.',
            $daftarNilai->isEmpty() => 'Belum ada nilai yang dipublikasikan untuk semester ini.',
            default => null,
        };
    }
}
