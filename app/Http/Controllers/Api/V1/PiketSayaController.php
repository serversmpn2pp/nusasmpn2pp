<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AnggotaKelas;
use App\Services\Mobile\PiketSayaMobileService;
use App\Services\Piket\CatatKehadiranSiswaPiketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PiketSayaController extends Controller
{
    public function index(Request $request, PiketSayaMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'status' => ['nullable', Rule::in(['semua', 'belum_scan', 'hadir', 'sakit', 'izin', 'alfa'])],
            'cari' => ['nullable', 'string', 'max:100'],
            'halaman' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json(['data' => $service->halaman($request->user(), $filter)])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }

    public function update(
        Request $request,
        AnggotaKelas $anggotaKelas,
        CatatKehadiranSiswaPiketService $service,
    ): JsonResponse {
        $data = $request->validate([
            'status_kehadiran' => ['required', Rule::in(['sakit', 'izin'])],
            'catatan' => ['required', 'string', 'min:3', 'max:500'],
        ]);
        $absensi = $service->catat(
            $request->user(),
            $anggotaKelas,
            $data['status_kehadiran'],
            $data['catatan'],
        );

        return response()->json([
            'pesan' => 'Kehadiran siswa berhasil dicatat oleh guru piket.',
            'data' => ['id' => (int) $absensi->id, 'status' => $absensi->status_kehadiran],
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
