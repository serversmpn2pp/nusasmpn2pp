<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\KegiatanIbadah;
use App\Services\Mobile\KegiatanIbadahMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KegiatanIbadahController extends Controller
{
    public function index(Request $request, KegiatanIbadahMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'cari' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['semua', 'aktif', 'nonaktif'])],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($filter)]);
    }

    public function store(Request $request, KegiatanIbadahMobileService $service): JsonResponse
    {
        $request->merge(['kode' => $service->rapikanKode($request->input('kode'))]);
        $kegiatan = $service->tambah($request->validate($this->aturanValidasi()));
        $kegiatan->loadCount([
            'jadwal',
            'jadwal as jumlah_jadwal_aktif' => fn ($query) => $query->where('aktif', true),
        ]);

        return $this->tanpaCache([
            'pesan' => 'Kegiatan ibadah berhasil ditambahkan.',
            'data' => $service->ringkas($kegiatan),
        ])->setStatusCode(201);
    }

    public function update(
        Request $request,
        KegiatanIbadah $kegiatanIbadah,
        KegiatanIbadahMobileService $service,
    ): JsonResponse {
        $request->merge(['kode' => $service->rapikanKode($request->input('kode'))]);
        $service->ubah(
            $kegiatanIbadah,
            $request->validate($this->aturanValidasi($kegiatanIbadah)),
        );

        return $this->tanpaCache(['pesan' => 'Kegiatan ibadah berhasil diperbarui.']);
    }

    public function destroy(
        KegiatanIbadah $kegiatanIbadah,
        KegiatanIbadahMobileService $service,
    ): JsonResponse {
        $service->nonaktifkan($kegiatanIbadah);

        return $this->tanpaCache([
            'pesan' => 'Kegiatan dan seluruh jadwalnya berhasil dinonaktifkan.',
        ]);
    }

    private function aturanValidasi(?KegiatanIbadah $kegiatan = null): array
    {
        return [
            'kode' => [
                'required',
                'string',
                'max:50',
                'regex:/^[a-z0-9_]+$/',
                Rule::unique('kegiatan_ibadah', 'kode')->ignore($kegiatan),
            ],
            'nama' => ['required', 'string', 'max:150'],
            'aktif' => ['required', 'boolean'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
