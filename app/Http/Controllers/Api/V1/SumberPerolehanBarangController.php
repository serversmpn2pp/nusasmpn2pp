<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SumberPerolehanBarang;
use App\Services\Mobile\SumberPerolehanBarangMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SumberPerolehanBarangController extends Controller
{
    public function index(Request $request, SumberPerolehanBarangMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'cari' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['semua', 'aktif', 'nonaktif'])],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return $this->tanpaCache([
            'data' => $service->daftar(
                $filter,
                $request->user()->memilikiIzin('barang.kelola'),
            ),
        ]);
    }

    public function store(Request $request, SumberPerolehanBarangMobileService $service): JsonResponse
    {
        $request->merge(['kode' => $service->rapikanKode($request->input('kode'))]);
        $sumber = $service->tambah($request->validate($this->aturanValidasi()));
        $sumber->loadCount('unitBarang');

        return $this->tanpaCache([
            'pesan' => 'Sumber perolehan berhasil ditambahkan.',
            'data' => $service->ringkas($sumber),
        ])->setStatusCode(201);
    }

    public function update(
        Request $request,
        SumberPerolehanBarang $sumberPerolehanBarang,
        SumberPerolehanBarangMobileService $service,
    ): JsonResponse {
        $request->merge(['kode' => $service->rapikanKode($request->input('kode'))]);
        $service->ubah(
            $sumberPerolehanBarang,
            $request->validate($this->aturanValidasi($sumberPerolehanBarang)),
        );

        return $this->tanpaCache(['pesan' => 'Sumber perolehan berhasil diperbarui.']);
    }

    public function destroy(
        SumberPerolehanBarang $sumberPerolehanBarang,
        SumberPerolehanBarangMobileService $service,
    ): JsonResponse {
        $service->nonaktifkan($sumberPerolehanBarang);

        return $this->tanpaCache([
            'pesan' => 'Sumber perolehan berhasil dinonaktifkan. Riwayat aset tetap tersimpan.',
        ]);
    }

    private function aturanValidasi(?SumberPerolehanBarang $sumber = null): array
    {
        return [
            'nama' => [
                'required',
                'string',
                'max:120',
                Rule::unique('sumber_perolehan_barang', 'nama')->ignore($sumber),
            ],
            'kode' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9_]+$/',
                Rule::unique('sumber_perolehan_barang', 'kode')->ignore($sumber),
            ],
            'deskripsi' => ['nullable', 'string', 'max:2000'],
            'aktif' => ['required', 'boolean'],
        ];
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
