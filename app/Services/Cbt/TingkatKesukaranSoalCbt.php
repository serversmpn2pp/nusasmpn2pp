<?php

namespace App\Services\Cbt;

class TingkatKesukaranSoalCbt
{
    // Batas operasional untuk menunda kategori pada sampel kecil, bukan jaminan validitas statistik.
    public const MINIMAL_HASIL = 10;

    public function hitung(float $totalSkor, int $dinilai, float $skorMaksimal, int $belumDinilai = 0, int $skorTidakValid = 0): array
    {
        $hasil = ['indeks' => null, 'kategori' => null, 'status' => 'belum_ada_nilai'];

        if ($skorTidakValid > 0 || ! is_finite($skorMaksimal) || $skorMaksimal <= 0
            || ! is_finite($totalSkor) || $totalSkor < 0
            || $totalSkor > $dinilai * $skorMaksimal + 1e-9) {
            return [...$hasil, 'status' => 'skor_tidak_valid'];
        }

        if ($dinilai === 0) {
            return $hasil;
        }

        // Hilangkan galat floating point sebelum membandingkan batas kategori.
        $indeks = round($totalSkor / ($dinilai * $skorMaksimal), 12);
        $hasil['indeks'] = $indeks;

        if ($belumDinilai > 0) {
            return [...$hasil, 'status' => 'menunggu_koreksi'];
        }

        if ($dinilai < self::MINIMAL_HASIL) {
            return [...$hasil, 'status' => 'sampel_terbatas'];
        }

        return [
            ...$hasil,
            'status' => 'siap',
            'kategori' => match (true) {
                $indeks <= 0.30 => 'sukar',
                $indeks <= 0.70 => 'sedang',
                default => 'mudah',
            },
        ];
    }
}
