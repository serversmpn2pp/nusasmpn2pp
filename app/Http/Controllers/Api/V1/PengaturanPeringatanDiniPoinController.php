<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TahunPelajaran;
use App\Services\Mobile\PengaturanPeringatanDiniPoinMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PengaturanPeringatanDiniPoinController extends Controller
{
    public function index(Request $request, PengaturanPeringatanDiniPoinMobileService $service): JsonResponse
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
        PengaturanPeringatanDiniPoinMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'aktif' => ['required', 'boolean'],
            'notifikasi_aktif' => ['required', 'boolean'],
            'persentase_mendekati_ambang' => ['required', 'integer', 'min:50', 'max:99'],
            'jumlah_pelanggaran_berulang' => ['required', 'integer', 'min:2', 'max:20'],
            'periode_pelanggaran_hari' => ['required', 'integer', 'min:7', 'max:365'],
            'jumlah_keterlambatan_berulang' => ['required', 'integer', 'min:2', 'max:30'],
            'periode_keterlambatan_hari' => ['required', 'integer', 'min:7', 'max:365'],
        ]);

        $service->simpan($tahunPelajaran, $data, $request->user()?->id);

        return $this->tanpaCache([
            'pesan' => 'Pengaturan peringatan dini tahun '.$tahunPelajaran->nama.' berhasil disimpan.',
        ]);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
