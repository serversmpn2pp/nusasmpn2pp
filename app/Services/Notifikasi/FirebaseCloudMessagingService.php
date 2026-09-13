<?php

namespace App\Services\Notifikasi;

use App\Contracts\FirebaseAccessTokenProvider;
use App\Models\NotifikasiPengguna;
use App\Services\Mobile\TujuanNotifikasiMobileService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class FirebaseCloudMessagingService
{
    public function __construct(
        private readonly FirebaseAccessTokenProvider $accessTokenProvider,
        private readonly TujuanNotifikasiMobileService $tujuanNotifikasi,
    ) {}

    /**
     * Mengembalikan false hanya saat FCM memastikan token perangkat tidak lagi terdaftar.
     */
    public function kirim(NotifikasiPengguna $notifikasi, string $tokenPerangkat): bool
    {
        $projectId = trim((string) config('services.firebase.project_id'));

        if ($projectId === '') {
            throw new RuntimeException('Project ID Firebase belum dikonfigurasi.');
        }

        $respons = Http::acceptJson()
            ->withToken($this->accessTokenProvider->token())
            ->timeout(20)
            ->post(
                'https://fcm.googleapis.com/v1/projects/'.rawurlencode($projectId).'/messages:send',
                ['message' => $this->pesan($notifikasi, $tokenPerangkat)],
            );

        if ($respons->successful()) {
            return true;
        }

        if ($this->tokenTidakTerdaftar($respons)) {
            return false;
        }

        $status = (string) ($respons->json('error.status') ?: 'UNKNOWN');

        throw new RuntimeException(
            "Pengiriman FCM gagal dengan HTTP {$respons->status()} ({$status}).",
        );
    }

    private function pesan(NotifikasiPengguna $notifikasi, string $tokenPerangkat): array
    {
        return [
            'token' => $tokenPerangkat,
            'notification' => [
                'title' => Str::limit(strip_tags($notifikasi->judul), 120),
                'body' => Str::limit(strip_tags($notifikasi->pesan), 500),
            ],
            'data' => [
                'notifikasi_id' => (string) $notifikasi->id,
                'jenis' => (string) $notifikasi->jenis,
                'tujuan' => $this->tujuanNotifikasi->untuk($notifikasi)
                    ?? '/beranda?tab=notifikasi',
            ],
            'android' => [
                'priority' => 'high',
                'notification' => [
                    'channel_id' => 'nusa_notifications',
                    'sound' => 'default',
                ],
            ],
        ];
    }

    private function tokenTidakTerdaftar(Response $respons): bool
    {
        if ($respons->status() !== 404 || $respons->json('error.status') !== 'NOT_FOUND') {
            return false;
        }

        return collect($respons->json('error.details', []))
            ->contains(fn ($detail) => is_array($detail)
                && ($detail['errorCode'] ?? null) === 'UNREGISTERED');
    }
}
