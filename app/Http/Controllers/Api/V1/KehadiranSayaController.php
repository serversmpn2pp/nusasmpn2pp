<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Mobile\KehadiranSayaMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KehadiranSayaController extends Controller
{
    public function __invoke(Request $request, KehadiranSayaMobileService $service): JsonResponse
    {
        $pengguna = $request->user();
        abort_unless(
            $pengguna?->akunSiswa()
                || $pengguna?->akunOrangTua(),
            403,
        );

        $filter = $request->validate([
            'siswa_id' => ['nullable', 'integer', Rule::exists('siswa', 'id')],
            'tahun_pelajaran_id' => ['nullable', 'integer', Rule::exists('tahun_pelajaran', 'id')],
            'bulan' => ['nullable', 'date_format:Y-m'],
        ]);

        return response()->json([
            'data' => $service->tampilkan($pengguna, $filter),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
