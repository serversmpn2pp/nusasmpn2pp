<?php

namespace App\Console\Commands;

use App\Services\Monitoring\CacheMonitoringTv;
use Illuminate\Cache\DatabaseStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class BersihkanCacheMonitoring extends Command
{
    protected $signature = 'nusa:bersihkan-cache-monitoring
        {--awalan= : Awalan kunci dari instalasi asal cadangan, bila berbeda}
        {--hapus : Hapus entri monitoring yang sudah kedaluwarsa}';

    protected $description = 'Pratinjau atau bersihkan cache Monitoring TV yang kedaluwarsa di database';

    private const POLA_LAMA = '/\A(?:'
        .'monitor-tv:v[12]:[0-9]{8}:[0-9]+'
        .'|monitor-ujian:v[12]:[0-9]+:[0-9]+:[0-9]+:[0-9]+'
        .'|monitor-pameran:v[12]:[0-9]+:[0-9]{4}-[0-9]{2}-[0-9]{2}:[0-9]+'
        .'|monitor-pameran-tamu:v[12]:[0-9]+:[0-9]+'
        .')\z/';

    public function handle(): int
    {
        $store = Cache::store('database')->getStore();

        if (! $store instanceof DatabaseStore) {
            $this->error('Penyimpanan cache database tidak tersedia.');

            return self::FAILURE;
        }

        $awalan = $this->option('awalan');
        if ($this->input->hasParameterOption('--awalan') && ($awalan === null || trim($awalan) === '')) {
            $this->error('Isi --awalan dengan awalan kunci yang persis dari instalasi asal.');

            return self::FAILURE;
        }

        $prefix = $awalan ?? $store->getPrefix();
        $hapus = (bool) $this->option('hapus');
        $kedaluwarsa = $store->getConnection()
            ->table(config('cache.stores.database.table'))
            ->where('expiration', '<=', now()->timestamp);
        $jumlah = 0;

        // Baca kunci saja dan periksa kembali masa berlaku saat menghapus per kelompok.
        (clone $kedaluwarsa)->select('key')->chunkById(500, function ($baris) use ($prefix, $hapus, $kedaluwarsa, &$jumlah) {
            $kunci = $baris->pluck('key')->filter(function (string $key) use ($prefix) {
                if (! str_starts_with($key, $prefix)) {
                    return false;
                }

                $nama = substr($key, strlen($prefix));

                return str_starts_with($nama, CacheMonitoringTv::PREFIX) || preg_match(self::POLA_LAMA, $nama) === 1;
            })->all();

            $jumlah += $hapus && $kunci !== []
                ? (clone $kedaluwarsa)->whereIn('key', $kunci)->delete()
                : count($kunci);
        }, 'key');

        $this->line('Awalan cache: '.($prefix === '' ? '(tanpa awalan)' : $prefix));
        $this->info($hapus
            ? "{$jumlah} entri cache Monitoring TV kedaluwarsa dihapus."
            : "Pratinjau: {$jumlah} entri cache Monitoring TV kedaluwarsa. Tidak ada data yang diubah.");

        return self::SUCCESS;
    }
}
