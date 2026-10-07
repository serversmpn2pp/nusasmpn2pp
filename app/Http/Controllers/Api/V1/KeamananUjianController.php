<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PesertaUjianCbt;
use App\Services\Cbt\KeamananUjianService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KeamananUjianController extends Controller
{
    public function reset(Request $request, PesertaUjianCbt $pesertaUjianCbt, KeamananUjianService $service): JsonResponse
    {
        $data = $request->validate(['alasan' => ['required', 'string', 'min:10', 'max:500']]);

        return response()->json(['data' => $service->resetPerangkat($request->user(), $pesertaUjianCbt, $data['alasan'])])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function buka(
        Request $request,
        PesertaUjianCbt $pesertaUjianCbt,
        KeamananUjianService $service,
    ): JsonResponse {
        return response()->json([
            'pesan' => 'Ujian peserta sudah dibuka dan dapat dilanjutkan.',
            'data' => $service->bukaTahanan($request->user(), $pesertaUjianCbt),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
