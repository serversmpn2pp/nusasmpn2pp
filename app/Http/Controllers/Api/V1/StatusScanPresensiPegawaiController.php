<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Mobile\StatusScanPresensiPegawaiMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class StatusScanPresensiPegawaiController extends Controller
{
    public function __invoke(Request $request, StatusScanPresensiPegawaiMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'jenis_pegawai' => ['nullable', 'string', 'max:100'],
            'status' => [
                'nullable',
                Rule::in(['semua', 'berhasil', 'sudah_tercatat', 'perlu_perhatian', 'masuk', 'pulang', 'terlambat']),
            ],
            'cari' => ['nullable', 'string', 'max:100'],
            'batas' => ['nullable', 'integer', 'min:10', 'max:100'],
        ]);

        return response()->json([
            'data' => $service->statusHariIni($filter),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
