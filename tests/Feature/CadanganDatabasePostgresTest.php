<?php

namespace Tests\Feature;

use App\Services\Sistem\CadanganDatabaseService;
use Illuminate\Support\Facades\Storage;
use PDO;
use ReflectionMethod;
use RuntimeException;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CadanganDatabasePostgresTest extends TestCase
{
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
