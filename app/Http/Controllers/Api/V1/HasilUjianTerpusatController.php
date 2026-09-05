<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\JadwalUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Services\Mobile\HasilUjianTerpusatMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class HasilUjianTerpusatController extends Controller
{
    public function index(Request $request, HasilUjianTerpusatMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'kata_kunci' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['semua', ...array_keys(KegiatanUjianCbt::DAFTAR_STATUS)])],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:30'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($request->user(), $filter)]);
    }

    public function show(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        HasilUjianTerpusatMobileService $service,
    ): JsonResponse {
        $filter = $request->validate([
            'jadwal_id' => ['nullable', 'integer', 'exists:jadwal_ujian_cbt,id'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'status' => ['nullable', Rule::in([
                'semua', 'tuntas', 'belum_tuntas', 'perlu_koreksi_otomatis',
                'perlu_koreksi_manual', 'belum_selesai',
            ])],
        ]);

        return $this->tanpaCache(['data' => $service->rincian($request->user(), $kegiatanUjianCbt, $filter)]);
    }

    public function terapkanNilai(
        Request $request,
        KegiatanUjianCbt $kegiatanUjianCbt,
        JadwalUjianCbt $jadwalUjianCbt,
        HasilUjianTerpusatMobileService $service,
    ): JsonResponse {
        $hasil = $service->terapkanNilai($request->user(), $kegiatanUjianCbt, $jadwalUjianCbt);

        return $this->tanpaCache(['pesan' => $hasil['pesan'], 'data' => $hasil]);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
