<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PeringatanDiniSiswa;
use App\Services\Mobile\PeringatanDiniSiswaMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PeringatanDiniSiswaController extends Controller
{
    public function index(Request $request, PeringatanDiniSiswaMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'kata_kunci' => ['nullable', 'string', 'max:120'],
            'jenis' => ['nullable', Rule::in(['semua', ...array_keys(PeringatanDiniSiswa::DAFTAR_JENIS)])],
            'tingkat' => ['nullable', Rule::in(['semua', ...array_keys(PeringatanDiniSiswa::DAFTAR_TINGKAT)])],
            'status' => ['nullable', Rule::in(['semua', ...array_keys(PeringatanDiniSiswa::DAFTAR_STATUS)])],
            'tahun_pelajaran_id' => ['nullable', 'integer', Rule::exists('tahun_pelajaran', 'id')],
            'kelas_id' => ['nullable', 'integer', Rule::exists('kelas', 'id')],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:30'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($request->user(), $filter)]);
    }

    public function show(
        Request $request,
        PeringatanDiniSiswa $peringatanDiniSiswa,
        PeringatanDiniSiswaMobileService $service,
    ): JsonResponse {
        return $this->tanpaCache([
            'data' => $service->rincian($request->user(), $peringatanDiniSiswa),
        ]);
    }

    public function proses(Request $request, PeringatanDiniSiswaMobileService $service): JsonResponse
    {
        $data = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', Rule::exists('tahun_pelajaran', 'id')],
        ]);
        $hasil = $service->proses(
            $request->user(),
            isset($data['tahun_pelajaran_id']) ? (int) $data['tahun_pelajaran_id'] : null,
        );

        return $this->tanpaCache([
            'message' => sprintf(
                'Deteksi selesai: %d baru, %d diperbarui, dan %d diselesaikan.',
                $hasil['peringatan_baru'],
                $hasil['peringatan_diperbarui'],
                $hasil['peringatan_diselesaikan'],
            ),
            'data' => $hasil,
        ]);
    }

    private function tanpaCache(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
