<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Mobile\StokBarangMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SaldoStokBarangController extends Controller
{
    public function __invoke(Request $request, StokBarangMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'cari' => ['nullable', 'string', 'max:160'],
            'status_stok' => ['nullable', Rule::in(['semua', 'aman', 'menipis', 'habis'])],
            'kategori_barang_id' => ['nullable', 'integer', 'exists:kategori_barang,id'],
            'lokasi_barang_id' => ['nullable', 'integer', 'exists:lokasi_barang,id'],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return response()->json([
            'data' => $service->daftarSaldo($filter, $request->user()->memilikiIzin('barang.kelola')),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
