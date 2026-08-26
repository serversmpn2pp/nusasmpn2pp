<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\KomponenNilai;
use App\Services\Mobile\KomponenNilaiMobileService;
use App\Services\Nilai\KomponenNilaiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KomponenNilaiController extends Controller
{
    public function index(Request $request, KomponenNilaiMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'cari' => ['nullable', 'string', 'max:100'],
            'tahun_pelajaran_id' => ['nullable', 'integer', Rule::exists('tahun_pelajaran', 'id')],
            'semester' => ['nullable', Rule::in(['semua', 'ganjil', 'genap'])],
            'jenis_komponen' => ['nullable', Rule::in(['semua', 'formatif', 'sumatif', 'sts', 'sas_saj'])],
            'status' => ['nullable', Rule::in(['semua', 'aktif', 'nonaktif'])],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($request->user(), $filter)]);
    }

    public function store(Request $request, KomponenNilaiService $service): JsonResponse
    {
        $komponen = $service->tambah(
            $request->user(),
            $request->validate($this->aturanValidasi()),
        );

        return $this->tanpaCache([
            'pesan' => 'Komponen nilai berhasil ditambahkan.',
            'data' => ['id' => (int) $komponen->id],
        ])->setStatusCode(201);
    }

    public function update(
        Request $request,
        KomponenNilai $komponenNilai,
        KomponenNilaiService $service,
    ): JsonResponse {
        $service->ubah(
            $request->user(),
            $komponenNilai,
            $request->validate($this->aturanValidasi()),
        );

        return $this->tanpaCache(['pesan' => 'Komponen nilai berhasil diperbarui.']);
    }

    public function destroy(
        Request $request,
        KomponenNilai $komponenNilai,
        KomponenNilaiService $service,
    ): JsonResponse {
        $service->nonaktifkan($request->user(), $komponenNilai);

        return $this->tanpaCache(['pesan' => 'Komponen nilai berhasil dinonaktifkan.']);
    }

    private function aturanValidasi(): array
    {
        return [
            'guru_mata_pelajaran_id' => ['required', 'integer', Rule::exists('guru_mata_pelajaran', 'id')],
            'semester' => ['required', Rule::in(['ganjil', 'genap'])],
            'jenis_komponen' => ['required', Rule::in(['formatif', 'sumatif', 'sts', 'sas_saj'])],
            'nama' => ['required', 'string', 'max:255'],
            'tanggal_penilaian' => ['nullable', 'date'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:999'],
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
