<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\LokasiBarang;
use App\Models\PenerimaanBarang;
use App\Models\SumberPerolehanBarang;
use App\Models\UnitBarang;
use App\Services\Inventaris\ProsesPenerimaanBarang;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PenerimaanBarangController extends Controller
{
    public function index(Request $request)
    {
        $kataKunci = trim((string) $request->input('kata_kunci', ''));
        $sumberPerolehanId = $request->input('sumber_perolehan_barang_id', 'semua');
        $tanggalMulai = $request->input('tanggal_mulai');
        $tanggalSelesai = $request->input('tanggal_selesai');

        $request->validate([
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ]);

        $penerimaanBarang = PenerimaanBarang::query()
            ->with(['sumberPerolehanBarang', 'dibuatOleh'])
            ->withCount('detailPenerimaanBarang')
            ->when($sumberPerolehanId !== 'semua', fn ($query) => $query->where('sumber_perolehan_barang_id', $sumberPerolehanId))
            ->when($tanggalMulai, fn ($query) => $query->whereDate('tanggal_penerimaan', '>=', $tanggalMulai))
            ->when($tanggalSelesai, fn ($query) => $query->whereDate('tanggal_penerimaan', '<=', $tanggalSelesai))
            ->when($kataKunci !== '', function ($query) use ($kataKunci) {
                $query->where(function ($query) use ($kataKunci) {
                    $query->where('nomor_penerimaan', 'ilike', '%'.$kataKunci.'%')
                        ->orWhere('nomor_dokumen', 'ilike', '%'.$kataKunci.'%')
                        ->orWhere('asal_barang', 'ilike', '%'.$kataKunci.'%')
                        ->orWhereHas('detailPenerimaanBarang.barang', function ($query) use ($kataKunci) {
                            $query->where('nama', 'ilike', '%'.$kataKunci.'%')
                                ->orWhere('kode', 'ilike', '%'.$kataKunci.'%');
                        });
                });
            })
            ->orderByDesc('tanggal_penerimaan')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('penerimaan-barang.index', [
            'penerimaanBarang' => $penerimaanBarang,
            'kataKunci' => $kataKunci,
            'sumberPerolehanId' => $sumberPerolehanId,
            'tanggalMulai' => $tanggalMulai,
            'tanggalSelesai' => $tanggalSelesai,
            'daftarSumberPerolehan' => SumberPerolehanBarang::orderByDesc('aktif')->orderBy('nama')->get(),
            'jumlahPenerimaan' => PenerimaanBarang::count(),
            'jumlahHariIni' => PenerimaanBarang::whereDate('tanggal_penerimaan', now()->toDateString())->count(),
            'jumlahUnitDibuat' => UnitBarang::whereNotNull('detail_penerimaan_barang_id')->count(),
            'jumlahJenisStokMasuk' => PenerimaanBarang::query()
                ->join('detail_penerimaan_barang', 'detail_penerimaan_barang.penerimaan_barang_id', '=', 'penerimaan_barang.id')
                ->join('barang', 'barang.id', '=', 'detail_penerimaan_barang.barang_id')
                ->where('barang.jenis_barang', 'habis_pakai')
                ->count('detail_penerimaan_barang.id'),
        ]);
    }

    public function create()
    {
        return view('penerimaan-barang.create', [
            'daftarBarang' => Barang::query()
                ->with('satuanBarang')
                ->where('aktif', true)
                ->orderBy('nama')
                ->get(),
            'daftarLokasi' => LokasiBarang::where('aktif', true)->orderBy('nama')->get(),
            'daftarSumberPerolehan' => SumberPerolehanBarang::where('aktif', true)->orderBy('nama')->get(),
            'daftarCaraPerolehan' => PenerimaanBarang::DAFTAR_CARA_PEROLEHAN,
            'daftarKondisi' => UnitBarang::DAFTAR_KONDISI,
        ]);
    }

    public function store(Request $request, ProsesPenerimaanBarang $prosesPenerimaan)
    {
        $data = $request->validate([
            'tanggal_penerimaan' => ['required', 'date', 'before_or_equal:today'],
            'sumber_perolehan_barang_id' => [
                'required',
                'integer',
                Rule::exists('sumber_perolehan_barang', 'id')->where('aktif', true),
            ],
            'cara_perolehan' => ['required', Rule::in(array_keys(PenerimaanBarang::DAFTAR_CARA_PEROLEHAN))],
            'nomor_dokumen' => ['nullable', 'string', 'max:120'],
            'asal_barang' => ['nullable', 'string', 'max:160'],
            'catatan' => ['nullable', 'string'],
            'rincian' => ['required', 'array', 'min:1', 'max:50'],
            'rincian.*.barang_id' => ['required', 'integer', 'exists:barang,id'],
            'rincian.*.lokasi_barang_id' => ['required', 'integer', 'exists:lokasi_barang,id'],
            'rincian.*.jumlah' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'rincian.*.harga_satuan' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'rincian.*.merek' => ['nullable', 'string', 'max:120'],
            'rincian.*.tipe' => ['nullable', 'string', 'max:120'],
            'rincian.*.kondisi' => ['nullable', Rule::in(array_keys(UnitBarang::DAFTAR_KONDISI))],
            'rincian.*.keterangan' => ['nullable', 'string', 'max:1000'],
        ], [
            'rincian.required' => 'Tambahkan minimal satu barang yang diterima.',
            'rincian.min' => 'Tambahkan minimal satu barang yang diterima.',
            'rincian.*.barang_id.required' => 'Barang pada setiap baris wajib dipilih.',
            'rincian.*.lokasi_barang_id.required' => 'Lokasi penyimpanan pada setiap baris wajib dipilih.',
            'rincian.*.jumlah.gt' => 'Jumlah barang harus lebih dari nol.',
        ]);

        $data = $this->rapikanData($data);
        $penerimaan = $prosesPenerimaan->catat($data, $request->user()?->id);

        return redirect()
            ->route('penerimaan-barang.show', $penerimaan)
            ->with('berhasil', 'Barang datang berhasil dicatat dan inventaris telah diperbarui.');
    }

    public function show(PenerimaanBarang $penerimaanBarang)
    {
        $penerimaanBarang->load([
            'sumberPerolehanBarang',
            'dibuatOleh',
            'detailPenerimaanBarang.barang.satuanBarang',
            'detailPenerimaanBarang.lokasiBarang',
            'detailPenerimaanBarang.mutasiStokBarang',
            'detailPenerimaanBarang.unitBarang',
        ]);

        return view('penerimaan-barang.show', compact('penerimaanBarang'));
    }

    private function rapikanData(array $data): array
    {
        foreach (['nomor_dokumen', 'asal_barang', 'catatan'] as $kolom) {
            $data[$kolom] = filled($data[$kolom] ?? null) ? trim($data[$kolom]) : null;
        }

        $data['rincian'] = collect($data['rincian'])->map(function (array $rincian) {
            foreach (['merek', 'tipe', 'keterangan'] as $kolom) {
                $rincian[$kolom] = filled($rincian[$kolom] ?? null) ? trim($rincian[$kolom]) : null;
            }

            $rincian['harga_satuan'] = filled($rincian['harga_satuan'] ?? null)
                ? $rincian['harga_satuan']
                : null;

            return $rincian;
        })->values()->all();

        return $data;
    }
}
