<?php

namespace App\Services\Omr;

use RuntimeException;
use Symfony\Component\Process\Process;

class PembacaPdfLjkOmr
{
    public function baca(string $lokasiPdf, string $direktoriPratinjau, int $jumlahSoal = 50): array
    {
        $process = new Process([
            config('omr.node_binary', 'node'),
            base_path('scripts/omr/baca-ljk-pdf.mjs'),
            $lokasiPdf,
            $direktoriPratinjau,
            (string) $jumlahSoal,
        ]);
        $process->setTimeout(config('omr.timeout_detik', 300));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException(
                'Mesin pembaca OMR gagal memproses PDF. '
                . trim($process->getErrorOutput() ?: $process->getOutput()),
            );
        }

        $hasil = json_decode($process->getOutput(), true);

        if (! is_array($hasil) || ! isset($hasil['pages'], $hasil['sheets']) || ! is_array($hasil['sheets'])) {
            throw new RuntimeException('Mesin pembaca OMR mengembalikan hasil yang tidak dikenali.');
        }

        return $hasil;
    }
}
