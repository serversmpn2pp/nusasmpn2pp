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

        $notifikasi->update(['kunci_unik' => 'nilai-dipublikasikan:1:20261006']);

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
                && $request['message']['notification']['body'] === $notifikasi->pesan
                && $request['message']['data']['notifikasi_id'] === (string) $notifikasi->id
                && $request['message']['data']['tujuan'] === '/nilai-saya'
                && $request['message']['android']['notification']['channel_id']
                    === 'nusa_notifications'
                && $request['message']['android']['notification']['visibility'] === 'PRIVATE';
        });
    }

    public function test_pencabutan_akun_langsung_menonaktifkan_perangkat_tanpa_mengubah_identitas(): void
    {
        Http::fake();
        [$notifikasi, $perangkat] = $this->buatData('/nilai-saya');
        $pengguna = $notifikasi->pengguna;
        $identitas = $pengguna->only(['pegawai_id', 'siswa_id', 'peran', 'akun_sistem']);
        $pengguna->update(['aktif' => false]);

        $this->assertFalse($perangkat->fresh()->aktif);
        $this->assertSame($identitas, $pengguna->fresh()->only(array_keys($identitas)));
        $this->jalankanJob($notifikasi);
        Http::assertNothingSent();
    }

    public function test_job_memeriksa_status_terbaru_walaupun_update_massal_melewati_event_model(): void
    {
        Http::fake();
        [$notifikasi, $perangkat] = $this->buatData('/nilai-saya');
        Pengguna::whereKey($notifikasi->pengguna_id)->update(['aktif' => false]);
        $this->assertTrue($perangkat->fresh()->aktif);

        $this->jalankanJob($notifikasi);
        Http::assertNothingSent();
        $this->assertFalse($perangkat->fresh()->aktif);
    }

    public function test_job_tidak_mengirim_ke_akun_yang_kembali_wajib_ganti_sandi(): void
    {
        Http::fake();
        [$notifikasi, $perangkat] = $this->buatData('/nilai-saya');
        Pengguna::whereKey($notifikasi->pengguna_id)->update(['wajib_ganti_kata_sandi' => true]);
        $this->jalankanJob($notifikasi);
        Http::assertNothingSent();
        $this->assertFalse($perangkat->fresh()->aktif);
    }

    public function test_job_tidak_memakai_token_yang_sudah_berpindah_ke_akun_lain(): void
    {
        Http::fake();
        [$notifikasi, $perangkat] = $this->buatData('/nilai-saya');
        $perangkat->update(['pengguna_id' => $this->penggunaLain()->id]);
        $this->jalankanJob($notifikasi);
        Http::assertNothingSent();
        $this->assertTrue($perangkat->fresh()->aktif);
    }

    public function test_status_dan_pemilik_diperiksa_ulang_sebelum_perangkat_berikutnya_dikirim(): void
    {
        [$notifikasi, $perangkat] = $this->buatData('/nilai-saya');
        $kedua = PerangkatNotifikasiPush::create([
            'pengguna_id' => $notifikasi->pengguna_id, 'token' => 'token-perangkat-kedua',
            'platform' => 'android', 'aktif' => true, 'terakhir_terlihat_pada' => now(),
        ]);
        $akunLain = $this->penggunaLain();
        Http::fake(function () use ($kedua, $akunLain) {
            $kedua->update(['pengguna_id' => $akunLain->id]);

            return Http::response(['name' => 'projects/nusa/messages/1']);
        });
        $this->jalankanJob($notifikasi);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $request) => $request['message']['token'] === $perangkat->token);
        $this->assertTrue($kedua->fresh()->aktif);
    }

    public function test_pencabutan_akun_di_tengah_job_menghentikan_pengiriman_selanjutnya(): void
    {
        [$notifikasi] = $this->buatData('/nilai-saya');
        PerangkatNotifikasiPush::create([
            'pengguna_id' => $notifikasi->pengguna_id, 'token' => 'token-kedua-dicabut',
            'platform' => 'android', 'aktif' => true, 'terakhir_terlihat_pada' => now(),
        ]);
        Http::fake(function () use ($notifikasi) {
            Pengguna::whereKey($notifikasi->pengguna_id)->update(['aktif' => false]);

            return Http::response(['name' => 'projects/nusa/messages/1']);
        });
        $this->jalankanJob($notifikasi);
        Http::assertSentCount(1);
        $this->assertSame(0, PerangkatNotifikasiPush::where('pengguna_id', $notifikasi->pengguna_id)->where('aktif', true)->count());
    }

    public function test_perangkat_nonaktif_dan_kedaluwarsa_tidak_menerima_push(): void
    {
        Http::fake();
        [$notifikasi, $perangkat] = $this->buatData(null);
        $perangkat->update(['aktif' => false]);
        $this->jalankanJob($notifikasi);
        $perangkat->update(['aktif' => true, 'terakhir_terlihat_pada' => now()->subDays(46)]);
        $this->jalankanJob($notifikasi);
        Http::assertNothingSent();
    }

    public function test_push_tidak_dikenal_tetap_umum_tanpa_mengubah_detail_di_database(): void
    {
        Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'projects/nusa/messages/1'])]);
        [$notifikasi] = $this->buatData(null);
        foreach (['Sanksi Nadia saldo 125 poin', 'Catatan privat siswi', 'Modul baru dengan detail rahasia'] as $rahasia) {
            $notifikasi->update(['judul' => $rahasia, 'pesan' => $rahasia, 'data_tambahan' => ['catatan' => $rahasia]]);
            $this->jalankanJob($notifikasi);
            $this->assertSame($rahasia, $notifikasi->fresh()->pesan);
        }
        Http::assertSentCount(3);
        Http::assertSent(fn (Request $request) => $request['message']['notification'] === [
            'title' => 'NUSA', 'body' => 'Ada pembaruan di NUSA. Buka aplikasi untuk melihat detail.',
        ]);
        foreach (Http::recorded() as [$request]) {
            $this->assertStringNotContainsString('Nadia', $request->body());
            $this->assertStringNotContainsString('catatan', $request->body());
            $this->assertStringNotContainsString('rahasia', $request->body());
        }
    }

    public function test_error_fcm_sementara_tetap_gagal_untuk_retry_bukan_mencabut_token(): void
    {
        Http::fake(['fcm.googleapis.com/*' => Http::response(['error' => ['status' => 'UNAVAILABLE']], 503)]);
        [$notifikasi, $perangkat] = $this->buatData(null);
        try {
            $this->jalankanJob($notifikasi);
            $this->fail('FCM 503 harus diteruskan ke mekanisme retry antrean.');
        } catch (\RuntimeException $error) {
            $this->assertStringContainsString('HTTP 503', $error->getMessage());
        }
        $this->assertTrue($perangkat->fresh()->aktif);
    }

    private function jalankanJob(NotifikasiPengguna $notifikasi): void
    {
        (new KirimPushNotification($notifikasi->id))->handle(app(FirebaseCloudMessagingService::class));
    }

    public function test_payload_fcm_sensitif_diredaksi_dan_presensi_tidak_memuat_identitas_anak(): void
    {
        Http::fake(['fcm.googleapis.com/*' => Http::response(['name' => 'projects/nusa/messages/1'])]);
        [$notifikasi] = $this->buatData('/sanksi-poin-siswa/1');
        foreach (['/sanksi-poin-siswa/1', '/konfirmasi-berhalangan-ibadah/2', '/presensi-anak?siswa_id=3&bulan=2026-10'] as $tautan) {
            $notifikasi->update(['tautan' => $tautan,
                'kunci_unik' => str_starts_with($tautan, '/presensi-anak') ? 'presensi-masuk-orang-tua:1' : null,
                'judul' => 'Nadia catatan rahasia', 'pesan' => 'Nadia rincian rahasia 125 poin',
                'data_tambahan' => ['catatan' => 'rahasia', 'jam_masuk' => '06:54', 'menit_terlambat' => 0],
            ]);
            $this->jalankanJob($notifikasi);
        }
        Http::assertSentCount(3);
        foreach (Http::recorded() as [$request]) {
            $this->assertStringNotContainsString('Nadia', $request->body());
            $this->assertStringNotContainsString('rahasia', $request->body());
            $this->assertStringNotContainsString('125', $request->body());
            $this->assertSame(['notifikasi_id', 'jenis', 'tujuan'], array_keys($request['message']['data']));
        }
        Http::assertSent(fn (Request $request) => $request['message']['notification']['body'] === 'Anak Anda tercatat hadir tepat waktu pukul 06.54 WIB.');
    }

    private function penggunaLain(): Pengguna
    {
        return Pengguna::create([
            'nama' => 'Akun Kedua', 'username' => 'akun-kedua-push', 'kata_sandi' => 'BukanSandiAwal123!',
            'peran' => 'pegawai', 'aktif' => true, 'akun_sistem' => false, 'wajib_ganti_kata_sandi' => false,
        ]);
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
            'kunci_unik' => $tautan === '/nilai-saya' ? 'nilai-dipublikasikan:1:20261006' : null,
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
