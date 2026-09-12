<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Mobile\AkademikAnakMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AkademikAnakController extends Controller
{
    public function __invoke(Request $request, AkademikAnakMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'tab' => ['nullable', Rule::in(['jadwal', 'nilai'])],
            'siswa_id' => ['nullable', 'integer', Rule::exists('siswa', 'id')],
            'tahun_pelajaran_id' => ['nullable', 'integer', Rule::exists('tahun_pelajaran', 'id')],
            'semester' => ['nullable', Rule::in(['ganjil', 'genap'])],
        ]);

        return response()->json([
            'data' => $service->tampilkan($request->user(), $filter),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
