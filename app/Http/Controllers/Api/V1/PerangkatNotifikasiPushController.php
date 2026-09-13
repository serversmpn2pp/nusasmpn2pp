<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PerangkatNotifikasiPush;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PerangkatNotifikasiPushController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
            'platform' => ['required', Rule::in(['android', 'ios'])],
            'nama_perangkat' => ['nullable', 'string', 'max:120'],
            'versi_aplikasi' => ['nullable', 'string', 'max:40'],
        ]);

        $perangkat = PerangkatNotifikasiPush::query()->updateOrCreate(
            ['token' => $data['token']],
            [
                'pengguna_id' => $request->user()->id,
                'platform' => $data['platform'],
                'nama_perangkat' => $data['nama_perangkat'] ?? null,
                'versi_aplikasi' => $data['versi_aplikasi'] ?? null,
                'aktif' => true,
                'terakhir_terlihat_pada' => now(),
            ],
        );

        return response()->json([
            'message' => 'Perangkat siap menerima notifikasi NUSA.',
            'data' => [
                'id' => (int) $perangkat->id,
                'aktif' => true,
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
        ]);

        $jumlah = PerangkatNotifikasiPush::query()
            ->where('pengguna_id', $request->user()->id)
            ->where('token', $data['token'])
            ->update([
                'aktif' => false,
                'updated_at' => now(),
            ]);

        return response()->json([
            'message' => 'Notifikasi pada perangkat ini telah dinonaktifkan.',
            'data' => ['jumlah_dinonaktifkan' => $jumlah],
        ]);
    }
}
