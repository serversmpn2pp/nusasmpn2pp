<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PesertaUjianCbt;
use App\Models\UjianCbt;
use App\Services\Mobile\MonitoringHasilAsesmenKelasMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MonitoringHasilAsesmenKelasController extends Controller
{
    public function monitoring(
        Request $request,
        UjianCbt $ujianCbt,
        MonitoringHasilAsesmenKelasMobileService $service,
    ): JsonResponse {
        $filter = $request->validate([
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'status' => ['nullable', Rule::in(['semua', ...array_keys(PesertaUjianCbt::DAFTAR_STATUS_PELAKSANAAN)])],
        ]);

        return $this->tanpaCache([
            'data' => $service->monitoring($request->user(), $ujianCbt, $filter),
        ]);
    }

    public function hasil(
        Request $request,
        UjianCbt $ujianCbt,
        MonitoringHasilAsesmenKelasMobileService $service,
    ): JsonResponse {
        $filter = $request->validate([
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'status' => ['nullable', Rule::in([
                'semua',
                'tuntas',
                'belum_tuntas',
                'perlu_koreksi_otomatis',
                'perlu_koreksi_manual',
                'belum_selesai',
            ])],
        ]);

        return $this->tanpaCache([
            'data' => $service->hasil($request->user(), $ujianCbt, $filter),
        ]);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
