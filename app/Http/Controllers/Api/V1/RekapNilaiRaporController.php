<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Mobile\RekapNilaiRaporMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RekapNilaiRaporController extends Controller
{
    public function __invoke(Request $request, RekapNilaiRaporMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'guru_mata_pelajaran_id' => ['nullable', 'integer', Rule::exists('guru_mata_pelajaran', 'id')],
            'semester' => ['nullable', Rule::in(['ganjil', 'genap'])],
        ]);

        return response()->json([
            'data' => $service->tampilkan($filter),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
