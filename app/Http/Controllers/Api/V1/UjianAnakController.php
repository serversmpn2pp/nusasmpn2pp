<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Mobile\UjianAnakMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UjianAnakController extends Controller
{
    public function __invoke(Request $request, UjianAnakMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'siswa_id' => ['nullable', 'integer', Rule::exists('siswa', 'id')],
        ]);

        return response()->json([
            'data' => $service->tampilkan(
                $request->user(),
                isset($filter['siswa_id']) ? (int) $filter['siswa_id'] : null,
            ),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
