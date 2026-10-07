<?php

namespace App\Console\Commands;

use App\Services\Pembinaan\PoinPresensiOtomatisService;
use Illuminate\Console\Command;

class ProsesPoinPresensi extends Command
{
    protected $signature = 'pembinaan:proses-poin-presensi';

    protected $description = 'Menetapkan poin terlambat dan alfa sejak tanggal aktivasi, termasuk hari yang terlewat scheduler';

    public function handle(PoinPresensiOtomatisService $service): int
    {
        $jumlah = $service->prosesSemua();
        $this->components->info($jumlah.' kejadian presensi baru ditetapkan otomatis.');

        return self::SUCCESS;
    }
}
