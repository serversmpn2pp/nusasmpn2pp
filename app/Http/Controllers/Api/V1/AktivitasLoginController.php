<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\RiwayatLogin;
use App\Services\Mobile\AktivitasLoginMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AktivitasLoginController extends Controller
{
    public function index(Request $request, AktivitasLoginMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'tampilan' => ['nullable', Rule::in(['pengguna', 'riwayat'])],
            'cari' => ['nullable', 'string', 'max:100'],
            'jenis_akun' => ['nullable', Rule::in(['semua', 'administrator', 'pegawai', 'siswa', 'orang_tua'])],
            'status_login' => ['nullable', Rule::in(['semua', 'pernah', 'belum'])],
            'status_percobaan' => ['nullable', Rule::in(['semua', 'berhasil', 'gagal'])],
            'perangkat' => ['nullable', Rule::in(['semua', 'android', 'ios', 'windows', 'mac', 'linux', 'lainnya'])],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($filter)]);
    }

    public function show(
        RiwayatLogin $riwayatLogin,
        AktivitasLoginMobileService $service,
    ): JsonResponse {
        return $this->tanpaCache(['data' => $service->detail($riwayatLogin)]);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
