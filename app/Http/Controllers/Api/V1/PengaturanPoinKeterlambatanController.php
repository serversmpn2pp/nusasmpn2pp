<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TahunPelajaran;
use App\Services\Mobile\PengaturanPoinKeterlambatanMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PengaturanPoinKeterlambatanController extends Controller
{
    public function index(Request $request, PengaturanPoinKeterlambatanMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'cari' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['semua', 'aktif', 'nonaktif'])],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($filter)]);
    }

    public function update(
        Request $request,
        TahunPelajaran $tahunPelajaran,
        PengaturanPoinKeterlambatanMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'aktif' => ['required', 'boolean'],
            'rentang' => ['required', 'array', 'min:1', 'max:20'],
            'rentang.*.menit_mulai' => ['required', 'integer', 'min:1', 'max:1440'],
            'rentang.*.menit_selesai' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'rentang.*.poin' => ['required', 'integer', 'min:0', 'max:500'],
        ]);

        $service->simpan($tahunPelajaran, $data, $request->user()?->id);

        return $this->tanpaCache([
            'pesan' => 'Pengaturan poin keterlambatan tahun '.$tahunPelajaran->nama.' berhasil disimpan.',
        ]);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
