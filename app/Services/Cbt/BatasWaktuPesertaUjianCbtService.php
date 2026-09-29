<?php

namespace App\Services\Cbt;

use App\Models\PesertaUjianCbt;
use Carbon\Carbon;

class BatasWaktuPesertaUjianCbtService
{
    public function batasAkses(PesertaUjianCbt $peserta): ?Carbon
    {
        $peserta->loadMissing(['ujianCbt', 'sesiUjianCbt']);

        if ($peserta->waktu_tambahan_sampai) {
            return $peserta->waktu_tambahan_sampai->copy();
        }

        $batasJadwal = $peserta->susulanDijadwalkan()
            ? $peserta->susulan_selesai
            : ($peserta->sesiUjianCbt?->waktu_selesai ?: $peserta->ujianCbt?->tanggal_selesai);

        if (! $peserta->waktu_mulai || ! $peserta->ujianCbt) {
            return $batasJadwal?->copy();
        }

        $batasDurasi = $peserta->waktu_mulai->copy()->addMinutes((int) $peserta->ujianCbt->durasi_menit);

        return $batasJadwal && $batasJadwal->lt($batasDurasi)
            ? $batasJadwal->copy()
            : $batasDurasi;
    }

    public function sisaDetik(PesertaUjianCbt $peserta): int
    {
        $peserta->loadMissing('ujianCbt');

        if (! $peserta->waktu_mulai) {
            return max(0, (int) ($peserta->ujianCbt?->durasi_menit ?? 0) * 60);
        }

        $batas = $this->batasAkses($peserta);

        return $batas ? (int) max(0, now()->diffInSeconds($batas, false)) : 0;
    }

    public function waktuTambahanAktif(PesertaUjianCbt $peserta): bool
    {
        return $peserta->waktu_tambahan_sampai
            && now()->lt($peserta->waktu_tambahan_sampai)
            && in_array($peserta->status, ['sedang_mengerjakan', 'terblokir'], true);
    }
}
