<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Siswa;
use App\Services\Mobile\RekapPoinSiswaMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RekapPoinSiswaController extends Controller
{
    public function index(Request $request, RekapPoinSiswaMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'kata_kunci' => ['nullable', 'string', 'max:120'],
            'status_perhatian' => ['nullable', Rule::in([
                'semua',
                'berpoin',
                'mendekati_sanksi',
                'menunggu_verifikasi',
                'sanksi_aktif',
            ])],
            'tahun_pelajaran_id' => ['nullable', 'integer', Rule::exists('tahun_pelajaran', 'id')],
            'kelas_id' => ['nullable', 'integer', Rule::exists('kelas', 'id')],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:30'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($request->user(), $filter)]);
    }

    public function show(
        Request $request,
        Siswa $siswa,
        RekapPoinSiswaMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', Rule::exists('tahun_pelajaran', 'id')],
        ]);

        return $this->tanpaCache([
            'data' => $service->rincian(
                $request->user(),
                $siswa,
                isset($data['tahun_pelajaran_id']) ? (int) $data['tahun_pelajaran_id'] : null,
            ),
        ]);
    }

    private function tanpaCache(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
