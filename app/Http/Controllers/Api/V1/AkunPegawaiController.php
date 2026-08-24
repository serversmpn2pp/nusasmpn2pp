<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Pegawai;
use App\Services\Mobile\AkunPegawaiMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AkunPegawaiController extends Controller
{
    public function index(Request $request, AkunPegawaiMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'cari' => ['nullable', 'string', 'max:100'],
            'status_akun' => ['nullable', Rule::in(['semua', 'sudah', 'belum', 'tanpa_nip'])],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($request->user(), $filter)]);
    }

    public function show(
        Request $request,
        Pegawai $pegawai,
        AkunPegawaiMobileService $service,
    ): JsonResponse {
        return $this->tanpaCache(['data' => $service->detail($request->user(), $pegawai)]);
    }

    public function store(Pegawai $pegawai, AkunPegawaiMobileService $service): JsonResponse
    {
        $akun = $service->buat($pegawai);

        return $this->tanpaCache([
            'pesan' => 'Akun pegawai berhasil dibuat. Username: '.$akun->username.'.',
            'data' => ['id' => (int) $akun->id, 'username' => $akun->username],
        ])->setStatusCode(201);
    }

    public function storeMassal(AkunPegawaiMobileService $service): JsonResponse
    {
        $ringkasan = $service->buatMassal();

        return $this->tanpaCache([
            'pesan' => 'Pembuatan akun pegawai selesai.',
            'data' => $ringkasan,
        ]);
    }

    public function resetKataSandi(
        Pegawai $pegawai,
        AkunPegawaiMobileService $service,
    ): JsonResponse {
        $akun = $service->resetKataSandi($pegawai);

        return $this->tanpaCache([
            'pesan' => 'Kata sandi akun '.$akun->nama.' berhasil direset ke default.',
        ]);
    }

    public function updateStatus(
        Request $request,
        Pegawai $pegawai,
        AkunPegawaiMobileService $service,
    ): JsonResponse {
        $data = $request->validate(['aktif' => ['required', 'boolean']]);
        $akun = $service->ubahStatus($pegawai, (bool) $data['aktif']);

        return $this->tanpaCache([
            'pesan' => 'Status akun '.$akun->nama.' berhasil diperbarui.',
        ]);
    }

    public function updatePeran(
        Request $request,
        Pegawai $pegawai,
        AkunPegawaiMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'peran_ids' => ['nullable', 'array'],
            'peran_ids.*' => [
                'integer',
                Rule::exists('peran', 'id')->where('aktif', true),
            ],
        ]);
        $akun = $service->ubahPeran($pegawai, $data['peran_ids'] ?? []);

        return $this->tanpaCache([
            'pesan' => 'Role akun '.$akun->nama.' berhasil diperbarui.',
        ]);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
