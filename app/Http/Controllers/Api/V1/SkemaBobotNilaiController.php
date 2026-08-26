<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SkemaBobotNilai;
use App\Services\Mobile\SkemaBobotNilaiMobileService;
use App\Services\Nilai\SkemaBobotNilaiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SkemaBobotNilaiController extends Controller
{
    public function index(Request $request, SkemaBobotNilaiMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', Rule::exists('tahun_pelajaran', 'id')],
            'semester' => ['nullable', Rule::in(['semua', 'ganjil', 'genap'])],
            'tingkat' => ['nullable', Rule::in(['semua', '7', '8', '9'])],
            'status' => ['nullable', Rule::in(['semua', 'aktif', 'nonaktif'])],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($request->user(), $filter)]);
    }

    public function store(Request $request, SkemaBobotNilaiService $service): JsonResponse
    {
        $skema = $service->tambah($request->validate($this->aturanValidasi()));

        return $this->tanpaCache([
            'pesan' => 'Skema bobot nilai berhasil ditambahkan.',
            'data' => ['id' => (int) $skema->id],
        ])->setStatusCode(201);
    }

    public function update(
        Request $request,
        SkemaBobotNilai $skemaBobotNilai,
        SkemaBobotNilaiService $service,
    ): JsonResponse {
        $service->ubah($skemaBobotNilai, $request->validate($this->aturanValidasi()));

        return $this->tanpaCache(['pesan' => 'Skema bobot nilai berhasil diperbarui.']);
    }

    public function destroy(
        SkemaBobotNilai $skemaBobotNilai,
        SkemaBobotNilaiService $service,
    ): JsonResponse {
        $service->nonaktifkan($skemaBobotNilai);

        return $this->tanpaCache(['pesan' => 'Skema bobot nilai berhasil dinonaktifkan.']);
    }

    private function aturanValidasi(): array
    {
        return [
            'tahun_pelajaran_id' => ['required', 'integer', Rule::exists('tahun_pelajaran', 'id')],
            'semester' => ['required', Rule::in(['ganjil', 'genap'])],
            'tingkat' => ['nullable', 'integer', Rule::in([7, 8, 9])],
            'bobot_formatif' => ['required', 'integer', 'min:0', 'max:100'],
            'bobot_sumatif' => ['required', 'integer', 'min:0', 'max:100'],
            'bobot_sts' => ['required', 'integer', 'min:0', 'max:100'],
            'bobot_sas_saj' => ['required', 'integer', 'min:0', 'max:100'],
            'aktif' => ['required', 'boolean'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
