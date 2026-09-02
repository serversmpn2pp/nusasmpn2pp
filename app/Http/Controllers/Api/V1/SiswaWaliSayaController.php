<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Services\Mobile\SiswaWaliSayaMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SiswaWaliSayaController extends Controller
{
    public function index(Request $request, SiswaWaliSayaMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'kata_kunci' => ['nullable', 'string', 'max:120'],
            'tingkat' => ['nullable', 'integer', Rule::in([7, 8, 9])],
            'kelas_id' => ['nullable', 'integer', Rule::exists('kelas', 'id')],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:30'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($request->user(), $filter)]);
    }

    public function show(
        Request $request,
        Siswa $siswa,
        SiswaWaliSayaMobileService $service,
    ): JsonResponse {
        return $this->tanpaCache(['data' => $service->rincian($request->user(), $siswa)]);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
