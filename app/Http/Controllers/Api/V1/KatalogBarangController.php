<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Services\Mobile\KatalogBarangMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KatalogBarangController extends Controller
{
    public function index(Request $request, KatalogBarangMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'kata_kunci' => ['nullable', 'string', 'max:100'],
            'kategori_barang_id' => ['nullable', 'integer', 'exists:kategori_barang,id'],
            'jenis_barang' => ['nullable', Rule::in(['semua', ...array_keys(Barang::DAFTAR_JENIS_BARANG)])],
            'ketersediaan' => ['nullable', Rule::in(array_keys(KatalogBarangMobileService::DAFTAR_KETERSEDIAAN))],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return $this->respons(['data' => $service->daftar($filter, $request->user()->akunPegawai())]);
    }

    public function show(Barang $barang, Request $request, KatalogBarangMobileService $service): JsonResponse
    {
        return $this->respons(['data' => $service->detail($barang, $request->user()->akunPegawai())]);
    }

    private function respons(array $data): JsonResponse
    {
        return response()->json($data)->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
