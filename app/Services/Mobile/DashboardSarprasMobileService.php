<?php

namespace App\Services\Mobile;

use App\Models\Barang;
use App\Models\MutasiStokBarang;
use App\Models\PeminjamanBarang;
use App\Models\PenerimaanBarang;
use App\Models\PengembalianBarang;
use App\Models\Pengguna;
use App\Models\SaldoStokBarang;
use App\Models\UnitBarang;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class DashboardSarprasMobileService
{
    public function __construct(private MenuMobileService $menuMobile) {}

    public function siapkan(Pengguna $pengguna): array
    {
        $hariIni = now()->startOfDay();
        $akhirJatuhTempo = $hariIni->copy()->addDays(7);
        $queryPeminjamanAktif = PeminjamanBarang::query()
            ->whereIn('status', ['dipinjam', 'sebagian_dikembalikan']);
        $querySaldoStok = SaldoStokBarang::query()
            ->select('saldo_stok_barang.*')
            ->join('barang', 'barang.id', '=', 'saldo_stok_barang.barang_id')
            ->where('barang.aktif', true);

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
            ->map(fn (string $label, string $status) => [
                'kode' => $status,
                'label' => $label,
                'jumlah' => UnitBarang::query()
                    ->where('aktif', true)
                    ->where('status_unit', $status)
                    ->count(),
                'warna' => $this->warnaStatusUnit($status),
            ])
            ->values();

        return [
            'dihasilkan_pada' => now()->toISOString(),
            'tanggal' => $hariIni->toDateString(),
            'tanggal_label' => $hariIni->locale('id')->translatedFormat('l, d F Y'),
            'hak_akses' => [
                'dapat_melihat_barang' => $pengguna->memilikiIzin(['barang.lihat', 'barang.kelola']),
                'dapat_mengelola_barang' => $pengguna->memilikiIzin('barang.kelola'),
                'dapat_mengelola_peminjaman' => $pengguna->memilikiIzin('barang.peminjaman_kelola'),
            ],
            'menu' => $this->menuSarpras($pengguna),
            'ringkasan' => [
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
            ],
            'stok_perlu_perhatian' => $stokPerluPerhatian->map(fn (SaldoStokBarang $saldo) => [
                'id' => (int) $saldo->id,
                'barang_id' => (int) $saldo->barang_id,
                'kode' => $saldo->barang?->kode,
                'nama' => $saldo->barang?->nama ?? 'Barang tidak ditemukan',
                'lokasi' => $saldo->lokasiBarang?->nama ?? 'Tanpa lokasi',
                'jumlah' => (float) $saldo->jumlah,
                'stok_minimum' => (float) ($saldo->barang?->stok_minimum ?? 0),
                'satuan' => $saldo->barang?->satuanBarang?->nama ?? 'unit',
                'status' => (float) $saldo->jumlah <= 0 ? 'habis' : 'menipis',
            ])->values(),
            'stok_belum_dicatat' => $barangStokBelumDicatat->map(fn (Barang $barang) => [
                'id' => (int) $barang->id,
                'kode' => $barang->kode,
                'nama' => $barang->nama,
                'satuan' => $barang->satuanBarang?->nama ?? 'unit',
            ])->values(),
            'peminjaman_terlambat' => $peminjamanTerlambat->map(fn (PeminjamanBarang $peminjaman) => [
                'id' => (int) $peminjaman->id,
                'nomor' => $peminjaman->nomor_peminjaman,
                'peminjam' => $peminjaman->namaPeminjam(),
                'identitas' => $peminjaman->identitasPeminjam(),
                'rencana_kembali' => $peminjaman->rencana_kembali?->toDateString(),
                'rencana_kembali_label' => $peminjaman->rencana_kembali?->locale('id')->translatedFormat('d M Y'),
                'terlambat_hari' => $peminjaman->jumlahHariTerlambat($hariIni),
                'barang' => $peminjaman->detailPeminjamanBarang
                    ->map(fn ($detail) => trim(($detail->barang?->nama ?? 'Barang').' ('.$this->angka($detail->jumlah).' '.($detail->barang?->satuanBarang?->nama ?? 'unit').')'))
                    ->values(),
            ])->values(),
            'distribusi_status_unit' => $distribusiStatusUnit,
            'unit_perlu_perhatian' => $unitPerluPerhatian->map(fn (UnitBarang $unit) => [
                'id' => (int) $unit->id,
                'barang' => $unit->barang?->nama ?? 'Barang tidak ditemukan',
                'kode_inventaris' => $unit->kode_inventaris,
                'lokasi' => $unit->lokasiBarang?->nama ?? 'Tanpa lokasi',
                'status' => $unit->status_unit,
                'status_label' => $unit->labelStatus(),
                'kondisi' => $unit->kondisi,
                'kondisi_label' => $unit->labelKondisi(),
                'nada' => $unit->status_unit === 'hilang' || $unit->kondisi === 'rusak_berat' ? 'bahaya' : 'peringatan',
            ])->values(),
            'aktivitas_terbaru' => $this->aktivitasTerbaru(),
        ];
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

    private function menuSarpras(Pengguna $pengguna): array
    {
        $kelompok = collect($this->menuMobile->siapkan($pengguna)['kelompok'])
            ->firstWhere('kode', 'sarana-prasarana');

        return collect($kelompok['items'] ?? [])
            ->reject(fn (array $item) => $item['kode'] === 'dashboard-sarpras')
            ->values()
            ->all();
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
                    ? ($item->alasan_pembatalan ?: 'Penerimaan dibatalkan')
                    : ($item->sumberPerolehanBarang?->nama ?? 'Tanpa sumber').' - '.$item->detail_penerimaan_barang_count.' jenis barang',
                'waktu' => ($item->dibatalkan_pada ?? $item->created_at)?->toISOString(),
                'nada' => $item->sudahDibatalkan() ? 'bahaya' : 'berhasil',
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
                    'judul' => $item->barang?->nama ?? 'Barang tidak ditemukan',
                    'keterangan' => ($jumlah > 0 ? '+' : '').$this->angka($jumlah).' - '.$item->labelKategori().' - '.($item->lokasiBarang?->nama ?? 'Tanpa lokasi'),
                    'waktu' => $item->created_at?->toISOString(),
                    'nada' => $jumlah > 0 ? 'berhasil' : 'bahaya',
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
                'waktu' => $item->created_at?->toISOString(),
                'nada' => $item->status === 'selesai' ? 'berhasil' : 'peringatan',
            ]);

        $pengembalian = PengembalianBarang::query()
            ->with(['peminjamanBarang.siswa:id,nama_lengkap,nisn', 'peminjamanBarang.pegawai:id,nama_lengkap,nip'])
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (PengembalianBarang $item) => [
                'jenis' => 'Pengembalian',
                'judul' => $item->peminjamanBarang?->namaPeminjam() ?? 'Peminjam tidak ditemukan',
                'keterangan' => $item->nomor_pengembalian.' - '.($item->peminjamanBarang?->nomor_peminjaman ?? '-'),
                'waktu' => $item->created_at?->toISOString(),
                'nada' => 'berhasil',
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

    private function angka(float|string|null $nilai): string
    {
        $angka = (float) $nilai;

        return rtrim(rtrim(number_format($angka, 2, ',', '.'), '0'), ',');
    }
}
