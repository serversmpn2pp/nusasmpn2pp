<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BersihkanCacheMonitoringTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cache.stores.database' => ['driver' => 'database', 'table' => 'cache']]);
    }

    public function test_pratinjau_dan_hapus_terbatas_pada_cache_monitoring_kedaluwarsa_milik_nusa(): void
    {
        config(['cache.prefix' => 'nusa_qa%-']);
        $prefix = config('cache.prefix');
        $kedaluwarsa = [
            $prefix.'monitor-tv:v1:20261110:1234',
            $prefix.'monitor-tv:v2:20261111:1234',
            $prefix.'monitor-ujian:v2:1:2:3:1234',
            $prefix.'monitor-pameran:v1:1:2026-10-29:1234',
            $prefix.'monitor-pameran-tamu:v2:4:1234',
            $prefix.'monitoring-tv:v3:ujian:1:2:3',
        ];
        $lain = [
            'nusaXqa-monitor-tv:v2:20261111:1234',
            $prefix.'monitor-tv:rate-limiter',
            $prefix.'monitor-tv:v2:20261111:bukan-snapshot',
            $prefix.'illuminate:queue:restart',
            $prefix.'monitoring-tv:v4:siswa',
        ];
        $aktif = $prefix.'monitoring-tv:v3:siswa';
        foreach ([...$kedaluwarsa, ...$lain, $aktif] as $kunci) {
            DB::table('cache')->insert([
                'key' => $kunci,
                'value' => 'data-uji',
                'expiration' => now()->timestamp + ($kunci === $aktif ? 60 : -1),
            ]);
        }

        $this->artisan('nusa:bersihkan-cache-monitoring')
            ->expectsOutput('Pratinjau: 6 entri cache Monitoring TV kedaluwarsa. Tidak ada data yang diubah.')
            ->assertSuccessful();
        $this->assertDatabaseCount('cache', 12);

        $this->artisan('nusa:bersihkan-cache-monitoring', ['--hapus' => true])
            ->expectsOutput('6 entri cache Monitoring TV kedaluwarsa dihapus.')
            ->assertSuccessful();
        $this->assertEqualsCanonicalizing([...$lain, $aktif], DB::table('cache')->pluck('key')->all());

        $this->artisan('nusa:bersihkan-cache-monitoring', ['--hapus' => true])
            ->expectsOutput('0 entri cache Monitoring TV kedaluwarsa dihapus.')
            ->assertSuccessful();
    }

    public function test_awalan_cadangan_asal_harus_eksplisit_dan_hapus_bekerja_per_kelompok(): void
    {
        config(['cache.prefix' => 'lokal-cache-']);
        $asal = 'server-cache-';
        $kedaluwarsa = now()->timestamp;
        DB::table('cache')->insert([
            ['key' => 'lokal-cache-monitor-tv:v2:20261111:9', 'value' => 'lokal', 'expiration' => $kedaluwarsa],
            ['key' => $asal.'monitoring-tv:v3:siswa', 'value' => 'aktif', 'expiration' => $kedaluwarsa + 60],
        ]);
        foreach (array_chunk(range(1, 502), 100) as $kelompok) {
            DB::table('cache')->insert(array_map(fn (int $i) => [
                'key' => $asal.'monitor-tv:v2:20261111:'.$i,
                'value' => 'lama',
                'expiration' => $kedaluwarsa,
            ], $kelompok));
        }

        $this->artisan('nusa:bersihkan-cache-monitoring')
            ->expectsOutput('Pratinjau: 1 entri cache Monitoring TV kedaluwarsa. Tidak ada data yang diubah.')
            ->assertSuccessful();
        $this->artisan('nusa:bersihkan-cache-monitoring', ['--awalan' => $asal])
            ->expectsOutput('Pratinjau: 502 entri cache Monitoring TV kedaluwarsa. Tidak ada data yang diubah.')
            ->assertSuccessful();
        $this->assertDatabaseCount('cache', 504);

        $this->artisan('nusa:bersihkan-cache-monitoring', ['--awalan' => $asal, '--hapus' => true])
            ->expectsOutput('502 entri cache Monitoring TV kedaluwarsa dihapus.')
            ->assertSuccessful();
        $this->assertEqualsCanonicalizing(
            ['lokal-cache-monitor-tv:v2:20261111:9', $asal.'monitoring-tv:v3:siswa'],
            DB::table('cache')->pluck('key')->all(),
        );
    }
}
