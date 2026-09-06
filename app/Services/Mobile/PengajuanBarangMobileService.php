<?php

namespace App\Services\Mobile;

use App\Models\PengajuanBarang;
use App\Models\SaldoStokBarang;
use App\Models\UnitBarang;
use Illuminate\Database\Eloquent\Builder;

class PengajuanBarangMobileService
{
    public function daftar(array $filter): array
    {
        $kataKunci = trim((string) ($filter['kata_kunci'] ?? ''));
        $jenis = $filter['jenis'] ?? 'semua';
        $status = $filter['status'] ?? 'menunggu';
        $halaman = (int) ($filter['halaman'] ?? 1);
        $perHalaman = (int) ($filter['per_halaman'] ?? 15);

        $paginator = PengajuanBarang::query()
            ->with(['pegawai', 'barang.satuanBarang', 'diprosesOleh'])
            ->when($kataKunci !== '', fn (Builder $query) => $this->cari($query, $kataKunci))
            ->when($jenis !== 'semua', fn (Builder $query) => $query->where('jenis_pengajuan', $jenis))
            ->when($status !== 'semua', fn (Builder $query) => $query->where('status', $status))
            ->orderByRaw("CASE WHEN status = 'menunggu' THEN 0 ELSE 1 END")
            ->orderBy('tanggal_dibutuhkan')
            ->orderByDesc('id')
            ->paginate($perHalaman, ['*'], 'halaman', $halaman);

        return [
            'ringkasan' => [
                'semua' => PengajuanBarang::query()->count(),
                'menunggu' => PengajuanBarang::query()->where('status', 'menunggu')->count(),
                'peminjaman' => PengajuanBarang::query()->where('status', 'menunggu')->where('jenis_pengajuan', 'peminjaman')->count(),
                'permintaan' => PengajuanBarang::query()->where('status', 'menunggu')->where('jenis_pengajuan', 'permintaan')->count(),
            ],
            'filter' => [
                'kata_kunci' => $kataKunci,
                'jenis' => $jenis,
                'status' => $status,
            ],
            'pilihan' => [
                'jenis' => $this->pilihan(PengajuanBarang::DAFTAR_JENIS, 'Semua jenis'),
                'status' => $this->pilihan(PengajuanBarang::DAFTAR_STATUS, 'Semua status'),
            ],
            'hak_akses' => ['dapat_kelola' => true],
            'items' => collect($paginator->items())->map(fn (PengajuanBarang $item) => $this->ringkas($item))->values(),
            'paginasi' => [
                'halaman' => $paginator->currentPage(),
                'halaman_terakhir' => $paginator->lastPage(),
                'per_halaman' => $paginator->perPage(),
                'total' => $paginator->total(),
                'ada_halaman_berikutnya' => $paginator->hasMorePages(),
            ],
        ];
    }

