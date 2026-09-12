<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LaporanPembinaanSiswa;
use App\Services\Mobile\PembinaanPoinAnakMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PembinaanPoinAnakController extends Controller
{
    public function index(Request $request, PembinaanPoinAnakMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'tab' => ['nullable', Rule::in(['laporan', 'poin'])],
            'siswa_id' => ['nullable', 'integer', Rule::exists('siswa', 'id')],
            'tahun_pelajaran_id' => ['nullable', 'integer', Rule::exists('tahun_pelajaran', 'id')],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        return $this->tanpaCache([
            'data' => $service->daftar($request->user(), $filter),
        ]);
    }

    public function show(
        Request $request,
        LaporanPembinaanSiswa $laporanPembinaanSiswa,
        PembinaanPoinAnakMobileService $service,
    ): JsonResponse {
        return $this->tanpaCache([
            'data' => $service->detail($request->user(), $laporanPembinaanSiswa),
        ]);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
