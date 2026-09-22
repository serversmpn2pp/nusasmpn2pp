<?php

namespace App\Services\Cbt;

use App\Models\RuangUjianCbt;
use Illuminate\Support\Carbon;

class JendelaPresensiUjianCbt
{
    public const MENIT_SEBELUM_UJIAN = 60;

    public function status(RuangUjianCbt $ruang, ?Carbon $sekarang = null): array
    {
        $sekarang ??= now();
        $waktuMulai = $this->waktuMulai($ruang);
        $dibukaPada = $waktuMulai?->copy()->subMinutes(self::MENIT_SEBELUM_UJIAN);
        $dibuka = ! $dibukaPada || $sekarang->greaterThanOrEqualTo($dibukaPada);

        return [
            'dibuka' => $dibuka,
            'waktu_mulai' => $waktuMulai,
            'dibuka_pada' => $dibukaPada,
            'menit_sebelum_ujian' => self::MENIT_SEBELUM_UJIAN,
            'pesan' => $dibuka || ! $waktuMulai || ! $dibukaPada
                ? null
                : $this->pesanBelumDibuka($waktuMulai, $dibukaPada),
        ];
    }

    public function sudahDibuka(RuangUjianCbt $ruang, ?Carbon $sekarang = null): bool
    {
        return $this->status($ruang, $sekarang)['dibuka'];
    }

    private function waktuMulai(RuangUjianCbt $ruang): ?Carbon
    {
        $ruang->loadMissing(['jadwalUjianCbt', 'sesiUjianCbt', 'ujianCbt']);

        if ($ruang->jadwalUjianCbt?->tanggal && $ruang->jadwalUjianCbt?->waktu_mulai) {
            return Carbon::parse(
                $ruang->jadwalUjianCbt->tanggal->format('Y-m-d').' '.substr((string) $ruang->jadwalUjianCbt->waktu_mulai, 0, 8)
            );
        }

        if ($ruang->sesiUjianCbt?->waktu_mulai) {
            return $ruang->sesiUjianCbt->waktu_mulai->copy();
        }

        return $ruang->ujianCbt?->tanggal_mulai?->copy();
    }

    private function pesanBelumDibuka(Carbon $waktuMulai, Carbon $dibukaPada): string
    {
        $jadwal = $waktuMulai->copy()->locale('id')->translatedFormat('l, d F Y').' pukul '.$waktuMulai->format('H:i');
        $waktuBuka = $dibukaPada->copy()->locale('id')->translatedFormat('l, d F Y').' pukul '.$dibukaPada->format('H:i');

        return "Ujian dijadwalkan {$jadwal}. Presensi dapat dicatat mulai {$waktuBuka} (".self::MENIT_SEBELUM_UJIAN.' menit sebelum ujian).';
    }
}
