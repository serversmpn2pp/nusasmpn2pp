<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\JenisPerangkatAjar;
use App\Services\Mobile\JenisPerangkatAjarMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JenisPerangkatAjarController extends Controller
{
    public function index(Request $request, JenisPerangkatAjarMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'cari' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['semua', 'aktif', 'nonaktif'])],
            'kewajiban' => ['nullable', Rule::in(['semua', 'wajib', 'opsional'])],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($filter)]);
    }

    public function store(Request $request, JenisPerangkatAjarMobileService $service): JsonResponse
    {
        $request->merge(['kode' => $service->rapikanKode($request->input('kode'))]);
        $jenis = $service->tambah($request->validate($this->aturanValidasi()));
        $jenis->loadCount('perangkatAjar');

        return $this->tanpaCache([
            'pesan' => 'Jenis perangkat ajar berhasil ditambahkan.',
            'data' => $service->ringkas($jenis),
        ])->setStatusCode(201);
    }

    public function update(
        Request $request,
        JenisPerangkatAjar $jenisPerangkatAjar,
        JenisPerangkatAjarMobileService $service,
    ): JsonResponse {
        $request->merge(['kode' => $service->rapikanKode($request->input('kode'))]);
        $service->ubah(
            $jenisPerangkatAjar,
            $request->validate($this->aturanValidasi($jenisPerangkatAjar)),
        );

        return $this->tanpaCache(['pesan' => 'Jenis perangkat ajar berhasil diperbarui.']);
    }

    public function destroy(
        JenisPerangkatAjar $jenisPerangkatAjar,
        JenisPerangkatAjarMobileService $service,
    ): JsonResponse {
        $service->nonaktifkan($jenisPerangkatAjar);

        return $this->tanpaCache([
            'pesan' => 'Jenis perangkat ajar berhasil dinonaktifkan. Dokumen lama tetap tersimpan.',
        ]);
    }

    private function aturanValidasi(?JenisPerangkatAjar $jenis = null): array
    {
        return [
            'nama' => [
                'required',
                'string',
                'max:120',
                Rule::unique('jenis_perangkat_ajar', 'nama')->ignore($jenis),
            ],
            'kode' => [
                'required',
                'string',
                'max:40',
                Rule::unique('jenis_perangkat_ajar', 'kode')->ignore($jenis),
            ],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'wajib' => ['required', 'boolean'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:999'],
            'aktif' => ['required', 'boolean'],
        ];
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
