<?php

namespace App\Services\Mobile;

use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\LokasiBarang;
use App\Models\MutasiStokBarang;
use App\Models\SaldoStokBarang;
use Illuminate\Database\Eloquent\Builder;

class StokBarangMobileService
{
    public function daftarSaldo(array $filter, bool $dapatKelola): array
    {
        $cari = trim((string) ($filter['cari'] ?? ''));
        $status = $filter['status_stok'] ?? 'semua';
        $kategoriId = isset($filter['kategori_barang_id']) ? (int) $filter['kategori_barang_id'] : null;
        $lokasiId = isset($filter['lokasi_barang_id']) ? (int) $filter['lokasi_barang_id'] : null;
        $halaman = (int) ($filter['halaman'] ?? 1);
        $perHalaman = (int) ($filter['per_halaman'] ?? 15);

        $paginator = SaldoStokBarang::query()
            ->select('saldo_stok_barang.*')
            ->join('barang', 'barang.id', '=', 'saldo_stok_barang.barang_id')
            ->with(['barang.kategoriBarang', 'barang.satuanBarang', 'lokasiBarang'])
            ->when($status === 'aman', fn (Builder $query) => $query
                ->whereColumn('saldo_stok_barang.jumlah', '>', 'barang.stok_minimum'))
            ->when($status === 'menipis', fn (Builder $query) => $query
                ->where('saldo_stok_barang.jumlah', '>', 0)
                ->whereColumn('saldo_stok_barang.jumlah', '<=', 'barang.stok_minimum'))
            ->when($status === 'habis', fn (Builder $query) => $query
                ->where('saldo_stok_barang.jumlah', '<=', 0))
            ->when($kategoriId, fn (Builder $query) => $query->where('barang.kategori_barang_id', $kategoriId))
            ->when($lokasiId, fn (Builder $query) => $query->where('saldo_stok_barang.lokasi_barang_id', $lokasiId))
            ->when($cari !== '', function (Builder $query) use ($cari) {
                $pola = '%'.mb_strtolower($cari).'%';
                $query->where(function (Builder $query) use ($pola) {
                    $query->whereRaw('LOWER(barang.nama) LIKE ?', [$pola])
                        ->orWhereRaw("LOWER(COALESCE(barang.kode, '')) LIKE ?", [$pola]);
                });
            })
            ->orderBy('barang.nama')
            ->orderBy('saldo_stok_barang.lokasi_barang_id')
            ->paginate($perHalaman, ['saldo_stok_barang.*'], 'halaman', $halaman);

        return [
            'ringkasan' => [
                'baris_saldo' => SaldoStokBarang::query()->count(),
                'lokasi_stok' => SaldoStokBarang::query()->distinct('lokasi_barang_id')->count('lokasi_barang_id'),
                'menipis' => SaldoStokBarang::query()
                    ->join('barang', 'barang.id', '=', 'saldo_stok_barang.barang_id')
                    ->where('saldo_stok_barang.jumlah', '>', 0)
                    ->whereColumn('saldo_stok_barang.jumlah', '<=', 'barang.stok_minimum')
                    ->count(),
                'habis' => SaldoStokBarang::query()->where('jumlah', '<=', 0)->count(),
            ],
            'filter' => [
                'cari' => $cari,
                'status_stok' => $status,
                'kategori_barang_id' => $kategoriId,
                'lokasi_barang_id' => $lokasiId,
            ],
            'pilihan' => [
                'status_stok' => [
                    ['nilai' => 'semua', 'label' => 'Semua status'],
                    ['nilai' => 'aman', 'label' => 'Aman'],
                    ['nilai' => 'menipis', 'label' => 'Menipis'],
                    ['nilai' => 'habis', 'label' => 'Habis'],
                ],
                'kategori' => KategoriBarang::query()->where('aktif', true)->orderBy('nama')->get()
                    ->map(fn (KategoriBarang $item) => $this->pilihan($item))->values(),
                'lokasi' => LokasiBarang::query()->where('aktif', true)->orderBy('nama')->get()
                    ->map(fn (LokasiBarang $item) => $this->pilihan($item))->values(),
            ],
            'hak_akses' => ['dapat_kelola' => $dapatKelola],
            'items' => collect($paginator->items())
                ->map(fn (SaldoStokBarang $saldo) => $this->saldo($saldo))
                ->values(),
            'paginasi' => $this->paginasi($paginator),
        ];
    }

