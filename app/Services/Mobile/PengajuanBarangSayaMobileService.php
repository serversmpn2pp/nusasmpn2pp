<?php

namespace App\Services\Mobile;

use App\Models\Barang;
use App\Models\PengajuanBarang;
use Illuminate\Database\Eloquent\Builder;

class PengajuanBarangSayaMobileService
{
    public function daftar(int $pegawaiId, array $filter): array
    {
        $status = $filter['status'] ?? 'semua';
        $halaman = (int) ($filter['halaman'] ?? 1);
        $perHalaman = (int) ($filter['per_halaman'] ?? 12);

        $paginator = PengajuanBarang::query()
            ->where('pegawai_id', $pegawaiId)
            ->with(['barang.satuanBarang', 'peminjamanBarang'])
            ->when($status !== 'semua', fn (Builder $query) => $query->where('status', $status))
            ->orderByDesc('tanggal_pengajuan')
            ->orderByDesc('id')
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'ringkasan' => [
                'semua' => PengajuanBarang::query()->where('pegawai_id', $pegawaiId)->count(),
                'menunggu' => PengajuanBarang::query()->where('pegawai_id', $pegawaiId)->where('status', 'menunggu')->count(),
                'dipenuhi' => PengajuanBarang::query()->where('pegawai_id', $pegawaiId)->where('status', 'dipenuhi')->count(),
                'selesai' => PengajuanBarang::query()->where('pegawai_id', $pegawaiId)->whereIn('status', ['ditolak', 'dibatalkan'])->count(),
            ],
            'filter' => ['status' => $status],
            'pilihan' => [
                'status' => collect(['semua' => 'Semua status'] + PengajuanBarang::DAFTAR_STATUS)
                    ->map(fn (string $label, string $nilai) => ['nilai' => $nilai, 'label' => $label])
                    ->values(),
            ],
            'items' => collect($paginator->items())
                ->map(fn (PengajuanBarang $item) => $this->ringkas($item))
                ->values(),
            'paginasi' => $this->paginasi($paginator),
        ];
    }

    public function katalog(array $filter): array
    {
        $kataKunci = trim((string) ($filter['kata_kunci'] ?? ''));
        $halaman = (int) ($filter['halaman'] ?? 1);
        $perHalaman = (int) ($filter['per_halaman'] ?? 20);

        $paginator = Barang::query()
            ->where('aktif', true)
            ->with(['kategoriBarang:id,nama', 'satuanBarang:id,nama'])
            ->withCount(['unitBarang as jumlah_unit_tersedia' => fn (Builder $query) => $query
                ->where('aktif', true)
                ->where('status_unit', 'tersedia')])
            ->withSum('saldoStokBarang as jumlah_stok', 'jumlah')
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci) {
                $pola = '%'.mb_strtolower($kataKunci).'%';
                $query->where(function (Builder $query) use ($pola) {
                    $query->whereRaw('LOWER(nama) LIKE ?', [$pola])
                        ->orWhereRaw('LOWER(kode) LIKE ?', [$pola])
                        ->orWhereHas('kategoriBarang', fn (Builder $query) => $query->whereRaw('LOWER(nama) LIKE ?', [$pola]));
                });
            })
            ->orderBy('nama')
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'filter' => ['kata_kunci' => $kataKunci],
            'items' => collect($paginator->items())->map(function (Barang $barang) {
                $aset = $barang->tipe_pengelolaan === 'aset_individual';
                $tersedia = $aset
                    ? (float) ($barang->jumlah_unit_tersedia ?? 0)
                    : max((float) ($barang->jumlah_stok ?? 0), 0);

                return [
                    'id' => (int) $barang->id,
                    'kode' => $barang->kode,
                    'nama' => $barang->nama,
                    'kategori' => $barang->kategoriBarang?->nama ?: '-',
                    'jenis_barang' => $barang->jenis_barang,
                    'jenis_barang_label' => $barang->labelJenisBarang(),
                    'tipe_pengelolaan' => $barang->tipe_pengelolaan,
                    'jenis_layanan' => $barang->jenis_barang === 'habis_pakai' ? 'permintaan' : 'peminjaman',
                    'jenis_layanan_label' => $barang->jenis_barang === 'habis_pakai'
                        ? 'Permintaan barang habis pakai'
                        : 'Peminjaman aset',
                    'jumlah_tersedia' => $tersedia,
                    'satuan' => $aset ? 'unit' : ($barang->satuanBarang?->nama ?: 'unit'),
                    'tersedia' => $tersedia > 0,
                ];
            })->values(),
            'paginasi' => $this->paginasi($paginator),
        ];
    }

    public function detail(PengajuanBarang $pengajuan): array
    {
        $pengajuan->load(['barang.kategoriBarang', 'barang.satuanBarang', 'peminjamanBarang']);

        return [
            'pengajuan' => $this->ringkas($pengajuan) + [
                'kategori_barang' => $pengajuan->barang?->kategoriBarang?->nama,
                'tujuan' => $pengajuan->tujuan,
                'catatan_petugas' => $pengajuan->catatan_petugas,
                'peminjaman_barang_id' => $pengajuan->peminjaman_barang_id ? (int) $pengajuan->peminjaman_barang_id : null,
                'nomor_peminjaman' => $pengajuan->peminjamanBarang?->nomor_peminjaman,
            ],
            'hak_akses' => ['dapat_membatalkan' => $pengajuan->masihMenunggu()],
        ];
    }

    private function ringkas(PengajuanBarang $item): array
    {
        return [
            'id' => (int) $item->id,
            'nomor' => $item->nomor_pengajuan,
            'barang_id' => (int) $item->barang_id,
            'kode_barang' => $item->barang?->kode ?: '-',
            'nama_barang' => $item->barang?->nama ?: '-',
            'jenis' => $item->jenis_pengajuan,
            'jenis_label' => $item->labelJenis(),
            'jumlah' => (float) $item->jumlah,
            'satuan' => $item->barang?->tipe_pengelolaan === 'aset_individual'
                ? 'unit'
                : ($item->barang?->satuanBarang?->nama ?: 'unit'),
            'tanggal_pengajuan' => $item->tanggal_pengajuan?->toDateString(),
            'tanggal_pengajuan_label' => $item->tanggal_pengajuan?->locale('id')->translatedFormat('d M Y'),
            'tanggal_dibutuhkan' => $item->tanggal_dibutuhkan?->toDateString(),
            'tanggal_dibutuhkan_label' => $item->tanggal_dibutuhkan?->locale('id')->translatedFormat('d M Y'),
            'rencana_kembali' => $item->rencana_kembali?->toDateString(),
            'rencana_kembali_label' => $item->rencana_kembali?->locale('id')->translatedFormat('d M Y'),
            'status' => $item->status,
            'status_label' => $item->labelStatus(),
        ];
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
