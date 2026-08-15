<?php

namespace App\Http\Controllers;

use App\Models\PengajuanBarang;
use App\Models\SaldoStokBarang;
use App\Models\UnitBarang;
use App\Services\Inventaris\ProsesPengajuanBarang;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PengajuanBarangController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'kata_kunci' => ['nullable', 'string', 'max:100'],
            'jenis' => ['nullable', Rule::in(['semua', ...array_keys(PengajuanBarang::DAFTAR_JENIS)])],
            'status' => ['nullable', Rule::in(['semua', ...array_keys(PengajuanBarang::DAFTAR_STATUS)])],
        ]);
        $kataKunci = trim((string) ($data['kata_kunci'] ?? ''));
        $jenis = $data['jenis'] ?? 'semua';
        $status = $data['status'] ?? 'menunggu';

        $pengajuanBarang = PengajuanBarang::query()
            ->with(['pegawai', 'barang.satuanBarang', 'diprosesOleh'])
            ->when($kataKunci !== '', function (Builder $query) use ($kataKunci) {
                $kataKunci = mb_strtolower($kataKunci);
                $query->where(function (Builder $query) use ($kataKunci) {
                    $query->whereRaw('LOWER(nomor_pengajuan) LIKE ?', ['%'.$kataKunci.'%'])
                        ->orWhereHas('pegawai', fn (Builder $query) => $query
                            ->whereRaw('LOWER(nama_lengkap) LIKE ?', ['%'.$kataKunci.'%']))
                        ->orWhereHas('barang', fn (Builder $query) => $query
                            ->whereRaw('LOWER(nama) LIKE ?', ['%'.$kataKunci.'%']));
                });
            })
            ->when($jenis !== 'semua', fn (Builder $query) => $query->where('jenis_pengajuan', $jenis))
            ->when($status !== 'semua', fn (Builder $query) => $query->where('status', $status))
            ->orderByRaw("CASE WHEN status = 'menunggu' THEN 0 ELSE 1 END")
            ->orderBy('tanggal_dibutuhkan')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('pengajuan-barang.index', [
            'pengajuanBarang' => $pengajuanBarang,
            'kataKunci' => $kataKunci,
            'jenis' => $jenis,
            'status' => $status,
            'daftarJenis' => PengajuanBarang::DAFTAR_JENIS,
            'daftarStatus' => PengajuanBarang::DAFTAR_STATUS,
            'ringkasan' => [
                'semua' => PengajuanBarang::count(),
                'menunggu' => PengajuanBarang::where('status', 'menunggu')->count(),
                'peminjaman' => PengajuanBarang::where('status', 'menunggu')->where('jenis_pengajuan', 'peminjaman')->count(),
                'permintaan' => PengajuanBarang::where('status', 'menunggu')->where('jenis_pengajuan', 'permintaan')->count(),
            ],
        ]);
    }

    public function show(PengajuanBarang $pengajuanBarang)
    {
        $pengajuanBarang->load([
            'pegawai',
            'barang.kategoriBarang',
            'barang.satuanBarang',
            'diprosesOleh',
            'peminjamanBarang',
        ]);

        $daftarUnit = collect();
        $daftarSaldo = collect();

        if ($pengajuanBarang->masihMenunggu()) {
            if ($pengajuanBarang->barang->tipe_pengelolaan === 'aset_individual') {
                $daftarUnit = UnitBarang::query()
                    ->where('barang_id', $pengajuanBarang->barang_id)
                    ->where('aktif', true)
                    ->where('status_unit', 'tersedia')
                    ->with('lokasiBarang')
                    ->orderBy('nomor_unit')
                    ->get();
            } else {
                $daftarSaldo = SaldoStokBarang::query()
                    ->where('barang_id', $pengajuanBarang->barang_id)
                    ->where('jumlah', '>', 0)
                    ->with('lokasiBarang')
                    ->orderBy('lokasi_barang_id')
                    ->get();
            }
        }

        return view('pengajuan-barang.show', compact('pengajuanBarang', 'daftarUnit', 'daftarSaldo'));
    }

    public function penuhi(
        Request $request,
        PengajuanBarang $pengajuanBarang,
        ProsesPengajuanBarang $prosesPengajuan,
    ) {
        $data = $request->validate([
            'unit_barang_ids' => ['nullable', 'array', 'max:100'],
            'unit_barang_ids.*' => ['integer', 'distinct', 'exists:unit_barang,id'],
            'lokasi_barang_id' => ['nullable', 'integer', 'exists:lokasi_barang,id'],
            'catatan_petugas' => ['nullable', 'string', 'max:1000'],
        ]);

        $pengajuan = $prosesPengajuan->penuhi($pengajuanBarang, $data, (int) $request->user()->id);

        return redirect()
            ->route('pengajuan-barang.show', $pengajuan)
            ->with('berhasil', 'Pengajuan dipenuhi dan transaksi barang berhasil dicatat.');
    }

    public function tolak(
        Request $request,
        PengajuanBarang $pengajuanBarang,
        ProsesPengajuanBarang $prosesPengajuan,
    ) {
        $data = $request->validate([
            'catatan_petugas' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $pengajuan = $prosesPengajuan->tolak(
            $pengajuanBarang,
            $data['catatan_petugas'],
            (int) $request->user()->id,
        );

        return redirect()
            ->route('pengajuan-barang.show', $pengajuan)
            ->with('berhasil', 'Pengajuan ditolak dan pemohon sudah diberi notifikasi.');
    }
}
