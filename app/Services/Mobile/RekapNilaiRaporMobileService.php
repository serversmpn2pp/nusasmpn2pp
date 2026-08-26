<?php

namespace App\Services\Mobile;

use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\SkemaBobotNilai;
use App\Services\Nilai\RekapNilaiRaporService;
use Illuminate\Support\Collection;

class RekapNilaiRaporMobileService
{
    private const KATEGORI = [
        'formatif' => 'Formatif',
        'sumatif' => 'Sumatif',
        'sts' => 'STS',
        'sas_saj' => 'SAS/SAJ',
    ];

    public function __construct(private readonly RekapNilaiRaporService $rekapNilai) {}

    public function tampilkan(array $filter): array
    {
        $semester = $filter['semester'] ?? 'ganjil';
        $daftarPenugasan = $this->rekapNilai->ambilDaftarGuruMataPelajaran();
        $penugasanId = isset($filter['guru_mata_pelajaran_id'])
            ? (int) $filter['guru_mata_pelajaran_id']
            : $daftarPenugasan->first()?->id;

        if ($penugasanId && ! $daftarPenugasan->contains('id', $penugasanId)) {
            abort(404);
        }

        $hasil = $this->rekapNilai->hitung($penugasanId, $semester);
        $penugasanDipilih = $hasil['guruMataPelajaranDipilih'];
        $skema = $hasil['skemaBobotNilai'];

        return [
            'guru_mata_pelajaran' => $daftarPenugasan
                ->map(fn (GuruMataPelajaran $item) => $this->ringkasPenugasan($item))
                ->values(),
            'guru_mata_pelajaran_dipilih' => $penugasanDipilih
                ? $this->ringkasPenugasan($penugasanDipilih)
                : null,
            'filter' => [
                'guru_mata_pelajaran_id' => $penugasanDipilih?->id,
                'semester' => $hasil['semester'],
            ],
            'skema' => $skema ? $this->ringkasSkema($skema, $hasil['labelNilaiAkhir']) : null,
            'kategori' => $this->ringkasKategori($hasil['jumlahKomponen'], $skema, $hasil['labelNilaiAkhir']),
            'ringkasan' => [
                'jumlah_siswa' => $hasil['jumlahSiswa'],
                'jumlah_lengkap' => $hasil['jumlahLengkap'],
                'jumlah_belum_lengkap' => $hasil['jumlahBelumLengkap'],
                'rata_rata_akhir' => $hasil['rataRataAkhir'] === null
                    ? null
                    : round((float) $hasil['rataRataAkhir'], 2),
            ],
            'siswa' => $hasil['rekapNilai']
                ->map(fn (array $item) => $this->ringkasRekapSiswa($item))
                ->values(),
            'label_nilai_akhir' => $hasil['labelNilaiAkhir'],
            'peringatan' => $this->peringatan($penugasanDipilih, $skema, $hasil['jumlahKomponen']),
            'hak_akses' => [
                'dapat_melihat' => true,
            ],
        ];
    }

    private function ringkasPenugasan(GuruMataPelajaran $item): array
    {
        return [
            'id' => (int) $item->id,
            'tahun_pelajaran' => [
                'id' => (int) $item->tahunPelajaran?->id,
                'nama' => $item->tahunPelajaran?->nama ?? '-',
                'aktif' => (bool) $item->tahunPelajaran?->aktif,
            ],
            'kelas' => [
                'id' => (int) $item->kelas?->id,
                'nama' => $item->kelas?->nama ?? '-',
                'tingkat' => (int) $item->kelas?->tingkat,
            ],
            'mata_pelajaran' => [
                'id' => (int) $item->mataPelajaran?->id,
                'kode' => $item->mataPelajaran?->kode,
                'nama' => $item->mataPelajaran?->nama ?? '-',
            ],
            'pegawai' => [
                'id' => (int) $item->pegawai?->id,
                'nama' => $item->pegawai?->nama_lengkap ?? '-',
                'nip' => $item->pegawai?->nip,
            ],
        ];
    }

    private function ringkasSkema(SkemaBobotNilai $skema, string $labelNilaiAkhir): array
    {
        return [
            'id' => (int) $skema->id,
            'tingkat' => $skema->tingkat,
            'tingkat_label' => $skema->labelTingkat(),
            'label_nilai_akhir' => $labelNilaiAkhir,
            'bobot' => [
                'formatif' => (int) $skema->bobot_formatif,
                'sumatif' => (int) $skema->bobot_sumatif,
                'sts' => (int) $skema->bobot_sts,
                'sas_saj' => (int) $skema->bobot_sas_saj,
            ],
        ];
    }

    private function ringkasKategori(array $jumlahKomponen, ?SkemaBobotNilai $skema, string $labelNilaiAkhir): Collection
    {
        $bobot = [
            'formatif' => (int) ($skema?->bobot_formatif ?? 0),
            'sumatif' => (int) ($skema?->bobot_sumatif ?? 0),
            'sts' => (int) ($skema?->bobot_sts ?? 0),
            'sas_saj' => (int) ($skema?->bobot_sas_saj ?? 0),
        ];

        return collect(self::KATEGORI)->map(fn (string $label, string $kode) => [
            'kode' => $kode,
            'label' => $kode === 'sas_saj' ? $labelNilaiAkhir : $label,
            'jumlah_komponen' => (int) ($jumlahKomponen[$kode] ?? 0),
            'bobot' => $bobot[$kode],
        ])->values();
    }

    private function ringkasRekapSiswa(array $item): array
    {
        /** @var AnggotaKelas $anggota */
        $anggota = $item['anggota_kelas'];

        return [
            'anggota_kelas_id' => (int) $anggota->id,
            'nomor_absen' => $anggota->nomor_absen,
            'siswa' => [
                'id' => (int) $anggota->siswa?->id,
                'nama' => $anggota->siswa?->nama_lengkap ?? '-',
                'nis' => $anggota->siswa?->nis,
                'nisn' => $anggota->siswa?->nisn,
            ],
            'kategori' => $item['kategori'],
            'nilai_akhir' => $item['nilai_akhir'],
            'lengkap' => (bool) $item['lengkap'],
            'status' => $item['status'],
        ];
    }

    private function peringatan(
        ?GuruMataPelajaran $penugasan,
        ?SkemaBobotNilai $skema,
        array $jumlahKomponen,
    ): array {
        if (! $penugasan) {
            return ['Belum ada penugasan guru mata pelajaran aktif.'];
        }

        $peringatan = [];

        if (! $skema) {
            $peringatan[] = 'Skema bobot nilai aktif belum tersedia untuk kelas dan semester ini.';
        }

        if (array_sum($jumlahKomponen) === 0) {
            $peringatan[] = 'Komponen nilai aktif belum tersedia untuk penugasan dan semester ini.';
        }

        return $peringatan;
    }
}
