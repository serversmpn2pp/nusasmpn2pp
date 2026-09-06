<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PeminjamanBarang;
use App\Services\Mobile\PeminjamanBarangMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RekapPeminjamanBarangController extends Controller
{
    public function index(Request $request, PeminjamanBarangMobileService $service): JsonResponse
    {
        return $this->tanpaCache([
            'data' => $service->rekap(
                $this->filter($request, true),
                $request->user()->memilikiIzin('barang.peminjaman_kelola'),
            ),
        ]);
    }

    public function document(Request $request, PeminjamanBarangMobileService $service): JsonResponse
    {
        return $this->tanpaCache([
            'data' => $service->rekap(
                $this->filter($request),
                $request->user()->memilikiIzin('barang.peminjaman_kelola'),
                true,
            ),
        ]);
    }

    private function filter(Request $request, bool $denganPaginasi = false): array
    {
        return $request->validate([
            'kata_kunci' => ['nullable', 'string', 'max:100'],
            'status_pemantauan' => ['nullable', Rule::in(array_keys(PeminjamanBarangMobileService::STATUS_PEMANTAUAN))],
            'jenis_peminjam' => ['nullable', Rule::in(['semua', ...array_keys(PeminjamanBarang::DAFTAR_JENIS_PEMINJAM)])],
            'peminjam' => ['nullable', 'regex:/^(siswa|pegawai):[1-9][0-9]*$/'],
            'barang_id' => ['nullable', 'integer', 'exists:barang,id'],
            'tanggal_mulai' => ['nullable', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'halaman' => $denganPaginasi ? ['nullable', 'integer', 'min:1'] : ['prohibited'],
            'per_halaman' => $denganPaginasi ? ['nullable', 'integer', 'min:5', 'max:50'] : ['prohibited'],
        ]);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
