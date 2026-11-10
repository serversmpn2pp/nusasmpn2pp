<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotifikasiPengguna;
use App\Services\Mobile\NotifikasiMobileService;
use App\Services\Mobile\TujuanNotifikasiMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotifikasiController extends Controller
{
    public function index(Request $request, NotifikasiMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'status' => ['nullable', Rule::in(['semua', 'belum_dibaca'])],
            'sebelum_id' => ['nullable', 'integer', 'min:1'],
        ]);

        return response()->json(['data' => $service->daftar($request->user(), $filter)])
            ->header('Cache-Control', 'private, no-store');
    }

    public function tujuan(
        Request $request,
        NotifikasiPengguna $notifikasiPengguna,
        TujuanNotifikasiMobileService $tujuan,
    ): JsonResponse {
        abort_unless((int) $notifikasiPengguna->pengguna_id === (int) $request->user()->id, 403);

        // Teks asli hanya dikembalikan setelah sesi, status akun, dan pemilik lolos.
        // Verifikasi tidak menandai baca; payload FCM bukan sumber detail.
        return response()->json(['data' => [
            'id' => (int) $notifikasiPengguna->id,
            'tautan_mobile' => $tujuan->untuk($notifikasiPengguna),
            'judul' => $notifikasiPengguna->judul,
            'pesan' => $notifikasiPengguna->pesan,
        ]])->header('Cache-Control', 'private, no-store');
    }

    public function baca(Request $request, NotifikasiPengguna $notifikasiPengguna): JsonResponse
    {
        abort_unless(
            (int) $notifikasiPengguna->pengguna_id === (int) $request->user()->id,
            403,
        );

        $notifikasiPengguna->tandaiDibaca();

        return $this->response(
            $request,
            'Notifikasi ditandai sudah dibaca.',
            ['id' => (int) $notifikasiPengguna->id],
        );
    }

    public function bacaSemua(Request $request): JsonResponse
    {
        $jumlah = $request->user()
            ->notifikasiPengguna()
            ->belumDibaca()
            ->update([
                'dibaca_pada' => now(),
                'updated_at' => now(),
            ]);

        $pesan = $jumlah > 0
            ? $jumlah.' notifikasi ditandai sudah dibaca.'
            : 'Tidak ada notifikasi baru.';

        return $this->response($request, $pesan, ['jumlah_ditandai' => $jumlah]);
    }

    private function response(Request $request, string $pesan, array $tambahan = []): JsonResponse
    {
        return response()
            ->json([
                'message' => $pesan,
                'data' => $tambahan + [
                    'jumlah_belum_dibaca' => $request->user()
                        ->notifikasiPengguna()
                        ->belumDibaca()
                        ->count(),
                ],
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
