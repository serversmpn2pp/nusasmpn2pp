<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PeminjamanBarang;
use App\Models\UnitBarang;
use App\Services\Inventaris\ProsesPengembalianBarang;
use App\Services\Mobile\PeminjamanBarangMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PengembalianBarangController extends Controller
{
    public function index(Request $request, PeminjamanBarangMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'cari' => ['nullable', 'string', 'max:160'],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return $this->tanpaCache(['data' => $service->daftarPengembalian($filter)]);
    }

    public function identifikasi(Request $request, PeminjamanBarangMobileService $service): JsonResponse
    {
        $data = $request->validate(['kode' => ['required', 'string', 'max:120']]);

        return $this->tanpaCache(['data' => $service->identifikasiPengembalian($data['kode'])]);
    }

    public function store(
        Request $request,
        PeminjamanBarang $peminjamanBarang,
        ProsesPengembalianBarang $proses,
        PeminjamanBarangMobileService $service,
    ): JsonResponse {
        $data = $this->rapikan($request->validate([
            'tanggal_pengembalian' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.$peminjamanBarang->tanggal_peminjaman->toDateString()],
            'catatan' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.detail_peminjaman_barang_id' => ['required', 'integer', 'exists:detail_peminjaman_barang,id'],
            'items.*.jumlah' => ['required', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'items.*.kondisi_pengembalian' => ['nullable', Rule::in(array_keys(UnitBarang::DAFTAR_KONDISI))],
            'items.*.cara_input_barang' => ['required', Rule::in(['manual', 'scan'])],
            'items.*.catatan' => ['nullable', 'string'],
        ]));
        $pengembalian = $proses->catat($peminjamanBarang, $data, $request->user()?->id);

        return $this->tanpaCache([
            'pesan' => 'Pengembalian barang berhasil dicatat.',
            'pengembalian_id' => (int) $pengembalian->id,
            'data' => $service->detail($peminjamanBarang->fresh(), true),
        ])->setStatusCode(201);
    }

    private function rapikan(array $data): array
    {
        $data['catatan'] = filled($data['catatan'] ?? null) ? trim($data['catatan']) : null;
        foreach ($data['items'] ?? [] as &$item) {
            $item['catatan'] = filled($item['catatan'] ?? null) ? trim($item['catatan']) : null;
        }

        return $data;
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
