<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\JadwalPiketGuru;
use App\Services\Mobile\JadwalGuruPiketMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JadwalGuruPiketController extends Controller
{
    public function index(Request $request, JadwalGuruPiketMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'hari' => ['nullable', Rule::in(['semua', ...array_keys(JadwalPiketGuru::DAFTAR_HARI)])],
            'status' => ['nullable', Rule::in(['semua', 'aktif', 'nonaktif'])],
            'cari' => ['nullable', 'string', 'max:100'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($request->user(), $filter)]);
    }

    public function referensi(Request $request, JadwalGuruPiketMobileService $service): JsonResponse
    {
        $data = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
        ]);

        return $this->tanpaCache(['data' => $service->referensi($data['tahun_pelajaran_id'] ?? null)]);
    }

    public function store(Request $request, JadwalGuruPiketMobileService $service): JsonResponse
    {
        $data = $request->validate([
            'tahun_pelajaran_id' => ['required', 'integer', 'exists:tahun_pelajaran,id'],
            'hari' => ['required', Rule::in(array_keys(JadwalPiketGuru::DAFTAR_HARI))],
            'pegawai_ids' => ['required', 'array', 'min:1'],
            'pegawai_ids.*' => ['required', 'integer', 'distinct', 'exists:pegawai,id'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'aktif' => ['required', 'boolean'],
        ]);
        $jumlah = $service->tambah($data);

        return $this->tanpaCache(['pesan' => "{$jumlah} guru berhasil dimasukkan ke jadwal piket."])->setStatusCode(201);
    }

    public function update(
        Request $request,
        JadwalPiketGuru $jadwalPiketGuru,
        JadwalGuruPiketMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'tahun_pelajaran_id' => ['required', 'integer', 'exists:tahun_pelajaran,id'],
            'hari' => ['required', Rule::in(array_keys(JadwalPiketGuru::DAFTAR_HARI))],
            'pegawai_id' => ['required', 'integer', 'exists:pegawai,id'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
            'aktif' => ['required', 'boolean'],
        ]);
        $service->ubah($jadwalPiketGuru, $data);

        return $this->tanpaCache(['pesan' => 'Jadwal guru piket berhasil diperbarui.']);
    }

    public function destroy(JadwalPiketGuru $jadwalPiketGuru): JsonResponse
    {
        $jadwalPiketGuru->delete();

        return $this->tanpaCache(['pesan' => 'Guru berhasil dikeluarkan dari jadwal piket.']);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
