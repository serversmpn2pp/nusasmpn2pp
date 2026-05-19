<?php

namespace App\Support;

use InvalidArgumentException;

class QrCodeNisn
{
    private const UKURAN = 21;
    private const DATA_CODEWORDS = 19;
    private const EC_CODEWORDS = 7;
    private const FORMAT_BITS_LOW_MASK_0 = 0b111011111000100;
    private const RS_DIVISOR_7 = [87, 229, 146, 149, 238, 102, 21];

    public static function svg(string $nisn): string
    {
        $nisn = trim($nisn);

        if (! preg_match('/^[0-9]{1,41}$/', $nisn)) {
            throw new InvalidArgumentException('QR NISN hanya mendukung angka 1 sampai 41 digit.');
        }

        $modules = self::buatModules($nisn);
        $ukuranSvg = self::UKURAN + 8;
        $path = [];

        foreach ($modules as $y => $baris) {
            foreach ($baris as $x => $gelap) {
                if ($gelap) {
                    $path[] = 'M' . ($x + 4) . ' ' . ($y + 4) . 'h1v1h-1z';
                }
            }
        }

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $ukuranSvg . ' ' . $ukuranSvg . '" shape-rendering="crispEdges" aria-label="QR Code NISN">'
            . '<rect width="100%" height="100%" fill="#fff"/>'
            . '<path fill="#111827" d="' . implode('', $path) . '"/>'
            . '</svg>';
    }

    private static function buatModules(string $nisn): array
    {
        $modules = array_fill(0, self::UKURAN, array_fill(0, self::UKURAN, false));
        $fungsi = array_fill(0, self::UKURAN, array_fill(0, self::UKURAN, false));

        self::gambarPolaFungsi($modules, $fungsi);
        self::gambarData($modules, $fungsi, self::buatBitData($nisn));
        self::gambarFormat($modules, $fungsi);

        return $modules;
    }

    private static function buatBitData(string $nisn): array
    {
        $bits = [];
        self::tambahBits($bits, 0b0001, 4);
        self::tambahBits($bits, strlen($nisn), 10);

        for ($i = 0; $i < strlen($nisn); $i += 3) {
            $bagian = substr($nisn, $i, 3);
            self::tambahBits($bits, (int) $bagian, match (strlen($bagian)) {
                1 => 4,
                2 => 7,
                default => 10,
            });
        }

        $kapasitasBits = self::DATA_CODEWORDS * 8;
        self::tambahBits($bits, 0, min(4, $kapasitasBits - count($bits)));

        while (count($bits) % 8 !== 0) {
            $bits[] = false;
        }

        $data = [];
        foreach (array_chunk($bits, 8) as $byteBits) {
            $byte = 0;
            foreach ($byteBits as $bit) {
                $byte = ($byte << 1) | ($bit ? 1 : 0);
            }
            $data[] = $byte;
        }

        for ($pad = 0; count($data) < self::DATA_CODEWORDS; $pad++) {
            $data[] = $pad % 2 === 0 ? 0xec : 0x11;
        }

        $codewords = array_merge($data, self::buatErrorCorrection($data));
        $hasil = [];

        foreach ($codewords as $byte) {
            self::tambahBits($hasil, $byte, 8);
        }

        return $hasil;
    }

    private static function buatErrorCorrection(array $data): array
    {
        $result = array_fill(0, self::EC_CODEWORDS, 0);

        foreach ($data as $byte) {
            $factor = $byte ^ $result[0];
            array_shift($result);
            $result[] = 0;

            foreach (self::RS_DIVISOR_7 as $i => $koefisien) {
                $result[$i] ^= self::gfMultiply($koefisien, $factor);
            }
        }

        return $result;
    }

    private static function gambarPolaFungsi(array &$modules, array &$fungsi): void
    {
        self::gambarFinder($modules, $fungsi, 0, 0);
        self::gambarFinder($modules, $fungsi, self::UKURAN - 7, 0);
        self::gambarFinder($modules, $fungsi, 0, self::UKURAN - 7);

        for ($i = 8; $i < self::UKURAN - 8; $i++) {
            $gelap = $i % 2 === 0;
            self::setFungsi($modules, $fungsi, 6, $i, $gelap);
            self::setFungsi($modules, $fungsi, $i, 6, $gelap);
        }

        for ($i = 0; $i <= 8; $i++) {
            if ($i !== 6) {
                self::setFungsi($modules, $fungsi, 8, $i, false);
                self::setFungsi($modules, $fungsi, $i, 8, false);
            }
        }

        for ($i = 0; $i < 8; $i++) {
            self::setFungsi($modules, $fungsi, self::UKURAN - 1 - $i, 8, false);
        }

        for ($i = 0; $i < 7; $i++) {
            self::setFungsi($modules, $fungsi, 8, self::UKURAN - 1 - $i, false);
        }

        self::setFungsi($modules, $fungsi, 8, self::UKURAN - 8, true);
    }

