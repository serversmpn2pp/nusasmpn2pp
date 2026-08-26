<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Mobile\JadwalMengajarSayaMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JadwalMengajarSayaController extends Controller
{
    public function __invoke(
        Request $request,
        JadwalMengajarSayaMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'tahun_pelajaran_id' => [
                'nullable',
                'integer',
                Rule::exists('tahun_pelajaran', 'id'),
            ],
        ]);

        return response()->json([
            'data' => $service->daftar(
                $request->user(),
                isset($data['tahun_pelajaran_id'])
                    ? (int) $data['tahun_pelajaran_id']
                    : null,
            ),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
