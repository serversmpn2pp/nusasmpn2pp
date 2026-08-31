<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\MutasiStokBarang;
use App\Models\PeminjamanBarang;
use App\Models\PenerimaanBarang;
use App\Models\PengembalianBarang;
use App\Models\SaldoStokBarang;
use App\Models\UnitBarang;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardSaranaPrasaranaController extends Controller
{
    public function index()
    {
        $hariIni = now()->startOfDay();
        $akhirJatuhTempo = $hariIni->copy()->addDays(7);
        $queryPeminjamanAktif = PeminjamanBarang::query()
            ->whereIn('status', ['dipinjam', 'sebagian_dikembalikan']);
        $querySaldoStok = SaldoStokBarang::query()
            ->select('saldo_stok_barang.*')
            ->join('barang', 'barang.id', '=', 'saldo_stok_barang.barang_id')
            ->where('barang.aktif', true);

        $ringkasan = [
            'jenis_barang' => Barang::query()->where('aktif', true)->count(),
            'unit_aset' => UnitBarang::query()->where('aktif', true)->count(),
            'unit_tersedia' => UnitBarang::query()->where('aktif', true)->where('status_unit', 'tersedia')->count(),
            'peminjaman_aktif' => (clone $queryPeminjamanAktif)->count(),
            'peminjaman_terlambat' => (clone $queryPeminjamanAktif)
                ->whereNotNull('rencana_kembali')
                ->whereDate('rencana_kembali', '<', $hariIni->toDateString())
                ->count(),
            'jatuh_tempo' => (clone $queryPeminjamanAktif)
                ->whereBetween('rencana_kembali', [$hariIni->toDateString(), $akhirJatuhTempo->toDateString()])
                ->count(),
            'stok_menipis' => (clone $querySaldoStok)
                ->where('saldo_stok_barang.jumlah', '>', 0)
                ->whereColumn('saldo_stok_barang.jumlah', '<=', 'barang.stok_minimum')
                ->count(),
            'stok_habis' => (clone $querySaldoStok)
                ->where('saldo_stok_barang.jumlah', '<=', 0)
                ->count(),
            'unit_perlu_perhatian' => $this->queryUnitPerluPerhatian()->count(),
            'stok_belum_dicatat' => Barang::query()
                ->where('aktif', true)
                ->whereIn('tipe_pengelolaan', ['stok_dikembalikan', 'habis_pakai'])
                ->whereDoesntHave('saldoStokBarang')
                ->count(),
        ];

        $stokPerluPerhatian = (clone $querySaldoStok)
            ->with(['barang.satuanBarang', 'lokasiBarang'])
            ->whereColumn('saldo_stok_barang.jumlah', '<=', 'barang.stok_minimum')
            ->orderBy('saldo_stok_barang.jumlah')
            ->orderBy('barang.nama')
            ->limit(6)
            ->get();

        $barangStokBelumDicatat = Barang::query()
            ->with('satuanBarang')
            ->where('aktif', true)
            ->whereIn('tipe_pengelolaan', ['stok_dikembalikan', 'habis_pakai'])
            ->whereDoesntHave('saldoStokBarang')
            ->orderBy('nama')
            ->limit(4)
            ->get();

        $peminjamanTerlambat = (clone $queryPeminjamanAktif)
            ->with([
                'siswa:id,nama_lengkap,nisn',
                'pegawai:id,nama_lengkap,nip',
                'detailPeminjamanBarang.barang:id,nama,satuan_barang_id',
                'detailPeminjamanBarang.barang.satuanBarang:id,nama',
            ])
            ->whereNotNull('rencana_kembali')
            ->whereDate('rencana_kembali', '<', $hariIni->toDateString())
            ->orderBy('rencana_kembali')
            ->limit(6)
            ->get();

        $unitPerluPerhatian = $this->queryUnitPerluPerhatian()
            ->with(['barang:id,nama', 'lokasiBarang:id,nama'])
            ->orderByRaw("case when status_unit = 'hilang' then 0 when kondisi = 'rusak_berat' then 1 else 2 end")
            ->orderBy('kode_inventaris')
            ->limit(6)
            ->get();

        $distribusiStatusUnit = collect(UnitBarang::DAFTAR_STATUS)
            ->map(function (string $label, string $status) {
                return [
                    'kode' => $status,
                    'label' => $label,
                    'jumlah' => UnitBarang::query()
                        ->where('aktif', true)
                        ->where('status_unit', $status)
                        ->count(),
                    'warna' => $this->warnaStatusUnit($status),
                ];
            })
            ->values();

        return view('dashboard-sarana-prasarana.index', [
            'hariIni' => $hariIni,
            'ringkasan' => $ringkasan,
            'stokPerluPerhatian' => $stokPerluPerhatian,
            'barangStokBelumDicatat' => $barangStokBelumDicatat,
            'peminjamanTerlambat' => $peminjamanTerlambat,
            'unitPerluPerhatian' => $unitPerluPerhatian,
            'distribusiStatusUnit' => $distribusiStatusUnit,
            'maksDistribusiUnit' => max((int) $distribusiStatusUnit->max('jumlah'), 1),
            'aktivitasTerbaru' => $this->aktivitasTerbaru(),
        ]);
    }

    private function queryUnitPerluPerhatian(): Builder
    {
        return UnitBarang::query()
            ->where('aktif', true)
            ->where('status_unit', '!=', 'dihapuskan')
            ->where(function (Builder $query) {
                $query->whereIn('status_unit', ['dalam_perbaikan', 'hilang'])
                    ->orWhereIn('kondisi', ['rusak_ringan', 'rusak_berat']);
            });
    }

    private function aktivitasTerbaru(): Collection
    {
        $penerimaan = PenerimaanBarang::query()
            ->with('sumberPerolehanBarang:id,nama')
            ->withCount('detailPenerimaanBarang')
            ->latest('updated_at')
            ->limit(8)
            ->get()
            ->map(fn (PenerimaanBarang $item) => [
                'jenis' => $item->sudahDibatalkan() ? 'Barang datang dibatalkan' : 'Barang datang',
                'judul' => $item->nomor_penerimaan,
                'keterangan' => $item->sudahDibatalkan()
                    ? $item->alasan_pembatalan
                    : $item->sumberPerolehanBarang->nama.' - '.$item->detail_penerimaan_barang_count.' jenis barang',
                'waktu' => $item->dibatalkan_pada ?? $item->created_at,
                'route' => route('penerimaan-barang.show', $item),
                'warna' => $item->sudahDibatalkan() ? 'badge-inactive' : 'badge-active',
                'izin' => ['barang.lihat', 'barang.kelola'],
            ]);

        $mutasi = MutasiStokBarang::query()
            ->with(['barang:id,nama', 'lokasiBarang:id,nama'])
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(function (MutasiStokBarang $item) {
                $jumlah = (float) $item->jumlah_perubahan;

                return [
                    'jenis' => 'Mutasi stok',
                    'judul' => $item->barang->nama,
                    'keterangan' => ($jumlah > 0 ? '+' : '').number_format($jumlah, 2, ',', '.').' - '.$item->labelKategori().' - '.$item->lokasiBarang->nama,
                    'waktu' => $item->created_at,
                    'route' => route('mutasi-stok-barang.show', $item),
                    'warna' => $jumlah > 0 ? 'badge-active' : 'badge-inactive',
                    'izin' => ['barang.lihat', 'barang.kelola'],
                ];
            });

        $peminjaman = PeminjamanBarang::query()
            ->with(['siswa:id,nama_lengkap,nisn', 'pegawai:id,nama_lengkap,nip'])
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (PeminjamanBarang $item) => [
                'jenis' => 'Peminjaman',
                'judul' => $item->namaPeminjam(),
                'keterangan' => $item->nomor_peminjaman.' - '.$item->labelStatus(),
                'waktu' => $item->created_at,
                'route' => route('peminjaman-barang.show', $item),
                'warna' => $item->status === 'selesai' ? 'badge-active' : 'badge-warning',
                'izin' => ['barang.lihat', 'barang.peminjaman_kelola'],
            ]);

        $pengembalian = PengembalianBarang::query()
            ->with(['peminjamanBarang.siswa:id,nama_lengkap,nisn', 'peminjamanBarang.pegawai:id,nama_lengkap,nip'])
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (PengembalianBarang $item) => [
                'jenis' => 'Pengembalian',
                'judul' => $item->peminjamanBarang->namaPeminjam(),
                'keterangan' => $item->nomor_pengembalian.' - '.$item->peminjamanBarang->nomor_peminjaman,
                'waktu' => $item->created_at,
                'route' => route('peminjaman-barang.show', $item->peminjamanBarang),
                'warna' => 'badge-active',
                'izin' => ['barang.lihat', 'barang.peminjaman_kelola'],
            ]);

        return collect()
            ->concat($penerimaan)
            ->concat($mutasi)
            ->concat($peminjaman)
            ->concat($pengembalian)
            ->sortByDesc('waktu')
            ->take(10)
            ->values();
    }

    private function warnaStatusUnit(string $status): string
    {
        return match ($status) {
            'tersedia' => '#15477A',
            'dipinjam' => '#F1C40F',
            'dalam_perbaikan' => '#F97316',
            'hilang' => '#DC2626',
            'dihapuskan' => '#64748B',
            default => '#94A3B8',
        };
    }
}
