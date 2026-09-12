<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PeriodeBerhalanganIbadah;
use App\Services\Mobile\RekapBerhalanganIbadahMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RekapBerhalanganIbadahController extends Controller
{
    public function index(Request $request, RekapBerhalanganIbadahMobileService $service): JsonResponse
    {
        return $this->tanpaCache([
            'data' => $service->tampilkan($request->user(), $this->validasi($request)),
        ]);
    }

    public function export(Request $request, RekapBerhalanganIbadahMobileService $service): JsonResponse
    {
        return $this->tanpaCache([
            'data' => $service->tampilkan($request->user(), $this->validasi($request), true),
        ]);
    }

    private function validasi(Request $request): array
    {
        return $request->validate([
            'bulan' => ['nullable', 'date_format:Y-m'],
            'kelas_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in([
                'semua',
                PeriodeBerhalanganIbadah::STATUS_AKTIF,
                PeriodeBerhalanganIbadah::STATUS_PERLU_KONFIRMASI,
                PeriodeBerhalanganIbadah::STATUS_SELESAI,
            ])],
            'cari' => ['nullable', 'string', 'max:100'],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:1', 'max:30'],
        ]);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
