<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PenugasanGuruWaliSiswa;
use App\Services\Mobile\PenugasanGuruWaliMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PenugasanGuruWaliController extends Controller
{
    public function index(Request $request, PenugasanGuruWaliMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'kata_kunci' => ['nullable', 'string', 'max:120'],
            'guru_wali_pegawai_id' => ['nullable', 'integer', Rule::exists('pegawai', 'id')],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:30'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($request->user(), $filter)]);
    }

    public function store(Request $request, PenugasanGuruWaliMobileService $service): JsonResponse
    {
        $data = $request->validate([
            'guru_wali_pegawai_id' => ['required', 'integer', Rule::exists('pegawai', 'id')->where('aktif', true)],
            'siswa_ids' => ['required', 'array', 'min:1', 'max:200'],
            'siswa_ids.*' => ['required', 'integer', 'distinct', Rule::exists('siswa', 'id')->where('aktif', true)],
            'tanggal_mulai' => ['required', 'date'],
            'nomor_sk' => ['nullable', 'string', 'max:100'],
            'catatan' => ['nullable', 'string', 'max:5000'],
        ]);
        $hasil = $service->simpan($request->user(), $data);

        return $this->tanpaCache([
            'message' => $hasil['pesan'],
            'data' => [
                'baru' => $hasil['baru'],
                'dipindahkan' => $hasil['dipindahkan'],
                'tetap' => $hasil['tetap'],
            ],
        ], 201);
    }

    public function destroy(
        Request $request,
        PenugasanGuruWaliSiswa $penugasanGuruWali,
        PenugasanGuruWaliMobileService $service,
    ): JsonResponse {
        $penugasan = $service->akhiri($request->user(), $penugasanGuruWali);

        return $this->tanpaCache([
            'message' => 'Penugasan Guru Wali berhasil diakhiri.',
            'data' => $service->ringkas($penugasan),
        ]);
    }

    private function tanpaCache(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
