<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PesertaUjianCbt;
use App\Services\Mobile\KeamananUjianMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KeamananUjianController extends Controller
{
    public function buka(
        Request $request,
        PesertaUjianCbt $pesertaUjianCbt,
        KeamananUjianMobileService $service,
    ): JsonResponse {
        return response()->json([
            'pesan' => 'Ujian peserta sudah dibuka dan dapat dilanjutkan.',
            'data' => $service->bukaTahanan($request->user(), $pesertaUjianCbt),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
