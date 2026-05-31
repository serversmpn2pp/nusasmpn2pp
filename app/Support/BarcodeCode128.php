<?php

namespace App\Support;

use InvalidArgumentException;

class BarcodeCode128
{
    private const POLA = [
        '212222', '222122', '222221', '121223', '121322', '131222', '122213', '122312',
        '132212', '221213', '221312', '231212', '112232', '122132', '122231', '113222',
        '123122', '123221', '223211', '221132', '221231', '213212', '223112', '312131',
        '311222', '321122', '321221', '312212', '322112', '322211', '212123', '212321',
        '232121', '111323', '131123', '131321', '112313', '132113', '132311', '211313',
        '231113', '231311', '112133', '112331', '132131', '113123', '113321', '133121',
        '313121', '211331', '231131', '213113', '213311', '213131', '311123', '311321',
        '331121', '312113', '312311', '332111', '314111', '221411', '431111', '111224',
        '111422', '121124', '121421', '141122', '141221', '112214', '112412', '122114',
        '122411', '142112', '142211', '241211', '221114', '413111', '241112', '134111',
        '111242', '121142', '121241', '114212', '124112', '124211', '411212', '421112',
        '421211', '212141', '214121', '412121', '111143', '111341', '131141', '114113',
        '114311', '411113', '411311', '113141', '114131', '311141', '411131', '211412',
        '211214', '211232', '2331112',
    ];

    public static function svg(string $kode): string
    {
        $kode = trim($kode);

        if ($kode === '' || strlen($kode) > 80 || preg_match('/^[\x20-\x7E]+$/', $kode) !== 1) {
            throw new InvalidArgumentException('Barcode Code 128 hanya mendukung 1 sampai 80 karakter ASCII yang dapat dicetak.');
        }

        $nilai = [104];
        $checksum = 104;

        foreach (str_split($kode) as $index => $karakter) {
            $kodeKarakter = ord($karakter) - 32;
            $nilai[] = $kodeKarakter;
            $checksum += $kodeKarakter * ($index + 1);
        }

        $nilai[] = $checksum % 103;
        $nilai[] = 106;

        $x = 10;
        $rectangles = [];

        foreach ($nilai as $kodePola) {
            foreach (str_split(self::POLA[$kodePola]) as $index => $lebar) {
                $lebar = (int) $lebar;

                if ($index % 2 === 0) {
                    $rectangles[] = '<rect x="' . $x . '" y="0" width="' . $lebar . '" height="48"/>';
                }

                $x += $lebar;
            }
        }

        $lebarSvg = $x + 10;
        $label = htmlspecialchars($kode, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $lebarSvg . ' 48" preserveAspectRatio="none" shape-rendering="crispEdges" aria-label="Barcode ' . $label . '">'
            . '<rect width="100%" height="100%" fill="#fff"/>'
            . '<g fill="#111827">' . implode('', $rectangles) . '</g>'
            . '</svg>';
    }
}