    private static function gambarFinder(array &$modules, array &$fungsi, int $x, int $y): void
    {
        for ($dy = -1; $dy <= 7; $dy++) {
            for ($dx = -1; $dx <= 7; $dx++) {
                $xx = $x + $dx;
                $yy = $y + $dy;

                if ($xx < 0 || $xx >= self::UKURAN || $yy < 0 || $yy >= self::UKURAN) {
                    continue;
                }

                $gelap = $dx >= 0 && $dx <= 6 && $dy >= 0 && $dy <= 6
                    && ($dx === 0 || $dx === 6 || $dy === 0 || $dy === 6 || ($dx >= 2 && $dx <= 4 && $dy >= 2 && $dy <= 4));

                self::setFungsi($modules, $fungsi, $xx, $yy, $gelap);
            }
        }
    }

    private static function gambarData(array &$modules, array $fungsi, array $bits): void
    {
        $indexBit = 0;
        $naik = true;
        $y = self::UKURAN - 1;

        for ($x = self::UKURAN - 1; $x > 0; $x -= 2) {
            if ($x === 6) {
                $x--;
            }

            while (true) {
                for ($i = 0; $i < 2; $i++) {
                    $xx = $x - $i;

                    if (! $fungsi[$y][$xx]) {
                        $bit = $bits[$indexBit] ?? false;
                        $modules[$y][$xx] = $bit xor self::mask($xx, $y);
                        $indexBit++;
                    }
                }

                $y += $naik ? -1 : 1;

                if ($y < 0 || $y >= self::UKURAN) {
                    $y += $naik ? 1 : -1;
                    $naik = ! $naik;
                    break;
                }
            }
        }
    }

    private static function gambarFormat(array &$modules, array &$fungsi): void
    {
        $bits = self::FORMAT_BITS_LOW_MASK_0;

        for ($i = 0; $i <= 5; $i++) {
            self::setFungsi($modules, $fungsi, 8, $i, self::bit($bits, $i));
        }

        self::setFungsi($modules, $fungsi, 8, 7, self::bit($bits, 6));
        self::setFungsi($modules, $fungsi, 8, 8, self::bit($bits, 7));
        self::setFungsi($modules, $fungsi, 7, 8, self::bit($bits, 8));

        for ($i = 9; $i < 15; $i++) {
            self::setFungsi($modules, $fungsi, 14 - $i, 8, self::bit($bits, $i));
        }

        for ($i = 0; $i < 8; $i++) {
            self::setFungsi($modules, $fungsi, self::UKURAN - 1 - $i, 8, self::bit($bits, $i));
        }

        for ($i = 8; $i < 15; $i++) {
            self::setFungsi($modules, $fungsi, 8, self::UKURAN - 15 + $i, self::bit($bits, $i));
        }

        self::setFungsi($modules, $fungsi, 8, self::UKURAN - 8, true);
    }

    private static function tambahBits(array &$bits, int $nilai, int $panjang): void
    {
        for ($i = $panjang - 1; $i >= 0; $i--) {
            $bits[] = (($nilai >> $i) & 1) !== 0;
        }
    }

    private static function setFungsi(array &$modules, array &$fungsi, int $x, int $y, bool $gelap): void
    {
        $modules[$y][$x] = $gelap;
        $fungsi[$y][$x] = true;
    }

    private static function bit(int $nilai, int $index): bool
    {
        return (($nilai >> $index) & 1) !== 0;
    }

    private static function mask(int $x, int $y): bool
    {
        return (($x + $y) % 2) === 0;
    }

    private static function gfMultiply(int $x, int $y): int
    {
        $hasil = 0;

        while ($y > 0) {
            if (($y & 1) !== 0) {
                $hasil ^= $x;
            }

            $y >>= 1;
            $x <<= 1;

            if (($x & 0x100) !== 0) {
                $x ^= 0x11d;
            }
        }

        return $hasil & 0xff;
    }
}
