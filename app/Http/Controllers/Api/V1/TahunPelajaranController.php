<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\TahunPelajaran;
use App\Services\Mobile\TahunPelajaranMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TahunPelajaranController extends Controller
{
    public function index(Request $request, TahunPelajaranMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'cari' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['semua', 'aktif', 'nonaktif'])],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($request->user(), $filter)]);
    }

    public function store(Request $request, TahunPelajaranMobileService $service): JsonResponse
    {
        $tahunPelajaran = $service->tambah($request->validate($this->aturanValidasi()));

        return $this->tanpaCache([
            'pesan' => 'Tahun pelajaran berhasil ditambahkan.',
            'data' => ['id' => (int) $tahunPelajaran->id],
        ])->setStatusCode(201);
    }

    public function update(
        Request $request,
        TahunPelajaran $tahunPelajaran,
        TahunPelajaranMobileService $service,
    ): JsonResponse {
        $service->ubah(
            $tahunPelajaran,
            $request->validate($this->aturanValidasi($tahunPelajaran)),
        );

        return $this->tanpaCache(['pesan' => 'Tahun pelajaran berhasil diperbarui.']);
    }

    private function aturanValidasi(?TahunPelajaran $tahunPelajaran = null): array
    {
        return [
            'nama' => [
                'required',
                'string',
                'max:50',
                Rule::unique('tahun_pelajaran', 'nama')->ignore($tahunPelajaran),
            ],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
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
