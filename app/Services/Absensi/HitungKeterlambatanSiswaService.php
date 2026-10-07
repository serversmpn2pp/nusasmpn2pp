<?php

namespace App\Services\Absensi;

class HitungKeterlambatanSiswaService
{
    public function menit(string $jam, string $batas, bool $ketat): int
    {
        if (! $ketat) {
            return max(0, intdiv($this->detik($jam), 60) - intdiv($this->detik($batas), 60));
        }

        return (int) ceil(max(0, $this->detik($jam) - $this->detik($batas)) / 60);
    }

    private function detik(string $jam): int
    {
        $bagian = array_map('intval', explode(':', $jam));

        return $bagian[0] * 3600 + ($bagian[1] ?? 0) * 60 + ($bagian[2] ?? 0);
    }
}
