<?php

namespace Tests\Feature;

use App\Services\Sistem\CadanganDatabaseService;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDO;
use ReflectionMethod;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CadanganDatabasePostgresTest extends TestCase
{
    public function test_cadangan_baru_tanpa_isi_cache_dapat_dipulihkan(): void
    {
        if (getenv('NUSA_TEST_PG_BACKUP') !== '1') {
            $this->markTestSkipped('Isi NUSA_TEST_PG_BACKUP=1 untuk pengujian PostgreSQL terisolasi.');
        }

        $konfigurasi = config('database.connections.pgsql');
        $this->assertContains($konfigurasi['host'], ['127.0.0.1', 'localhost', '::1']);
        $this->assertEmpty($konfigurasi['url'] ?? null, 'Pengujian tidak menerima DB_URL.');

        $koneksiDefault = config('database.default');
        $dsn = 'pgsql:host='.$konfigurasi['host'].';port='.$konfigurasi['port'].';dbname=';
        $admin = new PDO($dsn.'postgres', $konfigurasi['username'], $konfigurasi['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $penanda = bin2hex(random_bytes(8));
        $sumber = 'nusa_backup_qa_'.$penanda;
        $tujuan = 'nusa_restore_qa_'.$penanda;
        $dibuat = [];
        $target = null;
        $disk = Storage::fake('local');

        try {
            foreach ([$sumber, $tujuan] as $namaDatabase) {
                $admin->exec('CREATE DATABASE "'.$namaDatabase.'" TEMPLATE template0');
                $dibuat[] = $namaDatabase;
            }

            config(['database.default' => 'pgsql', 'database.connections.pgsql.database' => $sumber]);
            DB::purge('pgsql');
            $this->assertSame($sumber, DB::selectOne('SELECT current_database() AS nama')->nama);
            $this->assertSame(0, Artisan::call('migrate', ['--database' => 'pgsql', '--force' => true]), Artisan::output());

            DB::table('siswa')->insert(['nama_lengkap' => 'Siswa QA Backup']);
            DB::table('cache')->insert(['key' => 'monitoring-tv:v3:siswa', 'value' => 'snapshot-uji', 'expiration' => now()->timestamp]);
            DB::table('cache_locks')->insert(['key' => 'lock-uji', 'owner' => 'uji', 'expiration' => now()->timestamp]);
            DB::table('sessions')->insert(['id' => 'sesi-uji', 'payload' => 'sesi', 'last_activity' => now()->timestamp]);
            DB::table('jobs')->insert(['queue' => 'uji', 'payload' => '{}', 'attempts' => 0, 'available_at' => now()->timestamp, 'created_at' => now()->timestamp]);

            $service = app(CadanganDatabaseService::class);
            $cadangan = $service->buatCadangan();
            $this->assertTrue($cadangan['valid']);
            $this->assertTrue($cadangan['tabel_terverifikasi']);
            $this->assertSame(1, DB::table('cache')->count(), 'Backup tidak menghapus cache pada database sumber.');

            $koneksiTujuan = array_replace($konfigurasi, ['database' => $tujuan]);
            $target = new PDO($dsn.$tujuan, $konfigurasi['username'], $konfigurasi['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            (new ReflectionMethod($service, 'pulihkanSchemaPublik'))
                ->invoke($service, $disk->path($cadangan['lokasi']), $service->status(), $koneksiTujuan);

            $tabel = $target->query("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public' ORDER BY tablename")
                ->fetchAll(PDO::FETCH_COLUMN);
            $manifest = json_decode($disk->get($cadangan['lokasi'].'.json'), true, flags: JSON_THROW_ON_ERROR);
            $this->assertSame($manifest['tabel'], $tabel);
            $this->assertSame(0, (int) $target->query('SELECT COUNT(*) FROM public.cache')->fetchColumn());
            $this->assertSame('Siswa QA Backup', $target->query('SELECT nama_lengkap FROM public.siswa')->fetchColumn());
            $this->assertSame('lock-uji', $target->query('SELECT key FROM public.cache_locks')->fetchColumn());
            $this->assertSame('sesi-uji', $target->query('SELECT id FROM public.sessions')->fetchColumn());
            $this->assertSame('uji', $target->query('SELECT queue FROM public.jobs')->fetchColumn());
        } finally {
            $target = null;
            DB::purge('pgsql');
            config(['database.default' => $koneksiDefault, 'database.connections.pgsql' => $konfigurasi]);
            foreach ($dibuat as $namaDatabase) {
                $admin->exec('DROP DATABASE "'.$namaDatabase.'"');
            }
            $disk->delete($disk->allFiles());
        }
    }

    public function test_custom_dump_dapat_dipulihkan_berulang_dan_kegagalan_tetap_atomik(): void
    {
        // Opt-in only: NUSA_TEST_PG_DUMP=/path/cadangan.dump php artisan test --filter=CadanganDatabasePostgresTest
        $path = getenv('NUSA_TEST_PG_DUMP');

        if (! $path) {
            $this->markTestSkipped('Isi NUSA_TEST_PG_DUMP untuk pengujian PostgreSQL terisolasi.');
        }

        $this->assertFileExists($path);
        $konfigurasi = config('database.connections.pgsql');
        $this->assertContains($konfigurasi['host'], ['127.0.0.1', 'localhost', '::1']);
        $this->assertEmpty($konfigurasi['url'] ?? null, 'Pengujian tidak menerima DB_URL.');
        $service = app(CadanganDatabaseService::class);
        $status = $service->status();
        $this->assertNotEmpty($status['pg_dump']);
        $this->assertNotEmpty($status['pg_restore']);
        $this->assertNotEmpty($status['psql']);
        Storage::fake('local');
        Storage::disk('local')->makeDirectory(config('cadangan_database.direktori'));

        $namaDatabase = 'nusa_restore_qa_'.bin2hex(random_bytes(8));
        $this->assertMatchesRegularExpression('/\Anusa_restore_qa_[a-f0-9]{16}\z/', $namaDatabase);
        $dsn = 'pgsql:host='.$konfigurasi['host'].';port='.$konfigurasi['port'].';dbname=';
        $admin = new PDO($dsn.'postgres', $konfigurasi['username'], $konfigurasi['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $dibuat = false;
        $target = null;

        try {
            $admin->exec('CREATE DATABASE "'.$namaDatabase.'" TEMPLATE template0');
            $dibuat = true;
            $target = new PDO($dsn.$namaDatabase, $konfigurasi['username'], $konfigurasi['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
            $target->exec('CREATE TABLE public.qa_sebelum_restore (id integer)');
            $ringkasan = (new ReflectionMethod($service, 'pastikanCadanganNusa'))
                ->invoke($service, $path, $status['pg_restore']);
            $koneksi = [
                'host' => $konfigurasi['host'],
                'port' => $konfigurasi['port'],
                'database' => $namaDatabase,
                'username' => $konfigurasi['username'],
                'password' => $konfigurasi['password'],
                'sslmode' => $konfigurasi['sslmode'] ?? 'prefer',
            ];
            $pulihkan = new ReflectionMethod($service, 'pulihkanSchemaPublik');

            for ($percobaan = 0; $percobaan < 2; $percobaan++) {
                $pulihkan->invoke($service, $path, $status, $koneksi);
                $tabel = $target->query("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public' ORDER BY tablename")
                    ->fetchAll(PDO::FETCH_COLUMN);

                $this->assertSame($ringkasan['tabel'], $tabel);
                $this->assertNotContains('qa_sebelum_restore', $tabel);
                $this->assertNotEmpty($target->query('SELECT id FROM public.migrations LIMIT 1')->fetchColumn());
                $this->assertSame([], Storage::disk('local')->allFiles(), 'Berkas SQL sementara harus dihapus.');
            }

            // Data tidak konsisten hanya dibuat di database QA untuk memaksa kegagalan setelah COPY.
            $target->exec('CREATE TABLE public.qa_restore_induk (id integer PRIMARY KEY)');
            $target->exec('CREATE TABLE public.qa_restore_anak (induk_id integer REFERENCES public.qa_restore_induk(id))');
            $target->exec('ALTER TABLE public.qa_restore_anak DISABLE TRIGGER ALL');
            $target->exec('INSERT INTO public.qa_restore_anak (induk_id) VALUES (987654)');
            $target->exec('ALTER TABLE public.qa_restore_anak ENABLE TRIGGER ALL');
            $lokasiTidakKonsisten = 'cadangan-tidak-konsisten.dump';
            $pathTidakKonsisten = Storage::disk('local')->path($lokasiTidakKonsisten);
            $dump = new Process([
                $status['pg_dump'], '--format=custom', '--no-owner', '--no-privileges',
                '--table=public.qa_restore_induk', '--table=public.qa_restore_anak',
                '--host='.$koneksi['host'], '--port='.(string) $koneksi['port'],
                '--username='.$koneksi['username'], '--file='.$pathTidakKonsisten, $namaDatabase,
            ], base_path(), ['PGPASSWORD' => $koneksi['password']]);
            $dump->mustRun();

            try {
                $pulihkan->invoke($service, $pathTidakKonsisten, $status, $koneksi);
                $this->fail('Constraint yang gagal harus membatalkan seluruh transaksi restore.');
            } catch (RuntimeException $exception) {
                $this->assertStringContainsString('violates foreign key constraint', $exception->getMessage());
                $this->assertSame(987654, (int) $target->query('SELECT induk_id FROM public.qa_restore_anak')->fetchColumn());
                $tabelSetelahGagal = $target->query("SELECT tablename FROM pg_catalog.pg_tables WHERE schemaname = 'public' ORDER BY tablename")
                    ->fetchAll(PDO::FETCH_COLUMN);
                $this->assertSame([], array_diff($ringkasan['tabel'], $tabelSetelahGagal));
            } finally {
                Storage::disk('local')->delete($lokasiTidakKonsisten);
            }

            $this->assertSame([], Storage::disk('local')->allFiles());
        } finally {
            $target = null;

            if ($dibuat) {
                $admin->exec('DROP DATABASE "'.$namaDatabase.'"');
            }
        }
    }
}
