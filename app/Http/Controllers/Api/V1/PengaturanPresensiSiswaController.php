<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PengaturanAbsensi;
use App\Services\Mobile\PengaturanPresensiSiswaMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PengaturanPresensiSiswaController extends Controller
{
    public function index(Request $request, PengaturanPresensiSiswaMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'hari' => ['nullable', Rule::in(['semua', ...array_keys(PengaturanAbsensi::DAFTAR_HARI)])],
            'status' => ['nullable', Rule::in(['semua', 'aktif', 'nonaktif'])],
        ]);

        return $this->tanpaCache([
            'data' => $service->daftar($request->user(), $filter),
        ]);
    }

    public function store(Request $request, PengaturanPresensiSiswaMobileService $service): JsonResponse
    {
        $pengaturan = $service->tambah($request->validate($this->aturanValidasi()));

        return $this->tanpaCache([
            'pesan' => 'Pengaturan presensi siswa berhasil ditambahkan.',
            'data' => ['id' => (int) $pengaturan->id],
        ])->setStatusCode(201);
    }

    public function update(
        Request $request,
        PengaturanAbsensi $pengaturanAbsensi,
        PengaturanPresensiSiswaMobileService $service,
    ): JsonResponse {
        $service->ubah(
            $pengaturanAbsensi,
            $request->validate($this->aturanValidasi($pengaturanAbsensi)),
        );

        return $this->tanpaCache([
            'pesan' => 'Pengaturan presensi siswa berhasil diperbarui.',
        ]);
    }

    private function aturanValidasi(?PengaturanAbsensi $pengaturanAbsensi = null): array
    {
        return [
            'hari' => [
                'required',
                Rule::in(array_keys(PengaturanAbsensi::DAFTAR_HARI)),
                Rule::unique('pengaturan_absensi', 'hari')->ignore($pengaturanAbsensi),
            ],
            'jam_scan_masuk_mulai' => ['required', 'date_format:H:i'],
            'jam_masuk' => ['required', 'date_format:H:i'],
            'jam_scan_masuk_selesai' => ['required', 'date_format:H:i'],
            'jam_scan_pulang_mulai' => ['required', 'date_format:H:i'],
            'jam_pulang' => ['required', 'date_format:H:i'],
            'jam_scan_pulang_selesai' => ['required', 'date_format:H:i'],
            'pulang_jumat_dibedakan' => ['nullable', 'boolean'],
            'jam_scan_pulang_perempuan_mulai' => ['nullable', 'date_format:H:i'],
            'jam_pulang_perempuan' => ['nullable', 'date_format:H:i'],
            'jam_scan_pulang_perempuan_selesai' => ['nullable', 'date_format:H:i'],
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
