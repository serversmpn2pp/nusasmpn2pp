<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GuruMataPelajaran;
use App\Services\Mobile\MonitoringSurveiMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HasilSurveiSayaController extends Controller
{
    public function index(Request $request, MonitoringSurveiMobileService $service): JsonResponse
    {
        $pegawaiId = $this->pegawaiId($request);
        $filter = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', Rule::exists('tahun_pelajaran', 'id')],
            'semester' => ['nullable', Rule::in(['ganjil', 'genap'])],
            'status' => ['nullable', Rule::in(['semua', 'belum', 'berjalan', 'lengkap'])],
            'cari' => ['nullable', 'string', 'max:100'],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return $this->tanpaCache([
            'data' => $service->daftar($filter, $pegawaiId),
        ]);
    }

    public function show(
        Request $request,
        GuruMataPelajaran $guruMataPelajaran,
        MonitoringSurveiMobileService $service,
    ): JsonResponse {
        abort_unless($guruMataPelajaran->pegawai_id === $this->pegawaiId($request), 403);

        $data = $request->validate([
            'semester' => ['required', Rule::in(['ganjil', 'genap'])],
        ]);

        return $this->tanpaCache([
            'data' => $service->rincian($guruMataPelajaran, $data['semester']),
        ]);
    }

    private function pegawaiId(Request $request): int
    {
        $pegawaiId = $request->user()?->pegawai_id;
        abort_unless($pegawaiId, 403);

        return (int) $pegawaiId;
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
