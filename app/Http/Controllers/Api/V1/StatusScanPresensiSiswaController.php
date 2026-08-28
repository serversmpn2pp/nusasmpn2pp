<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Mobile\StatusScanPresensiSiswaMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StatusScanPresensiSiswaController extends Controller
{
    public function __invoke(Request $request, StatusScanPresensiSiswaMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'status' => [
                'nullable',
                Rule::in(['semua', 'berhasil', 'sudah_tercatat', 'perlu_perhatian', 'masuk', 'pulang', 'terlambat']),
            ],
            'cari' => ['nullable', 'string', 'max:100'],
            'batas' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        return response()->json([
            'data' => $service->statusHariIni($request->user(), $filter),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
