<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PeminjamanBarang;
use App\Services\Inventaris\ProsesPeminjamanBarang;
use App\Services\Mobile\PeminjamanBarangMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PeminjamanBarangController extends Controller
{
    public function index(Request $request, PeminjamanBarangMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'cari' => ['nullable', 'string', 'max:160'],
            'jenis_peminjam' => ['nullable', Rule::in(['semua', ...array_keys(PeminjamanBarang::DAFTAR_JENIS_PEMINJAM)])],
            'status' => ['nullable', Rule::in(['semua', ...array_keys(PeminjamanBarang::DAFTAR_STATUS)])],
            'tanggal_mulai' => ['nullable', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar(
            $filter,
            $request->user()->memilikiIzin('barang.peminjaman_kelola'),
        )]);
    }

    public function show(PeminjamanBarang $peminjamanBarang, Request $request, PeminjamanBarangMobileService $service): JsonResponse
    {
        return $this->tanpaCache(['data' => $service->detail(
            $peminjamanBarang,
            $request->user()->memilikiIzin('barang.peminjaman_kelola'),
        )]);
    }

    public function store(Request $request, ProsesPeminjamanBarang $proses, PeminjamanBarangMobileService $service): JsonResponse
    {
        $data = $this->rapikan($request->validate([
            'jenis_peminjam' => ['required', Rule::in(array_keys(PeminjamanBarang::DAFTAR_JENIS_PEMINJAM))],
            'siswa_id' => ['nullable', 'integer', 'exists:siswa,id'],
            'pegawai_id' => ['nullable', 'integer', 'exists:pegawai,id'],
            'cara_input_peminjam' => ['required', Rule::in(['manual', 'scan'])],
            'tanggal_peminjaman' => ['required', 'date_format:Y-m-d'],
            'rencana_kembali' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tanggal_peminjaman'],
            'catatan' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.tipe_item' => ['required', Rule::in(['unit', 'stok'])],
            'items.*.unit_barang_id' => ['nullable', 'integer', 'exists:unit_barang,id'],
            'items.*.barang_id' => ['nullable', 'integer', 'exists:barang,id'],
            'items.*.lokasi_barang_id' => ['nullable', 'integer', 'exists:lokasi_barang,id'],
            'items.*.jumlah' => ['nullable', 'numeric', 'min:0.01', 'max:999999999999.99'],
            'items.*.cara_input_barang' => ['required', Rule::in(['manual', 'scan', 'campuran'])],
            'items.*.catatan' => ['nullable', 'string'],
        ]));
        $peminjaman = $proses->catat($data, $request->user()?->id);

        return $this->tanpaCache([
            'pesan' => 'Transaksi peminjaman barang berhasil dicatat.',
            'data' => $service->detail($peminjaman, true),
        ])->setStatusCode(201);
    }

    public function identifikasiPeminjam(Request $request, PeminjamanBarangMobileService $service): JsonResponse
    {
        $data = $request->validate([
            'jenis_peminjam' => ['nullable', Rule::in(['otomatis', ...array_keys(PeminjamanBarang::DAFTAR_JENIS_PEMINJAM)])],
            'kode' => ['required', 'string', 'max:100'],
        ]);

        return $this->tanpaCache(['data' => $service->identifikasiPeminjam(
            $data['kode'],
            $data['jenis_peminjam'] ?? 'otomatis',
        )]);
    }

    public function identifikasiBarang(Request $request, PeminjamanBarangMobileService $service): JsonResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', 'max:120'],
            'lokasi_barang_id' => ['nullable', 'integer', 'exists:lokasi_barang,id'],
        ]);

        return $this->tanpaCache(['data' => $service->identifikasiBarang(
            $data['kode'],
            isset($data['lokasi_barang_id']) ? (int) $data['lokasi_barang_id'] : null,
        )]);
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
