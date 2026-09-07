<?php

namespace App\Services\Mobile;

use App\Models\Barang;
use App\Models\LokasiBarang;
use App\Models\MutasiStokBarang;
use App\Models\Pegawai;
use App\Models\PeminjamanBarang;
use App\Models\SaldoStokBarang;
use App\Models\UnitBarang;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class LaporanInventarisBulananMobileService
{
    public function siapkan(array $filter): array
    {
        $periode = $filter['periode'] ?? now()->format('Y-m');
        $lokasiId = isset($filter['lokasi_barang_id']) ? (int) $filter['lokasi_barang_id'] : null;
        $awal = Carbon::createFromFormat('Y-m-d', $periode.'-01')->startOfMonth();
        $akhir = $awal->copy()->endOfMonth();
        $lokasi = $lokasiId ? LokasiBarang::find($lokasiId) : null;

        $saldoAwalPerId = MutasiStokBarang::query()
            ->selectRaw('saldo_stok_barang_id, SUM(jumlah_perubahan) as jumlah')
            ->whereDate('tanggal_mutasi', '<', $awal->toDateString())
            ->when($lokasiId, fn (Builder $query, int $id) => $query->where('lokasi_barang_id', $id))
            ->groupBy('saldo_stok_barang_id')
            ->pluck('jumlah', 'saldo_stok_barang_id');

        $rekapStok = SaldoStokBarang::query()
            ->with([
                'barang.kategoriBarang',
                'barang.satuanBarang',
                'lokasiBarang',
                'mutasiStokBarang' => fn ($query) => $query
                    ->whereBetween('tanggal_mutasi', [$awal->toDateString(), $akhir->toDateString()])
                    ->orderBy('tanggal_mutasi')
                    ->orderBy('id'),
            ])
            ->when($lokasiId, fn (Builder $query, int $id) => $query->where('lokasi_barang_id', $id))
            ->get()
            ->map(function (SaldoStokBarang $saldo) use ($saldoAwalPerId) {
                $mutasi = $saldo->mutasiStokBarang;
                $saldoAwal = (float) ($saldoAwalPerId[$saldo->id] ?? 0);
                $masuk = (float) $mutasi->where('jenis_mutasi', 'masuk')->sum('jumlah_perubahan');
                $keluar = abs((float) $mutasi->where('jenis_mutasi', 'keluar')->sum('jumlah_perubahan'));
                $penyesuaian = (float) $mutasi->where('jenis_mutasi', 'penyesuaian')->sum('jumlah_perubahan');
                $saldoAkhir = $saldoAwal + (float) $mutasi->sum('jumlah_perubahan');
                $minimum = (float) $saldo->barang->stok_minimum;
                $status = $saldoAkhir <= 0 ? 'habis' : ($saldoAkhir <= $minimum ? 'menipis' : 'aman');

                return [
                    'id' => (int) $saldo->id,
                    'barang' => [
                        'id' => (int) $saldo->barang->id,
                        'kode' => $saldo->barang->kode,
                        'nama' => $saldo->barang->nama,
                        'kategori' => $saldo->barang->kategoriBarang?->nama ?: '-',
                        'satuan' => $saldo->barang->satuanBarang?->nama ?: 'unit',
                    ],
                    'lokasi' => [
                        'id' => (int) $saldo->lokasiBarang->id,
                        'nama' => $saldo->lokasiBarang->nama,
                    ],
                    'saldo_awal' => $saldoAwal,
                    'stok_masuk' => $masuk,
                    'stok_keluar' => $keluar,
                    'penyesuaian' => $penyesuaian,
                    'saldo_akhir' => $saldoAkhir,
                    'jumlah_mutasi' => $mutasi->count(),
                    'status' => $status,
                    'status_label' => ucfirst($status),
                ];
            })
            ->sortBy(fn (array $item) => $item['barang']['nama'].'|'.$item['lokasi']['nama'])
            ->values();

        $mutasi = MutasiStokBarang::query()
            ->with(['barang.satuanBarang', 'lokasiBarang'])
            ->whereBetween('tanggal_mutasi', [$awal->toDateString(), $akhir->toDateString()])
            ->when($lokasiId, fn (Builder $query, int $id) => $query->where('lokasi_barang_id', $id))
            ->orderByDesc('tanggal_mutasi')
            ->orderByDesc('id')
            ->get();

        $queryUnit = UnitBarang::query()
            ->where('aktif', true)
            ->when($lokasiId, fn (Builder $query, int $id) => $query->where('lokasi_barang_id', $id));

        $distribusiUnit = collect(UnitBarang::DAFTAR_STATUS)
            ->map(fn (string $label, string $status) => [
                'kode' => $status,
                'label' => $label,
                'jumlah' => (clone $queryUnit)->where('status_unit', $status)->count(),
            ])
            ->values();

        $unitPerhatian = (clone $queryUnit)
            ->with(['barang:id,kode,nama', 'lokasiBarang:id,nama'])
            ->where(function (Builder $query) {
                $query->whereIn('status_unit', ['dalam_perbaikan', 'hilang'])
                    ->orWhereIn('kondisi', ['rusak_ringan', 'rusak_berat']);
            })
            ->orderByRaw("case when status_unit = 'hilang' then 0 when kondisi = 'rusak_berat' then 1 else 2 end")
            ->orderBy('kode_inventaris')
            ->get();

        $stokBelumDicatat = Barang::query()
            ->with('satuanBarang')
            ->where('aktif', true)
            ->whereIn('tipe_pengelolaan', ['stok_dikembalikan', 'habis_pakai'])
            ->whereDoesntHave('saldoStokBarang', function (Builder $query) use ($lokasiId) {
                $query->when($lokasiId, fn (Builder $query, int $id) => $query->where('lokasi_barang_id', $id));
            })
            ->orderBy('nama')
            ->get();

        $layanan = PeminjamanBarang::query()
            ->where('jenis_peminjam', 'pegawai')
            ->whereBetween('tanggal_peminjaman', [$awal->toDateString(), $akhir->toDateString()])
            ->with([
                'pegawai:id,nama_lengkap,nip,jenis_pegawai',
                'pengajuanBarang:id,nomor_pengajuan,peminjaman_barang_id',
                'detailPeminjamanBarang' => fn ($query) => $query
                    ->when($lokasiId, fn ($query, int $id) => $query->where('lokasi_barang_id', $id))
                    ->with(['barang.satuanBarang', 'unitBarang:id,kode_inventaris', 'lokasiBarang:id,nama']),
            ])
            ->when($lokasiId, fn (Builder $query, int $id) => $query
                ->whereHas('detailPeminjamanBarang', fn (Builder $query) => $query->where('lokasi_barang_id', $id)))
            ->orderByDesc('tanggal_peminjaman')
            ->orderByDesc('id')
            ->get();

        $ringkasanLayanan = [
            'jumlah_layanan' => $layanan->count(),
            'pegawai_dilayani' => $layanan->pluck('pegawai_id')->filter()->unique()->count(),
            'peminjaman_aset' => $layanan->filter(fn (PeminjamanBarang $item) => $item->detailPeminjamanBarang
                ->contains(fn ($detail) => $detail->wajib_dikembalikan))->count(),
            'penyerahan_habis_pakai' => $layanan->filter(fn (PeminjamanBarang $item) => $item->detailPeminjamanBarang
                ->contains(fn ($detail) => ! $detail->wajib_dikembalikan))->count(),
            'pinjaman_aktif' => $layanan->whereIn('status', ['dipinjam', 'sebagian_dikembalikan'])->count(),
        ];

        return [
            'periode' => $periode,
            'label_periode' => $awal->locale('id')->translatedFormat('F Y'),
            'awal_periode' => $awal->toDateString(),
            'akhir_periode' => $akhir->toDateString(),
            'lokasi_barang_id' => $lokasiId,
            'lokasi_label' => $lokasi?->nama ?: 'Semua lokasi',
            'dicetak_pada' => now()->locale('id')->translatedFormat('d F Y H:i'),
            'pilihan' => [
                'lokasi' => LokasiBarang::query()->orderBy('nama')->get()
                    ->map(fn (LokasiBarang $item) => [
                        'id' => (int) $item->id,
                        'kode' => $item->kode,
                        'nama' => $item->nama,
                        'aktif' => (bool) $item->aktif,
                    ])->values(),
            ],
            'ringkasan' => $this->ringkasan(
                $rekapStok,
                $mutasi,
                $queryUnit,
                $unitPerhatian,
                $stokBelumDicatat,
                $awal,
                $akhir,
            ),
            'ringkasan_layanan_pegawai' => $ringkasanLayanan,
            'penandatangan' => collect([
                'wakil_sarpras' => ['label' => 'Wakil Kepala Sekolah Bidang Sarpras', 'peran' => 'wakil_pimpinan_sarana_prasarana'],
                'petugas_inventaris' => ['label' => 'Petugas Inventaris', 'peran' => 'petugas_inventaris'],
                'kepala_sekolah' => ['label' => 'Kepala Sekolah', 'peran' => 'pimpinan'],
            ])->map(function (array $item, string $kode) {
                $pegawai = $this->pegawaiDenganPeran($item['peran']);

                return [
                    'kode' => $kode,
                    'jabatan' => $item['label'],
                    'nama' => $pegawai?->nama_lengkap ?: 'Belum ditentukan',
                    'nip' => $pegawai?->nip,
                ];
            })->values(),
            'barang_stok_belum_dicatat' => $stokBelumDicatat->map(fn (Barang $item) => [
                'id' => (int) $item->id,
                'kode' => $item->kode,
                'nama' => $item->nama,
                'satuan' => $item->satuanBarang?->nama ?: 'unit',
            ])->values(),
            'rekap_stok' => $rekapStok,
            'distribusi_status_unit' => $distribusiUnit,
            'unit_perlu_perhatian' => $unitPerhatian->map(fn (UnitBarang $item) => [
                'id' => (int) $item->id,
                'kode_inventaris' => $item->kode_inventaris,
                'barang' => $item->barang?->nama ?: '-',
                'lokasi' => $item->lokasiBarang?->nama ?: '-',
                'kondisi' => $item->kondisi,
                'kondisi_label' => $item->labelKondisi(),
                'status' => $item->status_unit,
                'status_label' => $item->labelStatus(),
            ])->values(),
            'layanan_barang_pegawai' => $layanan->map(fn (PeminjamanBarang $item) => [
                'id' => (int) $item->id,
                'nomor' => $item->nomor_peminjaman,
                'pegawai' => [
                    'id' => $item->pegawai_id ? (int) $item->pegawai_id : null,
                    'nama' => $item->pegawai?->nama_lengkap ?: 'Pegawai tidak ditemukan',
                    'nip' => $item->pegawai?->nip,
                    'jenis' => $item->pegawai?->jenis_pegawai ?: 'Pegawai',
                ],
                'tanggal' => $item->tanggal_peminjaman?->toDateString(),
                'tanggal_label' => $item->tanggal_peminjaman?->locale('id')->translatedFormat('d M Y') ?: '-',
                'rencana_kembali' => $item->rencana_kembali?->toDateString(),
                'rencana_kembali_label' => $item->rencana_kembali?->locale('id')->translatedFormat('d M Y'),
                'status' => $item->status,
                'status_label' => $item->labelStatus(),
                'sumber' => $item->pengajuanBarang->first()?->nomor_pengajuan ?: 'Dicatat petugas',
                'items' => $item->detailPeminjamanBarang->map(fn ($detail) => [
                    'barang' => $detail->barang?->nama ?: '-',
                    'jumlah' => (float) $detail->jumlah,
                    'satuan' => $detail->tipe_pengelolaan === 'aset_individual' ? 'unit' : ($detail->barang?->satuanBarang?->nama ?: 'unit'),
                    'kode_inventaris' => $detail->unitBarang?->kode_inventaris,
                    'lokasi' => $detail->lokasiBarang?->nama ?: '-',
                    'wajib_dikembalikan' => (bool) $detail->wajib_dikembalikan,
                ])->values(),
            ])->values(),
            'mutasi_periode' => $mutasi->map(fn (MutasiStokBarang $item) => [
                'id' => (int) $item->id,
                'tanggal' => $item->tanggal_mutasi?->toDateString(),
                'tanggal_label' => $item->tanggal_mutasi?->locale('id')->translatedFormat('d M Y') ?: '-',
                'barang' => $item->barang?->nama ?: '-',
                'lokasi' => $item->lokasiBarang?->nama ?: '-',
                'jenis' => $item->jenis_mutasi,
                'jenis_label' => $item->labelJenis(),
                'kategori' => $item->kategori_mutasi,
                'kategori_label' => $item->labelKategori(),
                'jumlah_perubahan' => (float) $item->jumlah_perubahan,
                'saldo_akhir' => (float) $item->saldo_sesudah,
                'satuan' => $item->barang?->satuanBarang?->nama ?: 'unit',
                'referensi' => $item->referensi,
            ])->values(),
        ];
    }

    private function ringkasan(
        Collection $rekapStok,
        Collection $mutasi,
        Builder $queryUnit,
        Collection $unitPerhatian,
        Collection $stokBelumDicatat,
        Carbon $awal,
        Carbon $akhir,
    ): array {
        return [
            'baris_stok' => $rekapStok->count(),
            'jumlah_mutasi' => $mutasi->count(),
            'stok_menipis' => $rekapStok->where('status', 'menipis')->count(),
            'stok_habis' => $rekapStok->where('status', 'habis')->count(),
            'stok_belum_dicatat' => $stokBelumDicatat->count(),
            'unit_aset' => (clone $queryUnit)->count(),
            'unit_diperoleh' => (clone $queryUnit)
                ->whereBetween('tanggal_perolehan', [$awal->toDateString(), $akhir->toDateString()])->count(),
            'unit_perlu_perhatian' => $unitPerhatian->count(),
        ];
    }

    private function pegawaiDenganPeran(string $kodePeran): ?Pegawai
    {
        return Pegawai::query()
            ->where('aktif', true)
            ->whereHas('pengguna', function (Builder $query) use ($kodePeran) {
                $query->where('aktif', true)
                    ->whereHas('daftarPeran', function (Builder $query) use ($kodePeran) {
                        $query->where('peran.kode', $kodePeran)->where('peran.aktif', true);
                    });
            })
            ->orderBy('nama_lengkap')
            ->first();
    }
}
