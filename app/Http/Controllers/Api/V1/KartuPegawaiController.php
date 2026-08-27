<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Mobile\KartuPegawaiMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KartuPegawaiController extends Controller
{
    public function __invoke(Request $request, KartuPegawaiMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'jenis_pegawai' => ['nullable', 'string', 'max:100'],
            'pegawai_id' => ['nullable', 'integer', 'exists:pegawai,id'],
            'status' => ['nullable', Rule::in(['semua', 'aktif', 'nonaktif'])],
            'cari' => ['nullable', 'string', 'max:100'],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:30'],
        ]);

        return response()->json([
            'data' => $service->daftar($request->user(), $filter),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
