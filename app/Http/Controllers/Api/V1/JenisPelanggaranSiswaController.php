<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\JenisPelanggaranSiswa;
use App\Models\LaporanPembinaanSiswa;
use App\Services\Mobile\JenisPelanggaranSiswaMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JenisPelanggaranSiswaController extends Controller
{
    public function index(Request $request, JenisPelanggaranSiswaMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'cari' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(['semua', 'aktif', 'nonaktif'])],
            'tingkat' => ['nullable', Rule::in(['semua', ...array_keys(LaporanPembinaanSiswa::DAFTAR_TINGKAT)])],
            'kategori_id' => ['nullable', 'integer', Rule::exists('kategori_pembinaan_siswa', 'id')],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($filter)]);
    }

    public function store(Request $request, JenisPelanggaranSiswaMobileService $service): JsonResponse
    {
        $request->merge(['kode' => $service->rapikanKode($request->input('kode'))]);
        $jenis = $service->tambah($request->validate($this->aturanValidasi()));
        $jenis->load('kategoriPembinaanSiswa:id,nama,kode,aktif')->loadCount('butirPelanggaranLaporan');

        return $this->tanpaCache([
            'pesan' => 'Jenis pelanggaran berhasil ditambahkan.',
            'data' => $service->ringkas($jenis),
        ])->setStatusCode(201);
    }

    public function update(
        Request $request,
        JenisPelanggaranSiswa $jenisPelanggaranSiswa,
        JenisPelanggaranSiswaMobileService $service,
    ): JsonResponse {
        $request->merge(['kode' => $service->rapikanKode($request->input('kode'))]);
        $service->ubah(
            $jenisPelanggaranSiswa,
            $request->validate($this->aturanValidasi($jenisPelanggaranSiswa)),
        );

        return $this->tanpaCache([
            'pesan' => 'Jenis pelanggaran berhasil diperbarui. Bobot lama pada laporan tetap tersimpan.',
        ]);
    }

    public function destroy(
        JenisPelanggaranSiswa $jenisPelanggaranSiswa,
        JenisPelanggaranSiswaMobileService $service,
    ): JsonResponse {
        $service->nonaktifkan($jenisPelanggaranSiswa);

        return $this->tanpaCache([
            'pesan' => 'Jenis pelanggaran berhasil dinonaktifkan tanpa mengubah laporan lama.',
        ]);
    }

    private function aturanValidasi(?JenisPelanggaranSiswa $jenis = null): array
    {
        return [
            'kategori_pembinaan_siswa_id' => ['nullable', 'integer', Rule::exists('kategori_pembinaan_siswa', 'id')],
            'kode' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Z0-9_]+$/',
                Rule::unique('jenis_pelanggaran_siswa', 'kode')->ignore($jenis),
            ],
            'nama' => ['required', 'string'],
            'tingkat' => ['required', Rule::in(array_keys(LaporanPembinaanSiswa::DAFTAR_TINGKAT))],
            'poin' => ['required', 'integer', 'min:1', 'max:1000'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'aktif' => ['required', 'boolean'],
        ];
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
