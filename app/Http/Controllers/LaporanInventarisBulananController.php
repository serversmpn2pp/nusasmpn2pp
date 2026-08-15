<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\LokasiBarang;
use App\Models\MutasiStokBarang;
use App\Models\Pegawai;
use App\Models\PeminjamanBarang;
use App\Models\SaldoStokBarang;
use App\Models\UnitBarang;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class LaporanInventarisBulananController extends Controller
{
    public function index(Request $request)
    {
        return view('laporan-inventaris-bulanan.index', $this->bangunDataLaporan($request));
    }

    public function cetak(Request $request)
    {
        return view('laporan-inventaris-bulanan.cetak', $this->bangunDataLaporan($request) + [
            'tanggalCetak' => now()->locale('id')->translatedFormat('d F Y H:i'),
        ]);
    }

    private function bangunDataLaporan(Request $request): array
    {
        $filter = $this->ambilFilter($request);
        $awalPeriode = Carbon::createFromFormat('Y-m-d', $filter['periode'] . '-01')->startOfMonth();
        $akhirPeriode = $awalPeriode->copy()->endOfMonth();
        $lokasiBarang = $filter['lokasi_barang_id']
            ? LokasiBarang::find($filter['lokasi_barang_id'])
            : null;

        $saldoAwalPerId = MutasiStokBarang::query()
            ->selectRaw('saldo_stok_barang_id, SUM(jumlah_perubahan) as jumlah')
            ->whereDate('tanggal_mutasi', '<', $awalPeriode->toDateString())
            ->when($filter['lokasi_barang_id'], fn (Builder $query, int $lokasiId) => $query->where('lokasi_barang_id', $lokasiId))
            ->groupBy('saldo_stok_barang_id')
            ->pluck('jumlah', 'saldo_stok_barang_id');

        $rekapStok = SaldoStokBarang::query()
            ->with([
                'barang.kategoriBarang',
                'barang.satuanBarang',
                'lokasiBarang',
                'mutasiStokBarang' => fn ($query) => $query
                    ->whereBetween('tanggal_mutasi', [$awalPeriode->toDateString(), $akhirPeriode->toDateString()])
                    ->orderBy('tanggal_mutasi')
                    ->orderBy('id'),
            ])
            ->when($filter['lokasi_barang_id'], fn (Builder $query, int $lokasiId) => $query->where('lokasi_barang_id', $lokasiId))
            ->get()
            ->map(function (SaldoStokBarang $saldo) use ($saldoAwalPerId) {
                $mutasiPeriode = $saldo->mutasiStokBarang;
                $saldoAwal = (float) ($saldoAwalPerId[$saldo->id] ?? 0);
                $stokMasuk = (float) $mutasiPeriode
                    ->where('jenis_mutasi', 'masuk')
                    ->sum('jumlah_perubahan');
                $stokKeluar = abs((float) $mutasiPeriode
                    ->where('jenis_mutasi', 'keluar')
                    ->sum('jumlah_perubahan'));
                $penyesuaian = (float) $mutasiPeriode
                    ->where('jenis_mutasi', 'penyesuaian')
                    ->sum('jumlah_perubahan');
                $saldoAkhir = $saldoAwal + (float) $mutasiPeriode->sum('jumlah_perubahan');
                $minimum = (float) $saldo->barang->stok_minimum;

                return [
                    'saldo' => $saldo,
                    'saldo_awal' => $saldoAwal,
                    'stok_masuk' => $stokMasuk,
                    'stok_keluar' => $stokKeluar,
                    'penyesuaian' => $penyesuaian,
                    'saldo_akhir' => $saldoAkhir,
                    'jumlah_mutasi' => $mutasiPeriode->count(),
                    'status' => $saldoAkhir <= 0 ? 'Habis' : ($saldoAkhir <= $minimum ? 'Menipis' : 'Aman'),
                ];
            })
            ->sortBy(fn (array $item) => $item['saldo']->barang->nama . '|' . $item['saldo']->lokasiBarang->nama)
            ->values();

        $mutasiPeriode = MutasiStokBarang::query()
            ->with(['barang.satuanBarang', 'lokasiBarang'])
            ->whereBetween('tanggal_mutasi', [$awalPeriode->toDateString(), $akhirPeriode->toDateString()])
            ->when($filter['lokasi_barang_id'], fn (Builder $query, int $lokasiId) => $query->where('lokasi_barang_id', $lokasiId))
            ->orderByDesc('tanggal_mutasi')
            ->orderByDesc('id')
            ->get();

        $queryUnit = UnitBarang::query()
            ->where('aktif', true)
            ->when($filter['lokasi_barang_id'], fn (Builder $query, int $lokasiId) => $query->where('lokasi_barang_id', $lokasiId));

        $distribusiStatusUnit = collect(UnitBarang::DAFTAR_STATUS)
            ->map(fn (string $label, string $status) => [
                'kode' => $status,
                'label' => $label,
                'jumlah' => (clone $queryUnit)->where('status_unit', $status)->count(),
            ])
            ->values();

        $unitPerluPerhatian = (clone $queryUnit)
            ->with(['barang:id,kode,nama', 'lokasiBarang:id,nama'])
            ->where(function (Builder $query) {
                $query->whereIn('status_unit', ['dalam_perbaikan', 'hilang'])
                    ->orWhereIn('kondisi', ['rusak_ringan', 'rusak_berat']);
            })
            ->orderByRaw("case when status_unit = 'hilang' then 0 when kondisi = 'rusak_berat' then 1 else 2 end")
            ->orderBy('kode_inventaris')
            ->get();

        $barangStokBelumDicatat = Barang::query()
            ->with('satuanBarang')
            ->where('aktif', true)
            ->whereIn('tipe_pengelolaan', ['stok_dikembalikan', 'habis_pakai'])
            ->whereDoesntHave('saldoStokBarang', function (Builder $query) use ($filter) {
                $query->when($filter['lokasi_barang_id'], fn (Builder $query, int $lokasiId) => $query->where('lokasi_barang_id', $lokasiId));
            })
            ->orderBy('nama')
            ->get();

        $layananBarangPegawai = PeminjamanBarang::query()
            ->where('jenis_peminjam', 'pegawai')
            ->whereBetween('tanggal_peminjaman', [$awalPeriode->toDateString(), $akhirPeriode->toDateString()])
            ->with([
                'pegawai:id,nama_lengkap,nip,jenis_pegawai',
                'pengajuanBarang:id,nomor_pengajuan,peminjaman_barang_id',
                'detailPeminjamanBarang' => fn ($query) => $query
                    ->when($filter['lokasi_barang_id'], fn ($query, int $lokasiId) => $query
                        ->where('lokasi_barang_id', $lokasiId))
                    ->with([
                        'barang.satuanBarang',
                        'unitBarang:id,kode_inventaris',
                        'lokasiBarang:id,nama',
                    ]),
            ])
            ->when($filter['lokasi_barang_id'], fn (Builder $query, int $lokasiId) => $query
                ->whereHas('detailPeminjamanBarang', fn (Builder $query) => $query
                    ->where('lokasi_barang_id', $lokasiId)))
            ->orderByDesc('tanggal_peminjaman')
            ->orderByDesc('id')
            ->get();

        $ringkasanLayananPegawai = [
            'jumlah_layanan' => $layananBarangPegawai->count(),
            'pegawai_dilayani' => $layananBarangPegawai->pluck('pegawai_id')->filter()->unique()->count(),
            'peminjaman_aset' => $layananBarangPegawai
                ->filter(fn (PeminjamanBarang $peminjaman) => $peminjaman->detailPeminjamanBarang
                    ->contains(fn ($detail) => $detail->wajib_dikembalikan))
                ->count(),
            'penyerahan_habis_pakai' => $layananBarangPegawai
                ->filter(fn (PeminjamanBarang $peminjaman) => $peminjaman->detailPeminjamanBarang
                    ->contains(fn ($detail) => ! $detail->wajib_dikembalikan))
                ->count(),
            'pinjaman_aktif' => $layananBarangPegawai
                ->whereIn('status', ['dipinjam', 'sebagian_dikembalikan'])
                ->count(),
        ];

        return $filter + [
            'awalPeriode' => $awalPeriode,
            'akhirPeriode' => $akhirPeriode,
            'labelPeriode' => $awalPeriode->locale('id')->translatedFormat('F Y'),
            'lokasiBarang' => $lokasiBarang,
            'daftarLokasi' => LokasiBarang::query()->orderBy('nama')->get(),
            'rekapStok' => $rekapStok,
            'mutasiPeriode' => $mutasiPeriode,
            'distribusiStatusUnit' => $distribusiStatusUnit,
            'unitPerluPerhatian' => $unitPerluPerhatian,
            'barangStokBelumDicatat' => $barangStokBelumDicatat,
            'layananBarangPegawai' => $layananBarangPegawai,
            'ringkasanLayananPegawai' => $ringkasanLayananPegawai,
            'penandatangan' => [
                'wakil_sarpras' => $this->pegawaiDenganPeran('wakil_pimpinan_sarana_prasarana'),
                'petugas_inventaris' => $this->pegawaiDenganPeran('petugas_inventaris'),
                'kepala_sekolah' => $this->pegawaiDenganPeran('pimpinan'),
            ],
            'ringkasan' => $this->buatRingkasan(
                $rekapStok,
                $mutasiPeriode,
                $queryUnit,
                $unitPerluPerhatian,
                $barangStokBelumDicatat,
                $awalPeriode,
                $akhirPeriode,
            ),
        ];
    }

    private function pegawaiDenganPeran(string $kodePeran): ?Pegawai
    {
        return Pegawai::query()
            ->where('aktif', true)
            ->whereHas('pengguna', function (Builder $query) use ($kodePeran) {
                $query->where('aktif', true)
                    ->whereHas('daftarPeran', function (Builder $query) use ($kodePeran) {
                        $query->where('peran.kode', $kodePeran)
                            ->where('peran.aktif', true);
                    });
            })
            ->orderBy('nama_lengkap')
            ->first();
    }

    private function buatRingkasan(
        Collection $rekapStok,
        Collection $mutasiPeriode,
        Builder $queryUnit,
        Collection $unitPerluPerhatian,
        Collection $barangStokBelumDicatat,
        Carbon $awalPeriode,
        Carbon $akhirPeriode,
    ): array {
        return [
            'baris_stok' => $rekapStok->count(),
            'jumlah_mutasi' => $mutasiPeriode->count(),
            'stok_menipis' => $rekapStok->where('status', 'Menipis')->count(),
            'stok_habis' => $rekapStok->where('status', 'Habis')->count(),
            'stok_belum_dicatat' => $barangStokBelumDicatat->count(),
            'unit_aset' => (clone $queryUnit)->count(),
            'unit_diperoleh' => (clone $queryUnit)
                ->whereBetween('tanggal_perolehan', [$awalPeriode->toDateString(), $akhirPeriode->toDateString()])
                ->count(),
            'unit_perlu_perhatian' => $unitPerluPerhatian->count(),
        ];
    }

    private function ambilFilter(Request $request): array
    {
        $data = $request->validate([
            'periode' => ['nullable', 'date_format:Y-m'],
            'lokasi_barang_id' => ['nullable', 'integer', 'exists:lokasi_barang,id'],
        ]);

        return [
            'periode' => $data['periode'] ?? now()->format('Y-m'),
            'lokasi_barang_id' => $data['lokasi_barang_id'] ?? null,
        ];
    }
}
