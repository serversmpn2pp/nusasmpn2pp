<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\KategoriPembinaanSiswa;
use App\Services\Mobile\KategoriPembinaanSiswaMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KategoriPembinaanSiswaController extends Controller
{
    public function index(Request $request, KategoriPembinaanSiswaMobileService $service): JsonResponse
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
                $request->user()->memilikiIzin('bk.kelola'),
            ),
        ]);
    }

    public function store(Request $request, KategoriPembinaanSiswaMobileService $service): JsonResponse
    {
        $request->merge(['kode' => $service->rapikanKode($request->input('kode'))]);
        $kategori = $service->tambah($request->validate($this->aturanValidasi()));
        $kategori->loadCount(['laporanPembinaanSiswa', 'jenisPelanggaranSiswa']);

        return $this->tanpaCache([
            'pesan' => 'Kategori pembinaan siswa berhasil ditambahkan.',
            'data' => $service->ringkas($kategori),
        ])->setStatusCode(201);
    }

    public function update(
        Request $request,
        KategoriPembinaanSiswa $kategoriPembinaanSiswa,
        KategoriPembinaanSiswaMobileService $service,
    ): JsonResponse {
        $request->merge(['kode' => $service->rapikanKode($request->input('kode'))]);
        $service->ubah(
            $kategoriPembinaanSiswa,
            $request->validate($this->aturanValidasi($kategoriPembinaanSiswa)),
        );

        return $this->tanpaCache(['pesan' => 'Kategori pembinaan siswa berhasil diperbarui.']);
    }

    public function destroy(
        KategoriPembinaanSiswa $kategoriPembinaanSiswa,
        KategoriPembinaanSiswaMobileService $service,
    ): JsonResponse {
        $service->nonaktifkan($kategoriPembinaanSiswa);

        return $this->tanpaCache([
            'pesan' => 'Kategori pembinaan siswa berhasil dinonaktifkan.',
        ]);
    }

    private function aturanValidasi(?KategoriPembinaanSiswa $kategori = null): array
    {
        return [
            'nama' => [
                'required',
                'string',
                'max:120',
                Rule::unique('kategori_pembinaan_siswa', 'nama')->ignore($kategori),
            ],
            'kode' => [
                'required',
                'string',
                'max:40',
                'regex:/^[A-Z0-9_]+$/',
                Rule::unique('kategori_pembinaan_siswa', 'kode')->ignore($kategori),
            ],
            'deskripsi' => ['nullable', 'string'],
            'aktif' => ['required', 'boolean'],
        ];
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
