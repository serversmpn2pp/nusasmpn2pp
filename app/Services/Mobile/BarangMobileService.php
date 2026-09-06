<?php

namespace App\Services\Mobile;

use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\LokasiBarang;
use App\Models\SatuanBarang;
use App\Services\Inventaris\GeneratorIdentitasInventaris;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BarangMobileService
{
    public function __construct(private GeneratorIdentitasInventaris $generatorIdentitas) {}

    public function daftar(array $filter, bool $dapatKelola): array
    {
        $cari = trim((string) ($filter['cari'] ?? ''));
        $status = $filter['status'] ?? 'semua';
        $jenis = $filter['jenis_barang'] ?? 'semua';
        $kategoriId = isset($filter['kategori_barang_id']) ? (int) $filter['kategori_barang_id'] : null;
        $halaman = (int) ($filter['halaman'] ?? 1);
        $perHalaman = (int) ($filter['per_halaman'] ?? 15);

        $paginator = Barang::query()
            ->with(['kategoriBarang', 'satuanBarang', 'lokasiPenyimpanan'])
            ->withCount('unitBarang')
            ->withExists(['saldoStokBarang', 'mutasiStokBarang', 'detailPeminjamanBarang'])
            ->withSum('saldoStokBarang', 'jumlah')
            ->when($status === 'aktif', fn (Builder $query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn (Builder $query) => $query->where('aktif', false))
            ->when($jenis !== 'semua', fn (Builder $query) => $query->where('jenis_barang', $jenis))
            ->when($kategoriId, fn (Builder $query) => $query->where('kategori_barang_id', $kategoriId))
            ->when($cari !== '', function (Builder $query) use ($cari) {
                $pola = '%'.mb_strtolower($cari).'%';
                $query->where(function (Builder $query) use ($pola) {
                    $query->whereRaw('LOWER(nama) LIKE ?', [$pola])
                        ->orWhereRaw("LOWER(COALESCE(kode, '')) LIKE ?", [$pola])
                        ->orWhereRaw("LOWER(COALESCE(deskripsi, '')) LIKE ?", [$pola]);
                });
            })
            ->orderByDesc('aktif')
            ->orderBy('nama')
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'ringkasan' => [
                'total' => Barang::query()->count(),
                'aktif' => Barang::query()->where('aktif', true)->count(),
                'tidak_habis_pakai' => Barang::query()->where('jenis_barang', 'tidak_habis_pakai')->count(),
                'habis_pakai' => Barang::query()->where('jenis_barang', 'habis_pakai')->count(),
            ],
            'filter' => [
                'cari' => $cari,
                'status' => $status,
                'jenis_barang' => $jenis,
                'kategori_barang_id' => $kategoriId,
            ],
            'pilihan' => $this->pilihanForm(true),
            'hak_akses' => ['dapat_kelola' => $dapatKelola],
            'items' => collect($paginator->items())
                ->map(fn (Barang $barang) => $this->ringkas($barang))
                ->values(),
            'paginasi' => [
                'halaman' => $paginator->currentPage(),
                'halaman_terakhir' => $paginator->lastPage(),
                'per_halaman' => $paginator->perPage(),
                'total' => $paginator->total(),
                'ada_halaman_berikutnya' => $paginator->hasMorePages(),
            ],
        ];
    }

    public function tambah(array $data): Barang
    {
        return DB::transaction(function () use ($data) {
            $data = $this->rapikanData($data);
            $data['kode'] = $data['jenis_barang'] === 'habis_pakai'
                ? $this->generatorIdentitas->buatKodeBarangHabisPakai()
                : $data['kode'];
            $data['tipe_pengelolaan'] = $data['jenis_barang'] === 'habis_pakai'
                ? 'habis_pakai'
                : 'aset_individual';

            return Barang::create($data);
        });
    }

    public function ubah(Barang $barang, array $data): void
    {
        $data = $this->rapikanData($data);
        $jenisBerubah = $data['jenis_barang'] !== $barang->jenis_barang;
        if ($jenisBerubah && $this->sudahDipakai($barang)) {
            throw ValidationException::withMessages([
                'jenis_barang' => 'Jenis barang tidak dapat diubah karena sudah memiliki unit, stok, atau riwayat transaksi.',
            ]);
        }

        DB::transaction(function () use ($barang, $data, $jenisBerubah) {
            if ($data['jenis_barang'] === 'habis_pakai') {
                $data['kode'] = $jenisBerubah
                    ? $this->generatorIdentitas->buatKodeBarangHabisPakai()
                    : $barang->kode;
                $data['tipe_pengelolaan'] = 'habis_pakai';
            } else {
                $data['tipe_pengelolaan'] = ! $jenisBerubah && $barang->tipe_pengelolaan === 'stok_dikembalikan'
                    ? 'stok_dikembalikan'
                    : 'aset_individual';
                $data['stok_minimum'] = 0;
            }
            $barang->update($data);
        });
    }

    public function nonaktifkan(Barang $barang): void
    {
        $barang->update(['aktif' => false]);
    }

    public function detail(Barang $barang): array
    {
        $barang->loadMissing(['kategoriBarang', 'satuanBarang', 'lokasiPenyimpanan'])
            ->loadCount('unitBarang')
            ->loadSum('saldoStokBarang', 'jumlah');

        return $this->ringkas($barang) + [
            'jenis_dapat_diubah' => ! $this->sudahDipakai($barang),
            'dibuat_pada' => $barang->created_at?->toIso8601String(),
            'diperbarui_pada' => $barang->updated_at?->toIso8601String(),
        ];
    }

    public function pilihanForm(bool $sertakanSemuaKategori = false): array
    {
        $kategori = KategoriBarang::query()
            ->when(! $sertakanSemuaKategori, fn (Builder $query) => $query->where('aktif', true))
            ->orderByDesc('aktif')
            ->orderBy('nama')
            ->get();

        return [
            'jenis_barang' => collect(Barang::DAFTAR_JENIS_BARANG)
                ->map(fn (string $label, string $nilai) => ['nilai' => $nilai, 'label' => $label])
                ->values(),
            'kategori' => $kategori->map(fn (KategoriBarang $item) => $this->pilihan($item))->values(),
            'satuan' => SatuanBarang::query()->where('aktif', true)->orderBy('nama')->get()
                ->map(fn (SatuanBarang $item) => $this->pilihan($item))->values(),
            'lokasi' => LokasiBarang::query()->where('aktif', true)->orderBy('nama')->get()
                ->map(fn (LokasiBarang $item) => $this->pilihan($item))->values(),
        ];
    }

    public function rapikanKodeBaku(mixed $kode): ?string
    {
        $kode = trim((string) $kode);
        $angka = preg_replace('/\D/', '', $kode);
        if (strlen($angka) === 10) {
            $kode = implode('.', str_split($angka, 2));
        }

        return $kode !== '' ? $kode : null;
    }

    private function ringkas(Barang $barang): array
    {
        $asetIndividual = $barang->tipe_pengelolaan === 'aset_individual';

        return [
            'id' => (int) $barang->id,
            'kode' => $barang->kodeKlasifikasi(),
            'nama' => $barang->nama,
            'kategori' => $this->relasi($barang->kategoriBarang),
            'satuan' => $this->relasi($barang->satuanBarang),
            'lokasi_penyimpanan' => $barang->lokasiPenyimpanan
                ? $this->relasi($barang->lokasiPenyimpanan)
                : null,
            'jenis_barang' => $barang->jenis_barang,
            'label_jenis_barang' => $barang->labelJenisBarang(),
            'tipe_pengelolaan' => $barang->tipe_pengelolaan,
            'label_tipe_pengelolaan' => $barang->labelTipePengelolaan(),
            'stok_minimum' => (float) $barang->stok_minimum,
            'saldo_stok' => (float) ($barang->saldo_stok_barang_sum_jumlah ?? 0),
            'jumlah_unit_aset' => (int) ($barang->unit_barang_count ?? 0),
            'ringkasan_kuantitas' => $asetIndividual
                ? (int) ($barang->unit_barang_count ?? 0).' unit aset'
                : $this->formatAngka((float) ($barang->saldo_stok_barang_sum_jumlah ?? 0)).' '.$barang->satuanBarang->nama,
            'deskripsi' => $barang->deskripsi,
            'aktif' => (bool) $barang->aktif,
            'jenis_dapat_diubah' => $this->jenisDapatDiubah($barang),
        ];
    }

    private function rapikanData(array $data): array
    {
        $jenis = $data['jenis_barang'];

        return [
            'kode' => $this->rapikanKodeBaku($data['kode'] ?? null),
            'nama' => trim($data['nama']),
            'kategori_barang_id' => (int) $data['kategori_barang_id'],
            'satuan_barang_id' => (int) $data['satuan_barang_id'],
            'lokasi_penyimpanan_id' => filled($data['lokasi_penyimpanan_id'] ?? null)
                ? (int) $data['lokasi_penyimpanan_id']
                : null,
            'jenis_barang' => $jenis,
            'stok_minimum' => $jenis === 'habis_pakai' ? ($data['stok_minimum'] ?? 0) : 0,
            'deskripsi' => filled($data['deskripsi'] ?? null) ? trim($data['deskripsi']) : null,
            'aktif' => (bool) $data['aktif'],
        ];
    }

    private function sudahDipakai(Barang $barang): bool
    {
        return $barang->unitBarang()->exists()
            || $barang->saldoStokBarang()->exists()
            || $barang->mutasiStokBarang()->exists()
            || $barang->detailPeminjamanBarang()->exists();
    }

    private function jenisDapatDiubah(Barang $barang): bool
    {
        $atribut = $barang->getAttributes();
        if (array_key_exists('saldo_stok_barang_exists', $atribut)) {
            return (int) ($barang->unit_barang_count ?? 0) === 0
                && ! (bool) $barang->saldo_stok_barang_exists
                && ! (bool) $barang->mutasi_stok_barang_exists
                && ! (bool) $barang->detail_peminjaman_barang_exists;
        }

        return ! $this->sudahDipakai($barang);
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

    private function relasi(object $item): array
    {
        return ['id' => (int) $item->id, 'nama' => $item->nama, 'kode' => $item->kode];
    }

    private function formatAngka(float $nilai): string
    {
        return rtrim(rtrim(number_format($nilai, 2, '.', ''), '0'), '.');
    }
}