    public function daftarMutasi(array $filter, bool $dapatKelola): array
    {
        $cari = trim((string) ($filter['cari'] ?? ''));
        $jenis = $filter['jenis_mutasi'] ?? 'semua';
        $barangId = isset($filter['barang_id']) ? (int) $filter['barang_id'] : null;
        $lokasiId = isset($filter['lokasi_barang_id']) ? (int) $filter['lokasi_barang_id'] : null;
        $tanggalMulai = $filter['tanggal_mulai'] ?? null;
        $tanggalSelesai = $filter['tanggal_selesai'] ?? null;
        $halaman = (int) ($filter['halaman'] ?? 1);
        $perHalaman = (int) ($filter['per_halaman'] ?? 15);

        $paginator = MutasiStokBarang::query()
            ->with(['barang.satuanBarang', 'lokasiBarang', 'dibuatOleh'])
            ->when($jenis !== 'semua', fn (Builder $query) => $query->where('jenis_mutasi', $jenis))
            ->when($barangId, fn (Builder $query) => $query->where('barang_id', $barangId))
            ->when($lokasiId, fn (Builder $query) => $query->where('lokasi_barang_id', $lokasiId))
            ->when($tanggalMulai, fn (Builder $query) => $query->whereDate('tanggal_mutasi', '>=', $tanggalMulai))
            ->when($tanggalSelesai, fn (Builder $query) => $query->whereDate('tanggal_mutasi', '<=', $tanggalSelesai))
            ->when($cari !== '', function (Builder $query) use ($cari) {
                $pola = '%'.mb_strtolower($cari).'%';
                $query->where(function (Builder $query) use ($pola) {
                    $query->whereRaw("LOWER(COALESCE(referensi, '')) LIKE ?", [$pola])
                        ->orWhereRaw("LOWER(COALESCE(keterangan, '')) LIKE ?", [$pola])
                        ->orWhereHas('barang', function (Builder $query) use ($pola) {
                            $query->whereRaw('LOWER(nama) LIKE ?', [$pola])
                                ->orWhereRaw("LOWER(COALESCE(kode, '')) LIKE ?", [$pola]);
                        });
                });
            })
            ->orderByDesc('tanggal_mutasi')
            ->orderByDesc('id')
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);

        $hariIni = now()->toDateString();