    public function detail(PengajuanBarang $pengajuan): array
    {
        $pengajuan->load([
            'pegawai',
            'barang.kategoriBarang',
            'barang.satuanBarang',
            'diprosesOleh',
            'peminjamanBarang',
        ]);

        $daftarUnit = collect();
        $daftarSaldo = collect();
        if ($pengajuan->masihMenunggu() && $pengajuan->barang) {
            if ($pengajuan->barang->tipe_pengelolaan === 'aset_individual') {
                $daftarUnit = UnitBarang::query()
                    ->where('barang_id', $pengajuan->barang_id)
                    ->where('aktif', true)
                    ->where('status_unit', 'tersedia')
                    ->with('lokasiBarang')
                    ->orderBy('nomor_unit')
                    ->get();
            } else {
                $daftarSaldo = SaldoStokBarang::query()
                    ->where('barang_id', $pengajuan->barang_id)
                    ->where('jumlah', '>', 0)
                    ->with('lokasiBarang')
                    ->orderBy('lokasi_barang_id')
                    ->get();
            }
        }

        return [
            'pengajuan' => $this->ringkas($pengajuan) + [
                'tujuan' => $pengajuan->tujuan,
                'catatan_petugas' => $pengajuan->catatan_petugas,
                'diproses_oleh' => $pengajuan->diprosesOleh?->nama,
                'diproses_pada' => $pengajuan->diproses_pada?->toIso8601String(),
                'diproses_pada_label' => $pengajuan->diproses_pada?->locale('id')->translatedFormat('d M Y, H:i'),
                'peminjaman_barang_id' => $pengajuan->peminjaman_barang_id ? (int) $pengajuan->peminjaman_barang_id : null,
                'nomor_peminjaman' => $pengajuan->peminjamanBarang?->nomor_peminjaman,
                'tipe_pengelolaan' => $pengajuan->barang?->tipe_pengelolaan,
                'kategori_barang' => $pengajuan->barang?->kategoriBarang?->nama,
            ],
            'ketersediaan' => [
                'unit_dibutuhkan' => $pengajuan->barang?->tipe_pengelolaan === 'aset_individual'
                    ? (int) $pengajuan->jumlah
                    : 0,
                'unit' => $daftarUnit->map(fn (UnitBarang $unit) => [
                    'id' => (int) $unit->id,
                    'kode' => $unit->kode_inventaris,
                    'nomor_aset_resmi' => $unit->nomor_aset_resmi,
                    'lokasi' => $unit->lokasiBarang?->nama ?: 'Tanpa lokasi',
                    'kondisi' => $unit->labelKondisi(),
                ])->values(),
                'saldo' => $daftarSaldo->map(fn (SaldoStokBarang $saldo) => [
                    'lokasi_barang_id' => (int) $saldo->lokasi_barang_id,
                    'lokasi' => $saldo->lokasiBarang?->nama ?: 'Tanpa lokasi',
                    'jumlah' => (float) $saldo->jumlah,
                    'satuan' => $pengajuan->barang?->satuanBarang?->nama ?: 'unit',
                ])->values(),
            ],
            'hak_akses' => [
                'dapat_memenuhi' => $pengajuan->masihMenunggu(),
                'dapat_menolak' => $pengajuan->masihMenunggu(),
            ],
        ];
    }

    private function ringkas(PengajuanBarang $item): array
    {
        return [
            'id' => (int) $item->id,
            'nomor' => $item->nomor_pengajuan,
            'nama_pegawai' => $item->pegawai?->nama_lengkap ?: '-',
            'nip' => $item->pegawai?->nip,
            'jenis_pegawai' => $item->pegawai?->jenis_pegawai ?: 'Pegawai',
            'barang_id' => (int) $item->barang_id,
            'kode_barang' => $item->barang?->kode ?: '-',
            'nama_barang' => $item->barang?->nama ?: '-',
            'jenis' => $item->jenis_pengajuan,
            'jenis_label' => $item->labelJenis(),
            'jumlah' => (float) $item->jumlah,
            'satuan' => $item->barang?->satuanBarang?->nama ?: 'unit',
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

    private function cari(Builder $query, string $kataKunci): Builder
    {
        $pola = '%'.mb_strtolower($kataKunci).'%';

        return $query->where(function (Builder $query) use ($pola) {
            $query->whereRaw('LOWER(nomor_pengajuan) LIKE ?', [$pola])
                ->orWhereHas('pegawai', fn (Builder $query) => $query->whereRaw('LOWER(nama_lengkap) LIKE ?', [$pola]))
                ->orWhereHas('barang', fn (Builder $query) => $query->whereRaw('LOWER(nama) LIKE ?', [$pola]));
        });
    }

    private function pilihan(array $items, string $semua): array
    {
        return collect(['semua' => $semua] + $items)
            ->map(fn (string $label, string $nilai) => ['nilai' => $nilai, 'label' => $label])
            ->values()
            ->all();
    }
}
