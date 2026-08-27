<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Mobile\NilaiSayaMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NilaiSayaController extends Controller
{
    public function __invoke(Request $request, NilaiSayaMobileService $service): JsonResponse
    {
        $pengguna = $request->user();
        abort_unless($pengguna?->akunSiswa() || $pengguna?->memilikiPeran('siswa'), 403);

        $filter = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', Rule::exists('tahun_pelajaran', 'id')],
            'semester' => ['nullable', Rule::in(['ganjil', 'genap'])],
        ]);

        return response()->json([
            'data' => $service->tampilkan($pengguna, $filter),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
