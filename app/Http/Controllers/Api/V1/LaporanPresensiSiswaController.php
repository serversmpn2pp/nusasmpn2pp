<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AnggotaKelas;
use App\Services\Mobile\LaporanPresensiSiswaMobileService;
use App\Support\PenulisExcelLaporanAbsensi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LaporanPresensiSiswaController extends Controller
{
    public function index(Request $request, LaporanPresensiSiswaMobileService $service): JsonResponse
    {
        $opsi = $request->validate([
            'cari' => ['nullable', 'string', 'max:100'],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:10', 'max:50'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($request->user(), $request, $opsi)]);
    }

    public function show(
        Request $request,
        AnggotaKelas $anggotaKelas,
        LaporanPresensiSiswaMobileService $service,
    ): JsonResponse {
        return $this->tanpaCache(['data' => $service->detail($request->user(), $request, $anggotaKelas)]);
    }

    public function export(
        Request $request,
        LaporanPresensiSiswaMobileService $service,
        PenulisExcelLaporanAbsensi $penulis,
    ) {
        $laporan = $service->bangunLaporan($request);
        $lokasi = $penulis->buat($laporan);

        return response()->download($lokasi, $service->namaBerkas($laporan), [
            'Content-Type' => PenulisExcelLaporanAbsensi::MIME,
        ])->deleteFileAfterSend(true);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
