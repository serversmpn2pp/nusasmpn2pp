<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\KategoriBarang;
use App\Services\Mobile\KategoriBarangMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KategoriBarangController extends Controller
{
    public function index(Request $request, KategoriBarangMobileService $service): JsonResponse
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

    public function store(Request $request, KategoriBarangMobileService $service): JsonResponse
    {
        $request->merge(['kode' => $service->rapikanKode($request->input('kode'))]);
        $kategori = $service->tambah($request->validate($this->aturanValidasi()));
        $kategori->loadCount('barang');

        return $this->tanpaCache([
            'pesan' => 'Kategori barang berhasil ditambahkan.',
            'data' => $service->ringkas($kategori),
        ])->setStatusCode(201);
    }

    public function update(
        Request $request,
        KategoriBarang $kategoriBarang,
        KategoriBarangMobileService $service,
    ): JsonResponse {
        $request->merge(['kode' => $service->rapikanKode($request->input('kode'))]);
        $service->ubah(
            $kategoriBarang,
            $request->validate($this->aturanValidasi($kategoriBarang)),
        );

        return $this->tanpaCache(['pesan' => 'Kategori barang berhasil diperbarui.']);
    }

    public function destroy(
        KategoriBarang $kategoriBarang,
        KategoriBarangMobileService $service,
    ): JsonResponse {
        $service->nonaktifkan($kategoriBarang);

        return $this->tanpaCache([
            'pesan' => 'Kategori barang berhasil dinonaktifkan. Riwayat barang tetap tersimpan.',
        ]);
    }

    private function aturanValidasi(?KategoriBarang $kategori = null): array
    {
        return [
            'nama' => [
                'required',
                'string',
                'max:120',
                Rule::unique('kategori_barang', 'nama')->ignore($kategori),
            ],
            'kode' => [
                'required',
                'string',
                'max:40',
                'regex:/^[A-Z0-9_]+$/',
                Rule::unique('kategori_barang', 'kode')->ignore($kategori),
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
