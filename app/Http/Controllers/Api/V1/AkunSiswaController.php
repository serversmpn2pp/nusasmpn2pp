<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Services\Mobile\AkunSiswaMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AkunSiswaController extends Controller
{
    public function index(Request $request, AkunSiswaMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'cari' => ['nullable', 'string', 'max:100'],
            'status_akun' => ['nullable', Rule::in(['semua', 'sudah', 'belum', 'tanpa_nisn'])],
            'kelas_id' => ['nullable', 'integer', 'min:1'],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($request->user(), $filter)]);
    }

    public function show(Request $request, Siswa $siswa, AkunSiswaMobileService $service): JsonResponse
    {
        return $this->tanpaCache(['data' => $service->detail($request->user(), $siswa)]);
    }

    public function store(Siswa $siswa, AkunSiswaMobileService $service): JsonResponse
    {
        $akun = $service->buat($siswa);

        return $this->tanpaCache([
            'pesan' => 'Akun siswa berhasil dibuat. Username: '.$akun->username.'.',
            'data' => ['id' => (int) $akun->id, 'username' => $akun->username],
        ])->setStatusCode(201);
    }

    public function storeMassal(Request $request, Kelas $kelas, AkunSiswaMobileService $service): JsonResponse
    {
        $ringkasan = $service->buatMassal($request->user(), $kelas);

        return $this->tanpaCache([
            'pesan' => 'Pembuatan akun siswa kelas '.$kelas->nama.' selesai.',
            'data' => $ringkasan,
        ]);
    }

    public function resetKataSandi(Siswa $siswa, AkunSiswaMobileService $service): JsonResponse
    {
        $akun = $service->resetKataSandi($siswa);

        return $this->tanpaCache([
            'pesan' => 'Kata sandi awal akun '.$akun->nama.' berhasil dibuat ulang.',
        ]);
    }

    public function updateStatus(Request $request, Siswa $siswa, AkunSiswaMobileService $service): JsonResponse
    {
        $data = $request->validate(['aktif' => ['required', 'boolean']]);
        $akun = $service->ubahStatus($siswa, (bool) $data['aktif']);

        return $this->tanpaCache([
            'pesan' => 'Status akun '.$akun->nama.' berhasil diperbarui.',
        ]);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
