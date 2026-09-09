<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PenugasanGuruBkTingkat;
use App\Services\Mobile\PenugasanGuruBkTingkatMobileService;
use App\Services\Pembinaan\PenugasanGuruBkTingkatService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PenugasanGuruBkTingkatController extends Controller
{
    public function index(Request $request, PenugasanGuruBkTingkatMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', Rule::exists('tahun_pelajaran', 'id')],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($request->user(), $filter)]);
    }

    public function store(Request $request, PenugasanGuruBkTingkatMobileService $service): JsonResponse
    {
        $data = $request->validate([
            'tahun_pelajaran_id' => ['required', 'integer', Rule::exists('tahun_pelajaran', 'id')],
            'pegawai_id' => ['required', 'integer', Rule::exists('pegawai', 'id')->where('aktif', true)],
            'tingkat' => ['required', 'array', 'min:1', 'max:3'],
            'tingkat.*' => [
                'required',
                'integer',
                'distinct',
                Rule::in(array_keys(PenugasanGuruBkTingkatService::DAFTAR_TINGKAT)),
            ],
        ]);
        $hasil = $service->simpan($request->user(), $data);

        return $this->tanpaCache([
            'message' => $hasil['jumlah'].' penugasan Guru BK berhasil disimpan.',
            'data' => $hasil,
        ], 201);
    }

    public function destroy(
        PenugasanGuruBkTingkat $penugasanGuruBkTingkat,
        PenugasanGuruBkTingkatMobileService $service,
    ): JsonResponse {
        return $this->tanpaCache([
            'message' => 'Penugasan Guru BK berhasil diakhiri.',
            'data' => $service->akhiri($penugasanGuruBkTingkat),
        ]);
    }

    private function tanpaCache(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
