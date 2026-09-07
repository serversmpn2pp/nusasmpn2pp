<?php

namespace App\Services\Mobile;

use App\Models\Barang;
use App\Models\UnitBarang;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class KatalogBarangMobileService
{
    public const DAFTAR_KETERSEDIAAN = [
        'semua' => 'Semua ketersediaan',
        'tersedia' => 'Tersedia',
        'dipinjam' => 'Sedang dipinjam',
        'tidak_tersedia' => 'Tidak tersedia',
    ];

    public function daftar(array $filter, bool $dapatMengajukan): array
    {
        $halaman = (int) ($filter['halaman'] ?? 1);
        $perHalaman = (int) ($filter['per_halaman'] ?? 12);
        $filter = [
            'kata_kunci' => trim((string) ($filter['kata_kunci'] ?? '')),
            'kategori_barang_id' => isset($filter['kategori_barang_id']) ? (int) $filter['kategori_barang_id'] : null,
            'jenis_barang' => $filter['jenis_barang'] ?? 'semua',
            'ketersediaan' => $filter['ketersediaan'] ?? 'semua',
        ];
        $paginator = $this->queryDasar()
            ->when($filter['kata_kunci'] !== '', fn (Builder $query) => $this->cari($query, $filter['kata_kunci']))
            ->when($filter['kategori_barang_id'], fn (Builder $query, int $id) => $query->where('kategori_barang_id', $id))
            ->when($filter['jenis_barang'] !== 'semua', fn (Builder $query) => $query->where('jenis_barang', $filter['jenis_barang']))
            ->when($filter['ketersediaan'] !== 'semua', fn (Builder $query) => $this->filterKetersediaan($query, $filter['ketersediaan']))
            ->orderBy('nama')
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'ringkasan' => $this->ringkasan(),
            'filter' => $filter,
            'pilihan' => [
                'kategori' => Barang::query()->where('aktif', true)->whereHas('kategoriBarang')
                    ->with('kategoriBarang:id,nama')->get()->pluck('kategoriBarang')->unique('id')->sortBy('nama')->values()
                    ->map(fn ($kategori) => ['id' => (int) $kategori->id, 'label' => $kategori->nama]),
                'jenis_barang' => $this->pilihan(['semua' => 'Semua jenis'] + Barang::DAFTAR_JENIS_BARANG),
                'ketersediaan' => $this->pilihan(self::DAFTAR_KETERSEDIAAN),
            ],
            'hak_akses' => ['dapat_mengajukan' => $dapatMengajukan],
            'items' => collect($paginator->items())->map(fn (Barang $barang) => $this->ringkasBarang($barang))->values(),
            'paginasi' => $this->paginasi($paginator),
        ];
    }

    public function detail(Barang $barang, bool $dapatMengajukan): array
    {
        abort_unless($barang->aktif, 404);
        $barang->load([
            'kategoriBarang:id,nama',
            'satuanBarang:id,nama',
            'saldoStokBarang' => fn ($query) => $query->with('lokasiBarang:id,nama')->orderBy('lokasi_barang_id'),
            'unitBarang' => fn ($query) => $query->where('aktif', true)->with([
                'lokasiBarang:id,nama',
                'detailPeminjamanBarang' => fn ($query) => $query
                    ->whereColumn('jumlah_dikembalikan', '<', 'jumlah')
                    ->whereHas('peminjamanBarang', fn ($query) => $query->whereIn('status', ['dipinjam', 'sebagian_dikembalikan']))
                    ->with(['peminjamanBarang.siswa:id,nama_lengkap', 'peminjamanBarang.pegawai:id,nama_lengkap'])
                    ->orderByDesc('id'),
            ])->orderBy('nomor_unit'),
        ])->loadCount([
            'unitBarang as jumlah_unit_aktif' => fn ($query) => $query->where('aktif', true),
            'unitBarang as jumlah_unit_tersedia' => fn ($query) => $query->where('aktif', true)->where('status_unit', 'tersedia'),
            'unitBarang as jumlah_unit_dipinjam' => fn ($query) => $query->where('aktif', true)->where('status_unit', 'dipinjam'),
        ])->loadSum('saldoStokBarang as jumlah_stok', 'jumlah');

        $aset = $barang->tipe_pengelolaan === 'aset_individual';
        $lokasi = $aset
            ? $barang->unitBarang->groupBy(fn (UnitBarang $unit) => $unit->lokasiBarang?->nama ?: 'Lokasi belum ditentukan')
                ->map(fn (Collection $unit, string $nama) => [
                    'lokasi' => $nama,
                    'jumlah' => $unit->count(),
                    'tersedia' => $unit->where('status_unit', 'tersedia')->count(),
                    'dipinjam' => $unit->where('status_unit', 'dipinjam')->count(),
                ])->values()
            : $barang->saldoStokBarang->map(fn ($saldo) => [
                'lokasi' => $saldo->lokasiBarang?->nama ?: 'Lokasi belum ditentukan',
                'jumlah' => (float) $saldo->jumlah,
                'tersedia' => (float) $saldo->jumlah,
                'dipinjam' => 0,
            ])->values();

        return [
            'barang' => $this->ringkasBarang($barang) + [
                'deskripsi' => $barang->deskripsi,
                'lokasi' => $lokasi,
                'unit' => $aset ? $barang->unitBarang->map(fn (UnitBarang $unit) => $this->formatUnit($unit))->values() : [],
                'peminjam_aktif' => $aset ? $barang->unitBarang->map(function (UnitBarang $unit) {
                    $peminjaman = $unit->detailPeminjamanBarang->first()?->peminjamanBarang;
                    if (! $peminjaman) {
                        return null;
                    }

                    return [
                        'nama' => $peminjaman->namaPeminjam(),
                        'unit' => $unit->kodeBarangUnit(),
                        'kode_inventaris' => $unit->kode_inventaris,
                        'rencana_kembali' => $peminjaman->rencana_kembali?->toDateString(),
                        'rencana_kembali_label' => $peminjaman->rencana_kembali?->locale('id')->translatedFormat('d M Y'),
                    ];
                })->filter()->values() : [],
            ],
            'hak_akses' => [
                'dapat_mengajukan' => $dapatMengajukan && $this->jumlahTersedia($barang) > 0,
            ],
        ];
    }

    private function queryDasar(): Builder
    {
        return Barang::query()->where('aktif', true)
            ->with(['kategoriBarang:id,nama', 'satuanBarang:id,nama'])
            ->withCount([
                'unitBarang as jumlah_unit_aktif' => fn ($query) => $query->where('aktif', true),
                'unitBarang as jumlah_unit_tersedia' => fn ($query) => $query->where('aktif', true)->where('status_unit', 'tersedia'),
                'unitBarang as jumlah_unit_dipinjam' => fn ($query) => $query->where('aktif', true)->where('status_unit', 'dipinjam'),
            ])->withSum('saldoStokBarang as jumlah_stok', 'jumlah');
    }

    private function ringkasBarang(Barang $barang): array
    {
        $aset = $barang->tipe_pengelolaan === 'aset_individual';
        $tersedia = $this->jumlahTersedia($barang);

        return [
            'id' => (int) $barang->id,
            'kode' => $barang->kode,
            'nama' => $barang->nama,
            'kategori' => $barang->kategoriBarang?->nama ?: '-',
            'jenis_barang' => $barang->jenis_barang,
            'jenis_barang_label' => $barang->labelJenisBarang(),
            'tipe_pengelolaan' => $barang->tipe_pengelolaan,
            'tipe_pengelolaan_label' => $barang->labelTipePengelolaan(),
            'jenis_layanan' => $barang->jenis_barang === 'habis_pakai' ? 'permintaan' : 'peminjaman',
            'jumlah_tersedia' => $tersedia,
            'jumlah_unit' => $aset ? (int) ($barang->jumlah_unit_aktif ?? 0) : 0,
            'jumlah_dipinjam' => $aset ? (int) ($barang->jumlah_unit_dipinjam ?? 0) : 0,
            'satuan' => $aset ? 'unit' : ($barang->satuanBarang?->nama ?: 'unit'),
            'tersedia' => $tersedia > 0,
        ];
    }

    private function formatUnit(UnitBarang $unit): array
    {
        return [
            'id' => (int) $unit->id,
            'kode_inventaris' => $unit->kode_inventaris,
            'nomor_aset_resmi' => $unit->nomor_aset_resmi,
            'lokasi' => $unit->lokasiBarang?->nama ?: 'Lokasi belum ditentukan',
            'kondisi' => $unit->labelKondisi(),
            'status' => $unit->status_unit,
            'status_label' => $unit->labelStatus(),
        ];
    }

    private function jumlahTersedia(Barang $barang): float
    {
        return $barang->tipe_pengelolaan === 'aset_individual'
            ? (float) ($barang->jumlah_unit_tersedia ?? $barang->unitBarang->where('status_unit', 'tersedia')->count())
            : max((float) ($barang->jumlah_stok ?? $barang->saldoStokBarang->sum('jumlah')), 0);
    }

    private function cari(Builder $query, string $kataKunci): Builder
    {
        $pola = '%'.mb_strtolower($kataKunci).'%';

        return $query->where(function (Builder $query) use ($pola) {
            $query->whereRaw('LOWER(nama) LIKE ?', [$pola])
                ->orWhereRaw('LOWER(kode) LIKE ?', [$pola])
                ->orWhereHas('kategoriBarang', fn (Builder $query) => $query->whereRaw('LOWER(nama) LIKE ?', [$pola]));
        });
    }

    private function filterKetersediaan(Builder $query, string $ketersediaan): Builder
    {
        if ($ketersediaan === 'dipinjam') {
            return $query->where('tipe_pengelolaan', 'aset_individual')
                ->whereHas('unitBarang', fn (Builder $query) => $query->where('aktif', true)->where('status_unit', 'dipinjam'));
        }
        if ($ketersediaan === 'tersedia') {
            return $query->where(function (Builder $query) {
                $query->where(fn (Builder $query) => $query->where('tipe_pengelolaan', 'aset_individual')
                    ->whereHas('unitBarang', fn (Builder $query) => $query->where('aktif', true)->where('status_unit', 'tersedia')))
                    ->orWhere(fn (Builder $query) => $query->where('tipe_pengelolaan', '!=', 'aset_individual')
                        ->whereHas('saldoStokBarang', fn (Builder $query) => $query->where('jumlah', '>', 0)));
            });
        }

        return $query->where(function (Builder $query) {
            $query->where(fn (Builder $query) => $query->where('tipe_pengelolaan', 'aset_individual')
                ->whereDoesntHave('unitBarang', fn (Builder $query) => $query->where('aktif', true)->where('status_unit', 'tersedia')))
                ->orWhere(fn (Builder $query) => $query->where('tipe_pengelolaan', '!=', 'aset_individual')
                    ->whereDoesntHave('saldoStokBarang', fn (Builder $query) => $query->where('jumlah', '>', 0)));
        });
    }

    private function ringkasan(): array
    {
        $barangStok = Barang::query()->where('aktif', true)->where('tipe_pengelolaan', '!=', 'aset_individual');

        return [
            'barang_aktif' => Barang::query()->where('aktif', true)->count(),
            'unit_tersedia' => UnitBarang::query()->where('aktif', true)->where('status_unit', 'tersedia')->whereHas('barang', fn (Builder $query) => $query->where('aktif', true))->count(),
            'unit_dipinjam' => UnitBarang::query()->where('aktif', true)->where('status_unit', 'dipinjam')->whereHas('barang', fn (Builder $query) => $query->where('aktif', true))->count(),
            'stok_tersedia' => (clone $barangStok)->whereHas('saldoStokBarang', fn (Builder $query) => $query->where('jumlah', '>', 0))->count(),
            'stok_habis' => (clone $barangStok)->whereDoesntHave('saldoStokBarang', fn (Builder $query) => $query->where('jumlah', '>', 0))->count(),
        ];
    }

    private function pilihan(array $items): array
    {
        return collect($items)->map(fn (string $label, string $nilai) => ['nilai' => $nilai, 'label' => $label])->values()->all();
    }

    private function paginasi(object $paginator): array
    {
        return ['halaman' => $paginator->currentPage(), 'halaman_terakhir' => $paginator->lastPage(), 'per_halaman' => $paginator->perPage(), 'total' => $paginator->total(), 'ada_halaman_berikutnya' => $paginator->hasMorePages()];
    }
}
