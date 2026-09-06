<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PesertaUjianCbt;
use App\Models\RuangUjianCbt;
use App\Services\Mobile\PresensiUjianMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PresensiUjianController extends Controller
{
    public function index(Request $request, PresensiUjianMobileService $service): JsonResponse
    {
        return $this->tanpaCache(['data' => $service->daftar($request->user())]);
    }

    public function show(
        Request $request,
        RuangUjianCbt $ruangUjianCbt,
        PresensiUjianMobileService $service,
    ): JsonResponse {
        return $this->tanpaCache(['data' => $service->detail($request->user(), $ruangUjianCbt)]);
    }

    public function scan(
        Request $request,
        RuangUjianCbt $ruangUjianCbt,
        PresensiUjianMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'isi_scan' => ['required', 'string', 'max:120'],
        ]);
        $hasil = $service->scan($request->user(), $ruangUjianCbt, $data['isi_scan']);

        return $this->tanpaCache(['data' => $hasil], $hasil['berhasil'] ? 200 : 422);
    }

    public function updateManual(
        Request $request,
        RuangUjianCbt $ruangUjianCbt,
        PesertaUjianCbt $pesertaUjianCbt,
        PresensiUjianMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(PesertaUjianCbt::DAFTAR_STATUS_KEHADIRAN))],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->tanpaCache([
            'pesan' => 'Presensi '.$pesertaUjianCbt->anggotaKelas?->siswa?->nama_lengkap.' berhasil diperbarui.',
            'data' => $service->ubahManual(
                $request->user(),
                $ruangUjianCbt,
                $pesertaUjianCbt,
                $data['status'],
                $data['catatan'] ?? null,
            ),
        ]);
    }

    private function tanpaCache(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
