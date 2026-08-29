<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Mobile\RingkasanKegiatanIbadahBulananMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RingkasanKegiatanIbadahBulananController extends Controller
{
    public function __invoke(Request $request, RingkasanKegiatanIbadahBulananMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'bulan' => ['nullable', 'date_format:Y-m'],
            'kegiatan_ibadah_id' => ['nullable', 'integer', 'exists:kegiatan_ibadah,id'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
        ]);

        return response()
            ->json(['data' => $service->ringkasan($request->user(), $filter)])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
