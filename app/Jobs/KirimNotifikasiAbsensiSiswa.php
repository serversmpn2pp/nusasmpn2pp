<?php

namespace App\Jobs;

use App\Models\NotifikasiAbsensiSiswa;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class KirimNotifikasiAbsensiSiswa implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public int $notifikasiId) {}

    public function backoff(): array
    {
        return [60, 180, 600];
    }

    public function handle(): void
    {
        $notifikasi = NotifikasiAbsensiSiswa::find($this->notifikasiId);

        if (! $notifikasi || $notifikasi->kanal !== 'whatsapp' || in_array($notifikasi->status, [
            NotifikasiAbsensiSiswa::STATUS_TERKIRIM,
            NotifikasiAbsensiSiswa::STATUS_SIMULASI,
            NotifikasiAbsensiSiswa::STATUS_DILEWATI,
        ], true)) {
            return;
        }

        // Kompatibilitas job lama di antrean: jangan kirim WA atau mengirim ulang
        // presensi historis melalui NUSA tanpa permintaan baru.
        $notifikasi->update([
            'status' => NotifikasiAbsensiSiswa::STATUS_DILEWATI,
            'pesan_error' => 'Pengiriman WhatsApp presensi dihentikan; scan baru menggunakan notifikasi NUSA.',
        ]);
    }
}
