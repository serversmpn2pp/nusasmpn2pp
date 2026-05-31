<?php

namespace App\Jobs;

use App\Models\NotifikasiAbsensiSiswa;
use App\Services\Notifikasi\KlienWhatsappCloud;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class KirimNotifikasiAbsensiSiswa implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public int $notifikasiId)
    {
    }

    public function backoff(): array
    {
        return [60, 180, 600];
    }

    public function handle(KlienWhatsappCloud $klienWhatsappCloud): void
    {
        $notifikasi = NotifikasiAbsensiSiswa::find($this->notifikasiId);

        if (! $notifikasi || in_array($notifikasi->status, [
            NotifikasiAbsensiSiswa::STATUS_TERKIRIM,
            NotifikasiAbsensiSiswa::STATUS_SIMULASI,
            NotifikasiAbsensiSiswa::STATUS_DILEWATI,
        ], true)) {
            return;
        }

        $notifikasi->increment('jumlah_percobaan');

        if (blank($notifikasi->nomor_tujuan)) {
            $notifikasi->update([
                'status' => NotifikasiAbsensiSiswa::STATUS_DILEWATI,
                'pesan_error' => 'Nomor WhatsApp tujuan belum diisi.',
                'gagal_pada' => now(),
            ]);

            return;
        }

        if ($notifikasi->mode_pengiriman === 'simulasi') {
            $notifikasi->update([
                'status' => NotifikasiAbsensiSiswa::STATUS_SIMULASI,
                'respons' => ['mode' => 'simulasi'],
                'dikirim_pada' => now(),
                'pesan_error' => null,
            ]);

            return;
        }

        try {
            $respons = $klienWhatsappCloud->kirimTeks($notifikasi->nomor_tujuan, $notifikasi->pesan);

            $notifikasi->update([
                'status' => NotifikasiAbsensiSiswa::STATUS_TERKIRIM,
                'respons' => $respons,
                'dikirim_pada' => now(),
                'pesan_error' => null,
            ]);
        } catch (Throwable $exception) {
            $notifikasi->update([
                'status' => NotifikasiAbsensiSiswa::STATUS_GAGAL,
                'pesan_error' => $exception->getMessage(),
                'gagal_pada' => now(),
            ]);

            throw $exception;
        }
    }
}
