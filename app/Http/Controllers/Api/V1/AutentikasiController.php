<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\UbahKataSandiRequest;
use App\Http\Resources\Api\V1\PenggunaResource;
use App\Models\Pengguna;
use App\Models\RiwayatLogin;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AutentikasiController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();
        $pengguna = Pengguna::query()
            ->where('username', $data['username'])
            ->first();
        $berhasil = $pengguna?->aktif
            && Hash::check($data['password'], $pengguna->getAuthPassword());

        RiwayatLogin::create([
            'pengguna_id' => $pengguna?->id,
            'username' => $data['username'],
            'berhasil' => $berhasil,
            'alamat_ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        if (! $berhasil) {
            throw ValidationException::withMessages([
                'username' => 'Username atau kata sandi tidak sesuai.',
            ]);
        }

        $pengguna->tokens()
            ->where('name', $data['device_name'])
            ->delete();

        $token = $pengguna->createToken($data['device_name'], ['mobile'])->plainTextToken;

        $pengguna->forceFill([
            'terakhir_login_pada' => now(),
        ])->save();

        return response()->json([
            'message' => 'Selamat datang di NUSA.',
            'token' => $token,
            'pengguna' => new PenggunaResource($this->muatProfil($pengguna)),
        ]);
    }

    public function saya(Request $request): JsonResponse
    {
        return response()->json([
            'pengguna' => new PenggunaResource($this->muatProfil($request->user())),
        ]);
    }

    public function ubahKataSandi(UbahKataSandiRequest $request): JsonResponse
    {
        $pengguna = $request->user();
        $data = $request->validated();

        if (! Hash::check($data['kata_sandi_lama'], $pengguna->getAuthPassword())) {
            throw ValidationException::withMessages([
                'kata_sandi_lama' => 'Kata sandi lama tidak sesuai.',
            ]);
        }

        $pengguna->forceFill([
            'kata_sandi' => Hash::make($data['kata_sandi_baru']),
            'kata_sandi_awal' => null,
            'wajib_ganti_kata_sandi' => false,
        ])->save();

        return response()->json([
            'message' => 'Kata sandi berhasil diganti.',
            'pengguna' => new PenggunaResource($this->muatProfil($pengguna)),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->json([
            'message' => 'Anda telah keluar dari NUSA.',
        ]);
    }

    private function muatProfil(Pengguna $pengguna): Pengguna
    {
        return $pengguna->load([
            'orangTuaWali:id,pengguna_id',
            'daftarPeran' => fn ($query) => $query->where('peran.aktif', true),
            'daftarPeran.izin' => fn ($query) => $query->where('izin.aktif', true),
        ]);
    }
}
