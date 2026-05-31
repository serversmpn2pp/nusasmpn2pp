<?php

namespace App\Http\Controllers;

use App\Models\PeminjamanBarang;
use App\Models\UnitBarang;
use App\Services\Inventaris\ProsesPengembalianBarang;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PengembalianBarangController extends Controller
{
    public function create(PeminjamanBarang $peminjamanBarang)
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

        return view('pengembalian-barang.create', [
            'peminjamanBarang' => $peminjamanBarang,
            'daftarKondisi' => UnitBarang::DAFTAR_KONDISI,
        ]);
    }

    public function store(Request $request, PeminjamanBarang $peminjamanBarang, ProsesPengembalianBarang $prosesPengembalian)
    {
        $data = $this->rapikanData($request->validate([
            'tanggal_pengembalian' => ['required', 'date', 'after_or_equal:' . $peminjamanBarang->tanggal_peminjaman->toDateString()],
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
