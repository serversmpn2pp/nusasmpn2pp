<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\SatuanBarang;
use App\Services\Mobile\SatuanBarangMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SatuanBarangController extends Controller
{
    public function index(Request $request, SatuanBarangMobileService $service): JsonResponse
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

    public function store(Request $request, SatuanBarangMobileService $service): JsonResponse
    {
        $request->merge(['kode' => $service->rapikanKode($request->input('kode'))]);
        $satuan = $service->tambah($request->validate($this->aturanValidasi()));
        $satuan->loadCount('barang');

        return $this->tanpaCache([
            'pesan' => 'Satuan barang berhasil ditambahkan.',
            'data' => $service->ringkas($satuan),
        ])->setStatusCode(201);
    }

    public function update(
        Request $request,
        SatuanBarang $satuanBarang,
        SatuanBarangMobileService $service,
    ): JsonResponse {
        $request->merge(['kode' => $service->rapikanKode($request->input('kode'))]);
        $service->ubah(
            $satuanBarang,
            $request->validate($this->aturanValidasi($satuanBarang)),
        );

        return $this->tanpaCache(['pesan' => 'Satuan barang berhasil diperbarui.']);
    }

    public function destroy(
        SatuanBarang $satuanBarang,
        SatuanBarangMobileService $service,
    ): JsonResponse {
        $service->nonaktifkan($satuanBarang);

        return $this->tanpaCache([
            'pesan' => 'Satuan barang berhasil dinonaktifkan. Riwayat barang tetap tersimpan.',
        ]);
    }

    private function aturanValidasi(?SatuanBarang $satuan = null): array
    {
        return [
            'nama' => [
                'required',
                'string',
                'max:80',
                Rule::unique('satuan_barang', 'nama')->ignore($satuan),
            ],
            'kode' => [
                'required',
                'string',
                'max:30',
                'regex:/^[A-Z0-9_]+$/',
                Rule::unique('satuan_barang', 'kode')->ignore($satuan),
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
