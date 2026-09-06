<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\LokasiBarang;
use App\Services\Mobile\LokasiBarangMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LokasiBarangController extends Controller
{
    public function index(Request $request, LokasiBarangMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'cari' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['semua', 'aktif', 'nonaktif'])],
            'jenis' => ['nullable', Rule::in(['semua', ...array_keys(LokasiBarang::DAFTAR_JENIS)])],
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

    public function store(Request $request, LokasiBarangMobileService $service): JsonResponse
    {
        $request->merge(['kode' => $service->rapikanKode($request->input('kode'))]);
        $lokasi = $service->tambah($request->validate($this->aturanValidasi()));
        $lokasi->load('penanggungJawab')->loadCount('barangSebagaiPenyimpanan');

        return $this->tanpaCache([
            'pesan' => 'Lokasi barang berhasil ditambahkan.',
            'data' => $service->ringkas($lokasi),
        ])->setStatusCode(201);
    }

    public function update(
        Request $request,
        LokasiBarang $lokasiBarang,
        LokasiBarangMobileService $service,
    ): JsonResponse {
        $request->merge(['kode' => $service->rapikanKode($request->input('kode'))]);
        $service->ubah(
            $lokasiBarang,
            $request->validate($this->aturanValidasi($lokasiBarang)),
        );

        return $this->tanpaCache(['pesan' => 'Lokasi barang berhasil diperbarui.']);
    }

    public function destroy(
        LokasiBarang $lokasiBarang,
        LokasiBarangMobileService $service,
    ): JsonResponse {
        $service->nonaktifkan($lokasiBarang);

        return $this->tanpaCache([
            'pesan' => 'Lokasi barang berhasil dinonaktifkan. Riwayat inventaris tetap tersimpan.',
        ]);
    }

    private function aturanValidasi(?LokasiBarang $lokasi = null): array
    {
        return [
            'nama' => [
                'required',
                'string',
                'max:120',
                Rule::unique('lokasi_barang', 'nama')->ignore($lokasi),
            ],
            'kode' => [
                'required',
                'string',
                'max:40',
                'regex:/^[A-Z0-9_]+$/',
                Rule::unique('lokasi_barang', 'kode')->ignore($lokasi),
            ],
            'jenis' => ['required', Rule::in(array_keys(LokasiBarang::DAFTAR_JENIS))],
            'penanggung_jawab_pegawai_id' => ['nullable', 'integer', 'exists:pegawai,id'],
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
