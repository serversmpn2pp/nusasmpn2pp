<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\NotifikasiPengguna;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotifikasiController extends Controller
{
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
