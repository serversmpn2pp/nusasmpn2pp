<?php

namespace App\Http\Controllers;

use App\Models\DetailPeminjamanBarang;
use App\Models\PeminjamanBarang;
use App\Models\UnitBarang;
use App\Services\Inventaris\ProsesPengembalianBarang;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PengembalianBarangController extends Controller
{
    public function index(Request $request)
    {
        return view('pengembalian-barang.index', [
            'kodeAwal' => strtoupper(substr(trim((string) $request->query('kode', '')), 0, 120)),
        ]);
    }

    public function identifikasi(Request $request)
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:120'],
        ]);
        $kode = strtoupper(trim($data['kode']));

        $unitBarang = UnitBarang::query()
            ->with(['barang', 'lokasiBarang'])
            ->whereRaw('LOWER(kode_inventaris) = ?', [strtolower($kode)])
            ->first();

        if (! $unitBarang) {
            return response()->json([
                'pesan' => str_starts_with($kode, 'BHP-')
                    ? 'Barang habis pakai tidak memiliki proses pengembalian.'
                    : 'Barcode aset tidak ditemukan. Gunakan barcode internal yang diawali AST.',
            ], 422);
        }

        $detail = DetailPeminjamanBarang::query()
            ->with([
                'barang.satuanBarang',
                'unitBarang',
                'lokasiBarang',
                'peminjamanBarang.siswa',
                'peminjamanBarang.pegawai',
            ])
            ->where('unit_barang_id', $unitBarang->id)
            ->where('wajib_dikembalikan', true)
            ->whereColumn('jumlah_dikembalikan', '<', 'jumlah')
            ->whereHas('peminjamanBarang', fn ($query) => $query->whereIn('status', ['dipinjam', 'sebagian_dikembalikan']))
            ->latest('id')
            ->first();

        if (! $detail) {
            return response()->json([
                'pesan' => $unitBarang->status_unit === 'tersedia'
                    ? 'Aset ini tidak sedang dipinjam atau sudah dikembalikan.'
                    : 'Tidak ditemukan transaksi peminjaman aktif untuk aset ini. Periksa riwayat unit secara manual.',
            ], 422);
        }

        $peminjaman = $detail->peminjamanBarang;

        return response()->json([
            'item' => [
                'kode' => $unitBarang->kode_inventaris,
                'nama_barang' => $detail->barang->nama,
                'nomor_aset_resmi' => $unitBarang->nomor_aset_resmi ?: '-',
                'lokasi_asal' => $detail->lokasiBarang?->nama ?: 'Tanpa lokasi',
                'kondisi_tercatat' => $unitBarang->labelKondisi(),
                'nomor_peminjaman' => $peminjaman->nomor_peminjaman,
                'nama_peminjam' => $peminjaman->namaPeminjam(),
                'identitas_peminjam' => $peminjaman->identitasPeminjam(),
                'tanggal_peminjaman' => $peminjaman->tanggal_peminjaman->locale('id')->translatedFormat('d F Y'),
                'rencana_kembali' => $peminjaman->rencana_kembali?->locale('id')->translatedFormat('d F Y') ?: '-',
                'url_konfirmasi' => route('pengembalian-barang.create', [
                    'peminjamanBarang' => $peminjaman,
                    'kode' => $unitBarang->kode_inventaris,
                ]),
            ],
        ]);
    }

    public function create(Request $request, PeminjamanBarang $peminjamanBarang)
    {
        $peminjamanBarang->load([
            'siswa',
            'pegawai',
            'detailPeminjamanBarang' => fn ($query) => $query
                ->where('wajib_dikembalikan', true)
                ->whereColumn('jumlah_dikembalikan', '<', 'jumlah')
                ->with(['barang.satuanBarang', 'unitBarang', 'lokasiBarang']),
        ]);

        abort_if($peminjamanBarang->status === 'selesai', 404);

        $kodeDipindai = strtoupper(substr(trim((string) $request->query('kode', '')), 0, 120));
        $kodeDipindaiValid = $kodeDipindai !== '' && $peminjamanBarang->detailPeminjamanBarang->contains(
            fn ($detail) => strcasecmp($detail->unitBarang?->kode_inventaris ?: $detail->barang->kode, $kodeDipindai) === 0,
        );

        return view('pengembalian-barang.create', [
            'peminjamanBarang' => $peminjamanBarang,
            'daftarKondisi' => UnitBarang::DAFTAR_KONDISI,
            'kodeDipindai' => $kodeDipindaiValid ? $kodeDipindai : '',
        ]);
    }

    public function store(Request $request, PeminjamanBarang $peminjamanBarang, ProsesPengembalianBarang $prosesPengembalian)
    {
        $data = $this->rapikanData($request->validate([
            'tanggal_pengembalian' => ['required', 'date', 'after_or_equal:'.$peminjamanBarang->tanggal_peminjaman->toDateString()],
            'catatan' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.detail_peminjaman_barang_id' => ['required', 'integer', 'exists:detail_peminjaman_barang,id'],
            'items.*.jumlah' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'items.*.kondisi_pengembalian' => ['nullable', Rule::in(array_keys(UnitBarang::DAFTAR_KONDISI))],
            'items.*.cara_input_barang' => ['required', Rule::in(['manual', 'scan'])],
            'items.*.catatan' => ['nullable', 'string'],
        ]));

        $prosesPengembalian->catat($peminjamanBarang, $data, $request->user()?->id);

        return redirect()
            ->route('peminjaman-barang.show', $peminjamanBarang)
            ->with('berhasil', 'Pengembalian barang berhasil dicatat.');
    }

    private function rapikanData(array $data): array
    {
        $data['catatan'] = filled($data['catatan'] ?? null) ? trim($data['catatan']) : null;

        foreach ($data['items'] ?? [] as &$item) {
            $item['catatan'] = filled($item['catatan'] ?? null) ? trim($item['catatan']) : null;
        }

        return $data;
    }
}
