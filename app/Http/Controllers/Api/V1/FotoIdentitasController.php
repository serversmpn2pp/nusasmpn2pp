<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Pegawai;
use App\Models\Siswa;
use App\Services\FotoProfilService;
use App\Services\Mobile\FotoIdentitasMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FotoIdentitasController extends Controller
{
    public function index(Request $request, FotoIdentitasMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'tab' => ['nullable', Rule::in(['siswa', 'pegawai'])],
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'status_foto' => ['nullable', Rule::in(['semua', 'belum', 'sudah'])],
            'status_pegawai' => ['nullable', Rule::in(['semua', 'aktif', 'nonaktif'])],
            'jenis_pegawai' => ['nullable', 'string', 'max:100'],
            'cari' => ['nullable', 'string', 'max:100'],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($request->user(), $filter)]);
    }

    public function updateSiswa(
        Request $request,
        Siswa $siswa,
        FotoIdentitasMobileService $service,
        FotoProfilService $fotoProfilService,
    ): JsonResponse {
        $data = $request->validate([
            'foto' => $fotoProfilService->aturan(wajib: true),
        ], $fotoProfilService->pesanValidasi());
        $url = $service->simpanFotoSiswa($request->user(), $siswa, $data['foto']);

        return $this->tanpaCache([
            'pesan' => 'Foto siswa berhasil diperbarui.',
            'data' => ['foto_url' => $url],
        ]);
    }

    public function updatePegawai(
        Request $request,
        Pegawai $pegawai,
        FotoIdentitasMobileService $service,
        FotoProfilService $fotoProfilService,
    ): JsonResponse {
        $data = $request->validate([
            'foto' => $fotoProfilService->aturan(wajib: true),
        ], $fotoProfilService->pesanValidasi());
        $url = $service->simpanFotoPegawai($request->user(), $pegawai, $data['foto']);

        return $this->tanpaCache([
            'pesan' => 'Foto pegawai berhasil diperbarui.',
            'data' => ['foto_url' => $url],
        ]);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
