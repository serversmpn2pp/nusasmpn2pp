<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PenerimaanBarang;
use App\Services\Mobile\LabelInventarisMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LabelInventarisController extends Controller
{
    public function __invoke(Request $request, LabelInventarisMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'jenis_label' => ['nullable', Rule::in(['unit', 'stok'])],
            'penerimaan_barang_id' => [
                'nullable',
                'integer',
                Rule::exists('penerimaan_barang', 'id')->where('status', PenerimaanBarang::STATUS_AKTIF),
            ],
            'tahun_perolehan' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'kategori_barang_id' => ['nullable', 'integer', 'exists:kategori_barang,id'],
            'barang_id' => ['nullable', 'integer', 'exists:barang,id'],
            'lokasi_barang_id' => ['nullable', 'integer', 'exists:lokasi_barang,id'],
        ]);

        return response()->json(['data' => $service->siapkan($filter)])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
