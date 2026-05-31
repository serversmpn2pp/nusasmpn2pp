<?php

namespace App\Services\Notifikasi;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class KlienWhatsappCloud
{
    public function kirimTeks(string $nomorTujuan, string $pesan): array
    {
        $baseUrl = rtrim((string) config('services.whatsapp.cloud_api_url'), '/');
        $phoneNumberId = (string) config('services.whatsapp.phone_number_id');
        $token = (string) config('services.whatsapp.access_token');

        if ($baseUrl === '' || $phoneNumberId === '' || $token === '') {
            throw new RuntimeException('Konfigurasi WhatsApp Cloud API belum lengkap.');
        }

        $response = Http::withToken($token)
            ->acceptJson()
            ->asJson()
            ->post($baseUrl . '/' . $phoneNumberId . '/messages', [
                'messaging_product' => 'whatsapp',
                'to' => $nomorTujuan,
                'type' => 'text',
                'text' => [
                    'preview_url' => false,
                    'body' => $pesan,
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException($response->body() ?: 'WhatsApp Cloud API menolak pesan.');
        }

        return $response->json() ?: [];
    }
}
