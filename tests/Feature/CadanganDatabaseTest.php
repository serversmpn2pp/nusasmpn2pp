<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use App\Services\Sistem\CadanganDatabaseService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class CadanganDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_dapat_melihat_halaman_cadangan_database(): void
    {
        $administrator = Pengguna::query()->where('username', 'administrator')->firstOrFail();
        $service = $this->mockServiceDasar();

        $service->shouldReceive('daftarCadangan')->once()->andReturn(collect([
            [
                'nama_file' => 'nusa-db-20260822-010000.dump',
                'lokasi' => 'cadangan-database/berkas/nusa-db-20260822-010000.dump',
                'jenis' => 'Manual',
                'ukuran' => 1024,
                'ukuran_label' => '1,0 KB',
                'waktu' => Carbon::parse('2026-08-22 01:00:00'),
                'valid' => true,
            ],
        ]));

        $this->actingAs($administrator)
            ->get(route('cadangan-database.index'))
            ->assertOk()
            ->assertSee('Backup &amp; Restore Database', false)
            ->assertSee('nusa-db-20260822-010000.dump')
            ->assertSee('Buat backup sekarang')
            ->assertSee('Pulihkan dari perangkat')
            ->assertSee('Cadangan ini mencakup seluruh data yang tersimpan di PostgreSQL', false);
    }

    public function test_administrator_dapat_membuat_cadangan_manual(): void
    {
        $administrator = Pengguna::query()->where('username', 'administrator')->firstOrFail();
        $service = $this->mock(CadanganDatabaseService::class, function (MockInterface $mock) use ($administrator) {
            $mock->shouldReceive('buatCadangan')
                ->once()
                ->with('manual', $administrator)
                ->andReturn([
                    'nama_file' => 'nusa-db-20260822-020000.dump',
                    'ukuran_label' => '640,0 KB',
                ]);
        });

        $this->actingAs($administrator)
            ->post(route('cadangan-database.store'))
            ->assertRedirect()
            ->assertSessionHas('berhasil', fn (string $pesan) => str_contains($pesan, 'nusa-db-20260822-020000.dump'));
    }

    public function test_restore_memerlukan_password_dan_konfirmasi_yang_tepat(): void
    {
        $administrator = Pengguna::query()->where('username', 'administrator')->firstOrFail();
        $service = $this->mock(CadanganDatabaseService::class);
        $service->shouldNotReceive('pulihkan');

        $this->actingAs($administrator)
            ->post(route('cadangan-database.restore', 'nusa-db-20260822-010000.dump'), [
                'kata_sandi' => 'password-salah',
                'konfirmasi' => 'PULIHKAN',
            ])
            ->assertSessionHasErrors('kata_sandi');

        $this->actingAs($administrator)
            ->post(route('cadangan-database.restore', 'nusa-db-20260822-010000.dump'), [
                'kata_sandi' => 'administrator',
                'konfirmasi' => 'pulihkan',
            ])
            ->assertSessionHasErrors('konfirmasi');
    }

    public function test_restore_yang_dikonfirmasi_memanggil_service_pemulihan(): void
    {
        $administrator = Pengguna::query()->where('username', 'administrator')->firstOrFail();
        $namaFile = 'nusa-db-20260822-010000.dump';
        $service = $this->mock(CadanganDatabaseService::class, function (MockInterface $mock) use ($administrator, $namaFile) {
            $mock->shouldReceive('pulihkan')
                ->once()
                ->with($namaFile, $administrator)
                ->andReturn([
                    'cadangan' => ['nama_file' => $namaFile],
                    'cadangan_pengaman' => ['nama_file' => 'nusa-db-pra-pemulihan-20260822-020000.dump'],
                ]);
        });

        $this->actingAs($administrator)
            ->post(route('cadangan-database.restore', $namaFile), [
                'kata_sandi' => 'administrator',
                'konfirmasi' => 'PULIHKAN',
            ])
            ->assertRedirect(route('cadangan-database.index'))
            ->assertSessionHas('berhasil');
    }

    public function test_administrator_dapat_mengunggah_cadangan_untuk_dipulihkan(): void
    {
        $administrator = Pengguna::query()->where('username', 'administrator')->firstOrFail();
        $namaFile = 'nusa-db-unggahan-20260822-030000-abcdef.dump';
        $service = $this->mock(CadanganDatabaseService::class, function (MockInterface $mock) use ($administrator, $namaFile) {
            $mock->shouldReceive('batasUnggah')->once()->andReturn([
                'byte' => 10 * 1024 * 1024,
                'kilobyte' => 10 * 1024,
                'label' => '10,00 MB',
            ]);
            $mock->shouldReceive('simpanUnggahan')
                ->once()
                ->withArgs(fn ($berkas, $pengguna) => $berkas instanceof UploadedFile && $pengguna->is($administrator))
                ->andReturn(['nama_file' => $namaFile]);
            $mock->shouldReceive('pulihkan')
                ->once()
                ->with($namaFile, $administrator)
                ->andReturn([
                    'cadangan' => ['nama_file' => $namaFile],
                    'cadangan_pengaman' => ['nama_file' => 'nusa-db-pra-pemulihan-20260822-030001.dump'],
                ]);
        });

        $this->actingAs($administrator)
            ->post(route('cadangan-database.restore-upload'), [
                'berkas_cadangan' => UploadedFile::fake()->createWithContent('cadangan.dump', 'PGDMP data-uji'),
                'kata_sandi' => 'administrator',
                'konfirmasi' => 'PULIHKAN',
            ])
            ->assertRedirect(route('cadangan-database.index'))
            ->assertSessionHas('berhasil');
    }

    public function test_pengguna_tanpa_izin_tidak_dapat_membuka_halaman_cadangan(): void
    {
        $pengguna = Pengguna::create([
            'nama' => 'Pegawai Tanpa Izin Backup',
            'username' => 'tanpa-izin-backup',
            'kata_sandi' => 'password-uji',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);

        $this->actingAs($pengguna)
            ->get(route('cadangan-database.index'))
            ->assertForbidden();
    }

    public function test_service_hanya_menerima_unggahan_custom_dump_postgresql(): void
    {
        Storage::fake('local');
        $service = app(CadanganDatabaseService::class);

        $cadangan = $service->simpanUnggahan(
            UploadedFile::fake()->createWithContent('cadangan.dump', 'PGDMP data-uji-valid'),
        );

        Storage::disk('local')->assertExists($cadangan['lokasi']);
        $this->assertTrue($cadangan['valid']);

        $this->expectException(RuntimeException::class);
        $service->simpanUnggahan(
            UploadedFile::fake()->createWithContent('bukan-cadangan.dump', 'isi tidak valid'),
        );
    }

    private function mockServiceDasar(): CadanganDatabaseService&MockInterface
    {
        return $this->mock(CadanganDatabaseService::class, function (MockInterface $mock) {
            $mock->shouldReceive('status')->once()->andReturn([
                'driver' => 'pgsql',
                'database' => 'nusa',
                'pg_dump' => 'C:\\PostgreSQL\\bin\\pg_dump.exe',
                'pg_restore' => 'C:\\PostgreSQL\\bin\\pg_restore.exe',
                'siap_backup' => true,
                'siap_restore' => true,
                'otomatis_aktif' => true,
                'jadwal_otomatis' => '01:00',
                'retensi_hari' => 30,
            ]);
            $mock->shouldReceive('daftarAktivitas')->once()->andReturn(new Collection);
            $mock->shouldReceive('batasUnggah')->once()->andReturn([
                'byte' => 10 * 1024 * 1024,
                'kilobyte' => 10 * 1024,
                'label' => '10,00 MB',
            ]);
        });
    }
}