        return [
            'ringkasan' => [
                'total' => MutasiStokBarang::query()->count(),
                'hari_ini' => MutasiStokBarang::query()->whereDate('tanggal_mutasi', $hariIni)->count(),
                'masuk_hari_ini' => (float) MutasiStokBarang::query()
                    ->whereDate('tanggal_mutasi', $hariIni)
                    ->where('jenis_mutasi', 'masuk')
                    ->sum('jumlah_perubahan'),
                'keluar_hari_ini' => abs((float) MutasiStokBarang::query()
                    ->whereDate('tanggal_mutasi', $hariIni)
                    ->where('jenis_mutasi', 'keluar')
                    ->sum('jumlah_perubahan')),
            ],
            'filter' => [
                'cari' => $cari,
                'jenis_mutasi' => $jenis,
                'barang_id' => $barangId,
                'lokasi_barang_id' => $lokasiId,
                'tanggal_mulai' => $tanggalMulai,
                'tanggal_selesai' => $tanggalSelesai,
            ],
            'pilihan' => $this->pilihanMutasi(),
            'hak_akses' => ['dapat_kelola' => $dapatKelola],
            'items' => collect($paginator->items())
                ->map(fn (MutasiStokBarang $mutasi) => $this->mutasi($mutasi))
                ->values(),
            'paginasi' => $this->paginasi($paginator),
        ];
    }

    public function detailMutasi(MutasiStokBarang $mutasi): array
    {
        $mutasi->load(['barang.satuanBarang', 'barang.kategoriBarang', 'lokasiBarang', 'dibuatOleh']);

        return array_replace($this->mutasi($mutasi), [
            'barang' => $this->barang($mutasi->barang) + [
                'kategori' => $mutasi->barang->kategoriBarang?->nama,
            ],
            'keterangan' => $mutasi->keterangan,
            'dibuat_pada' => $mutasi->created_at?->toIso8601String(),
        ]);
    }

    public function pilihanMutasi(): array
    {
        return [
            'jenis_mutasi' => $this->pilihanLabel(MutasiStokBarang::DAFTAR_JENIS),
            'kategori_mutasi' => $this->pilihanLabel(MutasiStokBarang::DAFTAR_KATEGORI),
            'kategori_per_jenis' => MutasiStokBarang::KATEGORI_PER_JENIS,
            'barang' => Barang::query()
                ->with('satuanBarang')
                ->whereIn('tipe_pengelolaan', ['stok_dikembalikan', 'habis_pakai'])
                ->orderByDesc('aktif')->orderBy('nama')->get()
                ->map(fn (Barang $item) => $this->barang($item))->values(),
            'lokasi' => LokasiBarang::query()->orderByDesc('aktif')->orderBy('nama')->get()
                ->map(fn (LokasiBarang $item) => $this->pilihan($item))->values(),
        ];
    }

    private function saldo(SaldoStokBarang $saldo): array
    {
        $jumlah = (float) $saldo->jumlah;
        $minimum = (float) $saldo->barang->stok_minimum;
        $status = $jumlah <= 0 ? 'habis' : ($jumlah <= $minimum ? 'menipis' : 'aman');

        return [
            'id' => (int) $saldo->id,
            'barang' => $this->barang($saldo->barang) + [
                'kategori' => $saldo->barang->kategoriBarang?->nama,
            ],
            'lokasi' => $this->pilihan($saldo->lokasiBarang),
            'jumlah' => $jumlah,
            'stok_minimum' => $minimum,
            'status' => $status,
            'status_label' => match ($status) {
                'habis' => 'Habis',
                'menipis' => 'Menipis',
                default => 'Aman',
            },
        ];
    }

    private function mutasi(MutasiStokBarang $mutasi): array
    {
        return [
            'id' => (int) $mutasi->id,
            'tanggal' => $mutasi->tanggal_mutasi?->toDateString(),
            'tanggal_label' => $mutasi->tanggal_mutasi?->locale('id')->translatedFormat('d M Y'),
            'barang' => $this->barang($mutasi->barang),
            'lokasi' => $this->pilihan($mutasi->lokasiBarang),
            'jenis_mutasi' => $mutasi->jenis_mutasi,
            'jenis_label' => $mutasi->labelJenis(),
            'kategori_mutasi' => $mutasi->kategori_mutasi,
            'kategori_label' => $mutasi->labelKategori(),
            'jumlah_perubahan' => (float) $mutasi->jumlah_perubahan,
            'saldo_sebelum' => (float) $mutasi->saldo_sebelum,
            'saldo_sesudah' => (float) $mutasi->saldo_sesudah,
            'referensi' => $mutasi->referensi,
            'dibuat_oleh' => $mutasi->dibuatOleh?->nama ?? 'Sistem',
        ];
    }

    private function barang(Barang $barang): array
    {
        return [
            'id' => (int) $barang->id,
            'nama' => $barang->nama,
            'kode' => $barang->kodeKlasifikasi(),
            'satuan' => $barang->satuanBarang?->nama ?? 'unit',
            'aktif' => (bool) $barang->aktif,
        ];
    }

    private function pilihan(object $item): array
    {
        return [
            'id' => (int) $item->id,
            'nama' => $item->nama,
            'kode' => $item->kode,
            'aktif' => (bool) $item->aktif,
        ];
    }

    private function pilihanLabel(array $items): array
    {
        return collect($items)
            ->map(fn (string $label, string $nilai) => ['nilai' => $nilai, 'label' => $label])
            ->values()->all();
    }

    private function paginasi(object $paginator): array
    {
        return [
            'halaman' => $paginator->currentPage(),
            'halaman_terakhir' => $paginator->lastPage(),
            'per_halaman' => $paginator->perPage(),
            'total' => $paginator->total(),
            'ada_halaman_berikutnya' => $paginator->hasMorePages(),
        ];
    }
}
