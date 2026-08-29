<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AbsensiPegawai;
use App\Models\Pegawai;
use App\Services\Mobile\RekapPresensiPegawaiMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RekapPresensiPegawaiController extends Controller
{
    public function index(Request $request, RekapPresensiPegawaiMobileService $service): JsonResponse
    {
        $aturan = [
            'tanggal' => ['nullable', 'date'],
            'cari' => ['nullable', 'string', 'max:100'],
            'halaman' => ['nullable', 'integer', 'min:1'],
        ];
        if (! $request->user()->membatasiCakupanAbsensiPegawai()) {
            $aturan += [
                'jenis_pegawai' => ['nullable', 'string', 'max:100'],
                'pegawai_id' => ['nullable', 'integer', 'exists:pegawai,id'],
                'status_pegawai' => ['nullable', Rule::in(['semua', 'aktif', 'nonaktif'])],
                'status' => ['nullable', Rule::in([
                    'semua', ...array_keys(AbsensiPegawai::DAFTAR_STATUS_KEHADIRAN),
                    'terlambat', 'pulang_cepat', 'belum_pulang',
                ])],
            ];
        }
        $filter = $request->validate($aturan);

        return $this->tanpaCache(['data' => $service->daftar($request->user(), $filter)]);
    }

    public function show(
        Request $request,
        Pegawai $pegawai,
        RekapPresensiPegawaiMobileService $service,
    ): JsonResponse {
        $data = $request->validate(['tanggal' => ['required', 'date']]);

        return $this->tanpaCache(['data' => $service->detail($request->user(), $pegawai, $data['tanggal'])]);
    }

    public function update(
        Request $request,
        Pegawai $pegawai,
        RekapPresensiPegawaiMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'tanggal' => ['required', 'date'],
            'status_kehadiran' => ['required', Rule::in(array_keys(AbsensiPegawai::DAFTAR_STATUS_KEHADIRAN))],
            'jam_masuk' => ['nullable', 'date_format:H:i'],
            'jam_pulang' => ['nullable', 'date_format:H:i'],
            'catatan' => ['nullable', 'string', 'max:2000'],
        ]);
        $absensi = $service->koreksi($request->user(), $pegawai, $data);

        return $this->tanpaCache([
            'pesan' => 'Koreksi presensi pegawai berhasil disimpan.',
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
