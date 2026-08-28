<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AnggotaKelas;
use App\Services\Absensi\KoreksiPresensiSiswaService;
use App\Services\Mobile\RekapPresensiSiswaMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RekapPresensiSiswaController extends Controller
{
    public function index(Request $request, RekapPresensiSiswaMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'tanggal' => ['nullable', 'date'],
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'status' => ['nullable', Rule::in([
                'semua', 'hadir', 'izin', 'sakit', 'alfa', 'belum_scan', 'terlambat', 'pulang_cepat', 'belum_pulang',
            ])],
            'cari' => ['nullable', 'string', 'max:100'],
            'halaman' => ['nullable', 'integer', 'min:1'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($request->user(), $filter)]);
    }

    public function show(
        Request $request,
        AnggotaKelas $anggotaKelas,
        RekapPresensiSiswaMobileService $service,
    ): JsonResponse {
        $data = $request->validate(['tanggal' => ['required', 'date']]);

        return $this->tanpaCache(['data' => $service->detail($request->user(), $anggotaKelas, $data['tanggal'])]);
    }

    public function update(
        Request $request,
        AnggotaKelas $anggotaKelas,
        KoreksiPresensiSiswaService $service,
    ): JsonResponse {
        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'status_kehadiran' => ['required', Rule::in(['hadir', 'izin', 'sakit', 'alfa'])],
            'jam_masuk' => ['nullable', 'date_format:H:i'],
            'jam_pulang' => ['nullable', 'date_format:H:i'],
            'catatan' => ['required', 'string', 'min:3', 'max:2000'],
        ]);
        $absensi = $service->koreksi($request->user(), $anggotaKelas, $data);

        return $this->tanpaCache([
            'pesan' => 'Koreksi presensi berhasil disimpan.',
            'data' => [
                'id' => (int) $absensi->id,
                'status' => $absensi->status_kehadiran,
                'menit_terlambat' => (int) $absensi->menit_terlambat,
                'menit_pulang_cepat' => (int) $absensi->menit_pulang_cepat,
            ],
        ]);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
