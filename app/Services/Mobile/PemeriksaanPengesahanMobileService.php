<?php

namespace App\Services\Mobile;

use App\Models\JenisPelanggaranSiswa;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pengguna;
use App\Models\VerifikasiBkPelanggaran;
use App\Services\Pembinaan\AntreanVerifikasiPelanggaranService;
use Illuminate\Database\Eloquent\Builder;

class PemeriksaanPengesahanMobileService
{
    public const DAFTAR_ANTREAN = [
        'semua' => 'Semua tugas aktif',
        'bk' => 'Pemeriksaan BK',
        'wakil' => 'Pengesahan Wakil Kesiswaan',
        'terlambat' => 'Terlambat diproses',
        'selesai' => 'Riwayat selesai',
    ];

    public function __construct(
        private AntreanVerifikasiPelanggaranService $antrean,
        private LaporanSiswaMobileService $laporanSiswa,
    ) {}

    public function daftar(Pengguna $pengguna, array $filter): array
    {
        $this->pastikanDapatMembuka($pengguna);
        $kataKunci = trim((string) ($filter['kata_kunci'] ?? ''));
        $jenisAntrean = (string) ($filter['antrean'] ?? 'semua');
        $halaman = max(1, (int) ($filter['halaman'] ?? 1));
        $perHalaman = min(30, max(5, (int) ($filter['per_halaman'] ?? 15)));
        $dasar = $this->antrean->queryUntuk($pengguna);
        $ringkasan = $this->antrean->ringkasan($pengguna);

        $query = (clone $dasar)
            ->with([
                'siswa:id,nama_lengkap,nis,nisn',
                'kelas:id,nama,tingkat',
                'tahunPelajaran:id,nama,aktif',
                'kategoriPembinaanSiswa:id,nama',
                'pelaporPegawai:id,nama_lengkap,nip',
                'dibuatOlehPengguna:id,nama',
                'butirPelanggaranLaporan' => fn ($query) => $query->orderByDesc('poin'),
                'verifikasiBkPelanggaran' => fn ($query) => $query
                    ->with(['bkPegawai:id,nama_lengkap', 'pengguna:id,nama'])
                    ->latest('diverifikasi_pada'),
            ])
            ->withCount([
                'butirPelanggaranLaporan',
                'buktiLaporanPembinaanSiswa',
                'saksiLaporanPembinaanSiswa',
                'klarifikasiSiswaPembinaan',
                'tindakLanjutPembinaanSiswa',
            ])
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci): void {
                $pola = '%'.mb_strtolower($kataKunci).'%';
                $query->where(function (Builder $query) use ($pola): void {
                    $query->whereRaw('LOWER(nomor_laporan) LIKE ?', [$pola])
                        ->orWhereHas('siswa', fn (Builder $query) => $query
                            ->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola])
                            ->orWhereRaw("LOWER(COALESCE(nisn, '')) LIKE ?", [$pola]))
                        ->orWhereHas('kelas', fn (Builder $query) => $query
                            ->whereRaw('LOWER(nama) LIKE ?', [$pola]));
                });
            });

        $this->antrean->terapkanJenisAntrean($query, $jenisAntrean);
        $paginasi = $query
            ->orderByRaw("case when status_verifikasi = 'perlu_klarifikasi' then 0 else 1 end")
            ->orderBy('updated_at')
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);

        $items = collect($paginasi->items())->map(function (LaporanPembinaanSiswa $laporan) use ($pengguna): array {
            $this->antrean->lengkapiUntukTampilan($laporan, $pengguna);
            $keputusanBk = $laporan->verifikasiBkPelanggaran->first();

            return $this->laporanSiswa->ringkas($laporan) + [
                'tugas_pengguna' => $laporan->tugas_pengguna,
                'tahap_aktif' => (int) $laporan->tahap_aktif,
                'batas_hari' => (int) $laporan->batas_hari,
                'hari_menunggu' => (int) $laporan->hari_menunggu,
                'sisa_hari' => (int) $laporan->sisa_hari,
                'terlambat_diproses' => (bool) $laporan->terlambat_diproses,
                'kelengkapan_fakta' => $laporan->kelengkapan_fakta,
                'keputusan_bk_terakhir' => $keputusanBk ? [
                    'hasil' => $keputusanBk->hasil,
                    'label_hasil' => $keputusanBk->labelHasil(),
                    'catatan' => $keputusanBk->catatan,
                    'petugas' => $keputusanBk->bkPegawai?->nama_lengkap ?? $keputusanBk->pengguna?->nama,
                    'diproses_pada' => $keputusanBk->diverifikasi_pada?->toISOString(),
                ] : null,
            ];
        })->values();

        return [
            'items' => $items,
            'ringkasan' => $ringkasan,
            'pilihan_antrean' => collect(self::DAFTAR_ANTREAN)->map(fn (string $label, string $kode) => [
                'kode' => $kode,
                'label' => $label,
            ])->values(),
            'filter' => [
                'kata_kunci' => $kataKunci,
                'antrean' => $jenisAntrean,
            ],
            'paginasi' => [
                'halaman' => $paginasi->currentPage(),
                'per_halaman' => $paginasi->perPage(),
                'total' => $paginasi->total(),
                'ada_halaman_berikutnya' => $paginasi->hasMorePages(),
            ],
            'hak_akses' => $this->hakAkses($pengguna),
        ];
    }

    public function rincian(Pengguna $pengguna, LaporanPembinaanSiswa $laporan): array
    {
        $this->pastikanDapatMembuka($pengguna);
        abort_unless(
            (clone $this->antrean->queryUntuk($pengguna))->whereKey($laporan->id)->exists(),
            403,
        );

        $rincian = $this->laporanSiswa->rincian($pengguna, $laporan);
        $laporan->loadCount([
            'butirPelanggaranLaporan',
            'buktiLaporanPembinaanSiswa',
            'saksiLaporanPembinaanSiswa',
            'klarifikasiSiswaPembinaan',
        ]);
        $this->antrean->lengkapiUntukTampilan($laporan, $pengguna);

        return $rincian + [
            'proses' => [
                'tugas_pengguna' => $laporan->tugas_pengguna,
                'tahap_aktif' => (int) $laporan->tahap_aktif,
                'batas_hari' => (int) $laporan->batas_hari,
                'hari_menunggu' => (int) $laporan->hari_menunggu,
                'sisa_hari' => (int) $laporan->sisa_hari,
                'terlambat_diproses' => (bool) $laporan->terlambat_diproses,
                'kelengkapan_fakta' => $laporan->kelengkapan_fakta,
            ],
            'pilihan_hasil_bk' => collect(VerifikasiBkPelanggaran::DAFTAR_HASIL)
                ->map(fn (string $label, string $kode) => ['kode' => $kode, 'label' => $label])
                ->values(),
            'pilihan_keputusan_wakil' => [
                ['kode' => 'sahkan', 'label' => 'Sahkan Rekomendasi Poin'],
                ['kode' => 'kembalikan', 'label' => 'Kembalikan kepada BK'],
            ],
            'jenis_pelanggaran' => JenisPelanggaranSiswa::query()
                ->with('kategoriPembinaanSiswa:id,nama')
                ->where('aktif', true)
                ->orderBy('urutan')
                ->orderBy('kode')
                ->get()
                ->map(fn (JenisPelanggaranSiswa $jenis) => [
                    'id' => (int) $jenis->id,
                    'kode' => $jenis->kode,
                    'nama' => $jenis->nama,
                    'tingkat' => $jenis->tingkat,
                    'poin' => (int) $jenis->poin,
                    'kategori' => $jenis->kategoriPembinaanSiswa?->nama,
                ])->values(),
            'hak_aksi' => [
                'dapat_verifikasi_bk' => $pengguna->memilikiIzin('poin_siswa.verifikasi_bk')
                    && in_array($laporan->status_verifikasi, AntreanVerifikasiPelanggaranService::STATUS_BK, true),
                'dapat_sahkan_wakil' => $pengguna->memilikiIzin('poin_siswa.sahkan_wakil')
                    && in_array($laporan->status_verifikasi, AntreanVerifikasiPelanggaranService::STATUS_WAKIL, true),
            ],
        ];
    }

    private function hakAkses(Pengguna $pengguna): array
    {
        return [
            'dapat_verifikasi_bk' => $pengguna->memilikiIzin('poin_siswa.verifikasi_bk'),
            'dapat_sahkan_wakil' => $pengguna->memilikiIzin('poin_siswa.sahkan_wakil'),
            'dapat_memantau_semua' => $this->antrean->dapatMemantauSemua($pengguna),
        ];
    }

    private function pastikanDapatMembuka(Pengguna $pengguna): void
    {
        abort_unless(
            $pengguna->administrator()
            || $pengguna->memilikiPeran(['bk', 'pimpinan', 'wakil_pimpinan_kesiswaan']),
            403,
        );
    }
}
