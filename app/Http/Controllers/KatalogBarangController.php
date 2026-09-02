<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\UnitBarang;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class KatalogBarangController extends Controller
{
    private const DAFTAR_KETERSEDIAAN = [
        'semua' => 'Semua ketersediaan',
        'tersedia' => 'Tersedia',
        'dipinjam' => 'Sedang dipinjam',
        'tidak_tersedia' => 'Tidak tersedia',
    ];

    public function index(Request $request)
    {
        $filter = $this->ambilFilter($request);

        $barang = Barang::query()
            ->where('aktif', true)
            ->with([
                'kategoriBarang:id,nama',
                'satuanBarang:id,nama',
                'saldoStokBarang' => fn ($query) => $query
                    ->with('lokasiBarang:id,nama')
                    ->orderBy('lokasi_barang_id'),
                'unitBarang' => fn ($query) => $query
                    ->where('aktif', true)
                    ->with([
                        'lokasiBarang:id,nama',
                        'detailPeminjamanBarang' => fn ($query) => $query
                            ->whereColumn('jumlah_dikembalikan', '<', 'jumlah')
                            ->whereHas('peminjamanBarang', fn ($query) => $query
                                ->whereIn('status', ['dipinjam', 'sebagian_dikembalikan']))
                            ->with([
                                'peminjamanBarang.siswa:id,nama_lengkap',
                                'peminjamanBarang.pegawai:id,nama_lengkap',
                            ])
                            ->orderByDesc('id'),
                    ])
                    ->orderBy('nomor_unit'),
            ])
            ->withCount([
                'unitBarang as jumlah_unit_aktif' => fn ($query) => $query->where('aktif', true),
                'unitBarang as jumlah_unit_tersedia' => fn ($query) => $query
                    ->where('aktif', true)
                    ->where('status_unit', 'tersedia'),
                'unitBarang as jumlah_unit_dipinjam' => fn ($query) => $query
                    ->where('aktif', true)
                    ->where('status_unit', 'dipinjam'),
            ])
            ->withSum('saldoStokBarang as jumlah_stok', 'jumlah')
            ->when($filter['kata_kunci'] !== '', function (Builder $query) use ($filter) {
                $kataKunci = mb_strtolower($filter['kata_kunci']);

                $query->where(function (Builder $query) use ($kataKunci) {
                    $query->whereRaw('LOWER(nama) LIKE ?', ['%'.$kataKunci.'%'])
                        ->orWhereRaw('LOWER(kode) LIKE ?', ['%'.$kataKunci.'%'])
                        ->orWhereHas('kategoriBarang', fn (Builder $query) => $query
                            ->whereRaw('LOWER(nama) LIKE ?', ['%'.$kataKunci.'%']));
                });
            })
            ->when($filter['kategori_barang_id'], fn (Builder $query, int $kategoriId) => $query
                ->where('kategori_barang_id', $kategoriId))
            ->when($filter['jenis_barang'] !== 'semua', fn (Builder $query) => $query
                ->where('jenis_barang', $filter['jenis_barang']))
            ->when($filter['ketersediaan'] !== 'semua', fn (Builder $query) => $this
                ->terapkanFilterKetersediaan($query, $filter['ketersediaan']))
            ->orderBy('nama')
            ->paginate(12)
            ->withQueryString();

        $barang->getCollection()->each(fn (Barang $item) => $this->lengkapiInformasiKatalog($item));

        return view('katalog-barang.index', $filter + [
            'barang' => $barang,
            'daftarKategori' => KategoriBarang::query()
                ->where('aktif', true)
                ->whereHas('barang', fn (Builder $query) => $query->where('aktif', true))
                ->orderBy('nama')
                ->get(['id', 'nama']),
            'daftarJenisBarang' => Barang::DAFTAR_JENIS_BARANG,
            'daftarKetersediaan' => self::DAFTAR_KETERSEDIAAN,
            'ringkasan' => $this->ringkasan(),
        ]);
    }

    private function ambilFilter(Request $request): array
    {
        $data = $request->validate([
            'kata_kunci' => ['nullable', 'string', 'max:100'],
            'kategori_barang_id' => ['nullable', 'integer', 'exists:kategori_barang,id'],
            'jenis_barang' => ['nullable', Rule::in(['semua', ...array_keys(Barang::DAFTAR_JENIS_BARANG)])],
            'ketersediaan' => ['nullable', Rule::in(array_keys(self::DAFTAR_KETERSEDIAAN))],
        ]);

        return [
            'kata_kunci' => trim((string) ($data['kata_kunci'] ?? '')),
            'kategori_barang_id' => $data['kategori_barang_id'] ?? null,
            'jenis_barang' => $data['jenis_barang'] ?? 'semua',
            'ketersediaan' => $data['ketersediaan'] ?? 'semua',
        ];
    }

    private function terapkanFilterKetersediaan(Builder $query, string $ketersediaan): Builder
    {
        if ($ketersediaan === 'dipinjam') {
            return $query
                ->where('tipe_pengelolaan', 'aset_individual')
                ->whereHas('unitBarang', fn (Builder $query) => $query
                    ->where('aktif', true)
                    ->where('status_unit', 'dipinjam'));
        }

        if ($ketersediaan === 'tersedia') {
            return $query->where(function (Builder $query) {
                $query->where(function (Builder $query) {
                    $query->where('tipe_pengelolaan', 'aset_individual')
                        ->whereHas('unitBarang', fn (Builder $query) => $query
                            ->where('aktif', true)
                            ->where('status_unit', 'tersedia'));
                })->orWhere(function (Builder $query) {
                    $query->where('tipe_pengelolaan', '!=', 'aset_individual')
                        ->whereHas('saldoStokBarang', fn (Builder $query) => $query->where('jumlah', '>', 0));
                });
            });
        }

        return $query->where(function (Builder $query) {
            $query->where(function (Builder $query) {
                $query->where('tipe_pengelolaan', 'aset_individual')
                    ->whereDoesntHave('unitBarang', fn (Builder $query) => $query
                        ->where('aktif', true)
                        ->where('status_unit', 'tersedia'));
            })->orWhere(function (Builder $query) {
                $query->where('tipe_pengelolaan', '!=', 'aset_individual')
                    ->whereDoesntHave('saldoStokBarang', fn (Builder $query) => $query->where('jumlah', '>', 0));
            });
        });
    }

    private function lengkapiInformasiKatalog(Barang $barang): void
    {
        if ($barang->tipe_pengelolaan === 'aset_individual') {
            $barang->setAttribute('lokasi_ketersediaan', $barang->unitBarang
                ->groupBy(fn (UnitBarang $unit) => $unit->lokasiBarang?->nama ?: 'Lokasi belum ditentukan')
                ->map(function (Collection $unit, string $lokasi) {
                    return [
                        'lokasi' => $lokasi,
                        'tersedia' => $unit->where('status_unit', 'tersedia')->count(),
                        'jumlah' => $unit->count(),
                    ];
                })
                ->values());

            $barang->setAttribute('daftar_peminjam_aktif', $barang->unitBarang
                ->filter(fn (UnitBarang $unit) => $unit->status_unit === 'dipinjam')
                ->map(function (UnitBarang $unit) {
                    $peminjaman = $unit->detailPeminjamanBarang
                        ->first()?->peminjamanBarang;

                    if (! $peminjaman) {
                        return null;
                    }

                    return [
                        'nama' => $peminjaman->namaPeminjam(),
                        'unit' => $unit->kodeBarangUnit(),
                        'rencana_kembali' => $peminjaman->rencana_kembali,
                    ];
                })
                ->filter()
                ->values());

            return;
        }

        $barang->setAttribute('lokasi_ketersediaan', $barang->saldoStokBarang
            ->map(fn ($saldo) => [
                'lokasi' => $saldo->lokasiBarang?->nama ?: 'Lokasi belum ditentukan',
                'jumlah' => (float) $saldo->jumlah,
            ])
            ->values());
        $barang->setAttribute('daftar_peminjam_aktif', collect());
    }

    private function ringkasan(): array
    {
        $barangAktif = Barang::query()->where('aktif', true);
        $barangStok = Barang::query()
            ->where('aktif', true)
            ->where('tipe_pengelolaan', '!=', 'aset_individual');

        return [
            'barang_aktif' => (clone $barangAktif)->count(),
            'unit_tersedia' => UnitBarang::query()
                ->where('aktif', true)
                ->where('status_unit', 'tersedia')
                ->whereHas('barang', fn (Builder $query) => $query->where('aktif', true))
                ->count(),
            'unit_dipinjam' => UnitBarang::query()
                ->where('aktif', true)
                ->where('status_unit', 'dipinjam')
                ->whereHas('barang', fn (Builder $query) => $query->where('aktif', true))
                ->count(),
            'stok_tersedia' => (clone $barangStok)
                ->whereHas('saldoStokBarang', fn (Builder $query) => $query->where('jumlah', '>', 0))
                ->count(),
            'stok_habis' => (clone $barangStok)
                ->whereDoesntHave('saldoStokBarang', fn (Builder $query) => $query->where('jumlah', '>', 0))
                ->count(),
        ];
    }
}
