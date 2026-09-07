<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Mobile\LaporanInventarisBulananMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LaporanInventarisBulananController extends Controller
{
    public function __invoke(Request $request, LaporanInventarisBulananMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'periode' => ['nullable', 'date_format:Y-m'],
            'lokasi_barang_id' => ['nullable', 'integer', 'exists:lokasi_barang,id'],
        ]);

        return response()
            ->json(['data' => $service->siapkan($filter)])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
