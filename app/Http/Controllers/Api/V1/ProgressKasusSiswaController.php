<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LaporanPembinaanSiswa;
use App\Services\Mobile\ProgressKasusSiswaMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProgressKasusSiswaController extends Controller
{
    public function index(Request $request, ProgressKasusSiswaMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);

        return $this->tanpaCache([
            'data' => $service->daftar(
                $request->user(),
                (int) ($filter['halaman'] ?? 1),
                (int) ($filter['per_halaman'] ?? 10),
            ),
        ]);
    }

    public function show(
        Request $request,
        LaporanPembinaanSiswa $laporanPembinaanSiswa,
        ProgressKasusSiswaMobileService $service,
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
