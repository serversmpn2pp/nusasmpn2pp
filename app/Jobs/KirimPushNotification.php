<?php

namespace App\Jobs;

use App\Models\NotifikasiPengguna;
use App\Models\PerangkatNotifikasiPush;
use App\Services\Notifikasi\FirebaseCloudMessagingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class KirimPushNotification implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public int $notifikasiId) {}

    public function backoff(): array
    {
        return [30, 120, 600];
    }

    public function handle(FirebaseCloudMessagingService $firebase): void
    {
        if (! config('services.firebase.push_enabled')) {
            return;
        }

        $notifikasi = NotifikasiPengguna::query()->find($this->notifikasiId);

        if (! $notifikasi) {
            return;
        }

        $batasAktif = now()->subDays(
            max(1, (int) config('services.firebase.device_stale_days', 45)),
        );

        PerangkatNotifikasiPush::query()
            ->where('pengguna_id', $notifikasi->pengguna_id)
            ->where('aktif', true)
            ->where('terakhir_terlihat_pada', '>=', $batasAktif)
            ->orderBy('id')
            ->each(function (PerangkatNotifikasiPush $perangkat) use ($firebase, $notifikasi): void {
                if (! $firebase->kirim($notifikasi, $perangkat->token)) {
                    $perangkat->update(['aktif' => false]);
                }
            });
    }
}
