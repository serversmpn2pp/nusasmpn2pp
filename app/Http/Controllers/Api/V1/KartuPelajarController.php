<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Mobile\KartuPelajarMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KartuPelajarController extends Controller
{
    public function __invoke(Request $request, KartuPelajarMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'siswa_id' => ['nullable', 'integer', 'exists:siswa,id'],
            'cari' => ['nullable', 'string', 'max:100'],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:40'],
        ]);

        return response()->json([
            'data' => $service->daftar($request->user(), $filter),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
