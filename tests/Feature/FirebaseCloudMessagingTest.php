<?php

namespace Tests\Feature;

use App\Contracts\FirebaseAccessTokenProvider;
use App\Jobs\KirimPushNotification;
use App\Models\NotifikasiPengguna;
use App\Models\Pengguna;
use App\Models\PerangkatNotifikasiPush;
use App\Services\Notifikasi\FirebaseCloudMessagingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class FirebaseCloudMessagingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.firebase.push_enabled', true);
        config()->set('services.firebase.project_id', 'nusa-sekolah');
        config()->set('services.firebase.device_stale_days', 45);
        $this->app->instance(
            FirebaseAccessTokenProvider::class,
            new class implements FirebaseAccessTokenProvider
            {
                public function token(): string
                {
                    return 'token-akses-google';
                }
            },
        );
    }

    public function test_job_mengirim_notifikasi_dan_tujuan_native_ke_perangkat_aktif(): void
    {
        Http::fake([
            'fcm.googleapis.com/*' => Http::response(['name' => 'projects/nusa/messages/1']),
        ]);

        [$notifikasi, $perangkat] = $this->buatData('/nilai-saya');

        (new KirimPushNotification($notifikasi->id))->handle(
            app(FirebaseCloudMessagingService::class),
        );

        $this->assertTrue($perangkat->fresh()->aktif);
        Http::assertSent(function (Request $request) use ($notifikasi, $perangkat): bool {
            return $request->url()
                    === 'https://fcm.googleapis.com/v1/projects/nusa-sekolah/messages:send'
                && $request->hasHeader('Authorization', 'Bearer token-akses-google')
                && $request['message']['token'] === $perangkat->token
                && $request['message']['notification']['title'] === $notifikasi->judul
                && $request['message']['data']['notifikasi_id'] === (string) $notifikasi->id
                && $request['message']['data']['tujuan'] === '/nilai-saya'
                && $request['message']['android']['notification']['channel_id']
                    === 'nusa_notifications';
        });
    }

    public function test_job_menonaktifkan_token_yang_dinyatakan_tidak_terdaftar_oleh_fcm(): void
    {
        Http::fake([
            'fcm.googleapis.com/*' => Http::response([
                'error' => [
                    'status' => 'NOT_FOUND',
                    'details' => [[
                        '@type' => 'type.googleapis.com/google.firebase.fcm.v1.FcmError',
                        'errorCode' => 'UNREGISTERED',
                    ]],
                ],
            ], 404),
        ]);

        [$notifikasi, $perangkat] = $this->buatData(null);

        (new KirimPushNotification($notifikasi->id))->handle(
            app(FirebaseCloudMessagingService::class),
        );

        $this->assertFalse($perangkat->fresh()->aktif);
    }

    private function buatData(?string $tautan): array
    {
        $pengguna = Pengguna::where('username', 'administrator')->firstOrFail();
        $notifikasi = NotifikasiPengguna::create([
            'pengguna_id' => $pengguna->id,
            'jenis' => 'informasi',
            'judul' => 'Nilai telah dipublikasikan',
            'pesan' => 'Nilai terbaru sudah dapat dilihat di NUSA.',
            'tautan' => $tautan,
        ]);
        $perangkat = PerangkatNotifikasiPush::create([
            'pengguna_id' => $pengguna->id,
            'token' => 'fcm-token-uji-789',
            'platform' => 'android',
            'aktif' => true,
            'terakhir_terlihat_pada' => now(),
        ]);

        return [$notifikasi, $perangkat];
    }
}
