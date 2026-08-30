<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LaporanPembinaanSiswa;
use App\Services\Mobile\PemeriksaanPengesahanMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PemeriksaanPengesahanController extends Controller
{
    public function index(Request $request, PemeriksaanPengesahanMobileService $service): JsonResponse
    {
        $data = $request->validate([
            'kata_kunci' => ['nullable', 'string', 'max:120'],
            'antrean' => ['nullable', Rule::in(array_keys(PemeriksaanPengesahanMobileService::DAFTAR_ANTREAN))],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:30'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($request->user(), $data)]);
    }

    public function show(
        Request $request,
        LaporanPembinaanSiswa $laporanPembinaanSiswa,
        PemeriksaanPengesahanMobileService $service,
    ): JsonResponse {
        return $this->tanpaCache([
            'data' => $service->rincian($request->user(), $laporanPembinaanSiswa),
        ]);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
