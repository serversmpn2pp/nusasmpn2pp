<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Services\Mobile\SiswaMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SiswaController extends Controller
{
    public function index(Request $request, SiswaMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'cari' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:semua,aktif,nonaktif'],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return $this->tanpaCache([
            'data' => $service->daftar($request->user(), $filter),
        ]);
    }

    public function show(Request $request, Siswa $siswa, SiswaMobileService $service): JsonResponse
    {
        return $this->tanpaCache([
            'data' => $service->detail($request->user(), $siswa),
        ]);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()
            ->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
