<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PertanyaanSurveiPembelajaran;
use App\Services\Mobile\PernyataanSurveiMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PernyataanSurveiController extends Controller
{
    public function index(Request $request, PernyataanSurveiMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'cari' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['semua', 'aktif', 'nonaktif'])],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($filter)]);
    }

    public function store(Request $request, PernyataanSurveiMobileService $service): JsonResponse
    {
        $pertanyaan = $service->tambah($request->validate([
            ...$this->aturanValidasi(),
            'aktif' => ['required', 'boolean'],
        ]));

        return $this->tanpaCache([
            'pesan' => 'Pernyataan survei berhasil ditambahkan.',
            'data' => ['id' => (int) $pertanyaan->id],
        ])->setStatusCode(201);
    }

    public function update(
        Request $request,
        PertanyaanSurveiPembelajaran $pertanyaanSurveiPembelajaran,
        PernyataanSurveiMobileService $service,
    ): JsonResponse {
        $service->ubah(
            $pertanyaanSurveiPembelajaran,
            $request->validate($this->aturanValidasi()),
        );

        return $this->tanpaCache([
            'pesan' => 'Pernyataan survei berhasil diperbarui. Survei lama tetap memakai teks sebelumnya.',
        ]);
    }

    public function updateStatus(
        Request $request,
        PertanyaanSurveiPembelajaran $pertanyaanSurveiPembelajaran,
        PernyataanSurveiMobileService $service,
    ): JsonResponse {
        $data = $request->validate(['aktif' => ['required', 'boolean']]);
        $service->ubahStatus($pertanyaanSurveiPembelajaran, (bool) $data['aktif']);

        return $this->tanpaCache([
            'pesan' => $data['aktif']
                ? 'Pernyataan survei berhasil diaktifkan.'
                : 'Pernyataan survei berhasil dinonaktifkan.',
        ]);
    }

    private function aturanValidasi(): array
    {
        return [
            'pernyataan' => ['required', 'string', 'max:500'],
            'urutan' => ['required', 'integer', 'min:1', 'max:999'],
        ];
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
