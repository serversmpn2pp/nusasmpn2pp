<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Pegawai;
use App\Services\Mobile\LaporanPresensiPegawaiMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LaporanPresensiPegawaiController extends Controller
{
    public function index(Request $request, LaporanPresensiPegawaiMobileService $service): JsonResponse
    {
        $aturan = [
            'bulan' => ['nullable', 'date_format:Y-m'],
            'cari' => ['nullable', 'string', 'max:100'],
            'halaman' => ['nullable', 'integer', 'min:1'],
        ];
        if (! $request->user()->membatasiCakupanAbsensiPegawai()) {
            $aturan += [
                'jenis_pegawai' => ['nullable', 'string', 'max:100'],
                'pegawai_id' => ['nullable', 'integer', 'exists:pegawai,id'],
                'status_pegawai' => ['nullable', Rule::in(['semua', 'aktif', 'nonaktif'])],
            ];
        }
        $filter = $request->validate($aturan);

        return $this->tanpaCache(['data' => $service->daftar($request->user(), $filter)]);
    }

    public function show(
        Request $request,
        Pegawai $pegawai,
        LaporanPresensiPegawaiMobileService $service,
    ): JsonResponse {
        $data = $request->validate(['bulan' => ['nullable', 'date_format:Y-m']]);

        return $this->tanpaCache([
            'data' => $service->detail($request->user(), $pegawai, $data['bulan'] ?? now()->format('Y-m')),
        ]);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
