<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\JadwalKegiatanIbadah;
use App\Services\Ibadah\ProsesScanKegiatanIbadah;
use App\Services\Mobile\ScanKegiatanIbadahMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScanKegiatanIbadahController extends Controller
{
    public function index(Request $request, ScanKegiatanIbadahMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'jadwal_id' => ['nullable', 'integer', 'exists:jadwal_kegiatan_ibadah,id'],
        ]);

        return $this->tanpaCache([
            'data' => $service->dashboard($request->user(), $filter['jadwal_id'] ?? null),
        ]);
    }

    public function store(
        Request $request,
        ProsesScanKegiatanIbadah $prosesScan,
        ScanKegiatanIbadahMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'jadwal_kegiatan_ibadah_id' => ['required', 'integer', 'exists:jadwal_kegiatan_ibadah,id'],
            'isi_scan' => ['required', 'string', 'max:100'],
        ]);
        $jadwal = JadwalKegiatanIbadah::query()
            ->with('kegiatanIbadah')
            ->findOrFail($data['jadwal_kegiatan_ibadah_id']);
        $waktu = now();
        $hasil = $prosesScan->proses(
            jadwal: $jadwal,
            isiScan: $data['isi_scan'],
            petugas: $request->user(),
            waktuScan: $waktu,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );
        $hasilData = $service->dataHasil($hasil, $jadwal, $waktu);

        return $this->tanpaCache([
            'message' => $hasil['pesan'],
            'data' => $hasilData,
        ])->setStatusCode($hasil['berhasil'] ? 200 : 422);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
