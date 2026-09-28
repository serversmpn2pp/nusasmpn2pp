<?php

namespace App\Services\Cbt;

use App\Models\PesertaUjianCbt;
use App\Models\UjianCbt;
use Illuminate\Support\Facades\DB;

class SelesaikanPengerjaanKedaluwarsaCbtService
{
    public const TOLERANSI_AUTOSAVE_DETIK = 60;

    public function __construct(
        private readonly BatasWaktuPesertaUjianCbtService $batasWaktu,
        private readonly KoreksiOtomatisCbtService $koreksiOtomatis,
    ) {}

    public function selesaikanSemua(int $toleransiDetik = self::TOLERANSI_AUTOSAVE_DETIK): int
    {
        $jumlah = 0;

        PesertaUjianCbt::query()
            ->whereIn('status', ['sedang_mengerjakan', 'terblokir'])
            ->whereNotNull('waktu_mulai')
            ->orderBy('id')
            ->chunkById(100, function ($peserta) use (&$jumlah, $toleransiDetik) {
                foreach ($peserta as $item) {
                    $jumlah += $this->selesaikanPeserta($item, $toleransiDetik) ? 1 : 0;
                }
            });

        return $jumlah;
    }

    public function selesaikanUjian(UjianCbt $ujian, int $toleransiDetik = self::TOLERANSI_AUTOSAVE_DETIK): int
    {
        $jumlah = 0;

        $ujian->pesertaUjianCbt()
            ->whereIn('status', ['sedang_mengerjakan', 'terblokir'])
            ->whereNotNull('waktu_mulai')
            ->orderBy('id')
            ->chunkById(100, function ($peserta) use (&$jumlah, $toleransiDetik) {
                foreach ($peserta as $item) {
                    $jumlah += $this->selesaikanPeserta($item, $toleransiDetik) ? 1 : 0;
                }
            });

        return $jumlah;
    }

    public function selesaikanPeserta(
        PesertaUjianCbt $peserta,
        int $toleransiDetik = self::TOLERANSI_AUTOSAVE_DETIK,
    ): bool {
        return DB::transaction(function () use ($peserta, $toleransiDetik) {
            $terkunci = PesertaUjianCbt::query()
                ->with(['ujianCbt', 'sesiUjianCbt'])
                ->lockForUpdate()
                ->findOrFail($peserta->id);

            if (! in_array($terkunci->status, ['sedang_mengerjakan', 'terblokir'], true)
                || ! $terkunci->waktu_mulai) {
                return false;
            }

            $batas = $this->batasWaktu->batasAkses($terkunci);
            if (! $batas || now()->lt($batas->copy()->addSeconds(max(0, $toleransiDetik)))) {
                return false;
            }

            $perubahan = [
                'status' => 'selesai',
                'waktu_selesai' => $batas,
                'menit_tersisa' => 0,
                'selesai_otomatis_pada' => now(),
                'cara_selesai' => 'waktu_habis',
            ];

            if ($terkunci->status_susulan === 'dijadwalkan') {
                $perubahan['status_susulan'] = 'selesai';
            } elseif ($terkunci->status_susulan === 'menunggu_jadwal') {
                $perubahan['status_susulan'] = null;
            }

            if (in_array($terkunci->status_kehadiran_ujian, [null, 'belum_absen', 'alfa'], true)) {
                $perubahan['status_kehadiran_ujian'] = 'hadir';
                $perubahan['catatan_kehadiran_ujian'] = $this->catatanKehadiran($terkunci);
            }

            $terkunci->update($perubahan);
            $this->koreksiOtomatis->koreksiPeserta($terkunci->fresh());

            return true;
        });
    }

    private function catatanKehadiran(PesertaUjianCbt $peserta): string
    {
        $tambahan = 'Kehadiran diselaraskan otomatis karena peserta tercatat telah memulai ujian.';
        $catatan = trim((string) $peserta->catatan_kehadiran_ujian);

        return $catatan === '' ? $tambahan : $catatan.' '.$tambahan;
    }
}
