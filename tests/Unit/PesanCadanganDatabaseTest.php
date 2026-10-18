<?php

namespace Tests\Unit;

use App\Services\Sistem\CadanganDatabaseService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Symfony\Component\Process\Process;

class PesanCadanganDatabaseTest extends TestCase
{
    public function test_error_utama_tidak_terpotong_oleh_notice_drop_schema(): void
    {
        $stderr = "psql:reset.sql:1: NOTICE: drop cascades to 218 other objects\r\n";
        $stderr .= str_repeat("DETAIL: drop cascades to table contoh\r\n", 218);
        $stderr .= "psql:pemulihan.sql:31: ERROR: schema \"public\" does not exist\r\n";
        $stderr .= "LINE 1: CREATE TABLE public.contoh (id bigint);\r\n";

        $pesan = $this->pesan($stderr);

        $this->assertStringContainsString('ERROR: schema "public" does not exist', $pesan);
        $this->assertStringContainsString('LINE 1: CREATE TABLE', $pesan);
        $this->assertStringNotContainsString('drop cascades', $pesan);
    }

    public function test_fatal_koneksi_dan_error_pg_restore_tetap_ditampilkan(): void
    {
        foreach ([
            'psql: error: connection failed: FATAL: password authentication failed',
            'pg_restore: error: unsupported version in file header',
        ] as $detail) {
            $this->assertSame('Pemulihan database gagal. '.$detail, $this->pesan($detail));
        }
    }

    public function test_pesan_tanpa_penanda_error_dan_output_cadangan_tetap_tersedia(): void
    {
        $this->assertSame('Pemulihan database gagal. Access is denied.', $this->pesan('Access is denied.'));
        $this->assertSame('Pemulihan database gagal. Proses tidak selesai.', $this->pesan('', 'Proses tidak selesai.'));
        $this->assertStringContainsString('Periksa koneksi dan konfigurasi PostgreSQL.', $this->pesan(''));
    }

    public function test_detail_error_tetap_dibatasi_panjangnya(): void
    {
        $pesan = $this->pesan('psql: ERROR: '.str_repeat('x', 2000));

        $this->assertStringStartsWith('Pemulihan database gagal. psql: ERROR: ', $pesan);
        $this->assertLessThan(950, strlen($pesan));
    }

    private function pesan(string $stderr, string $stdout = ''): string
    {
        $process = $this->createMock(Process::class);
        $process->method('getErrorOutput')->willReturn($stderr);
        $process->method('getOutput')->willReturn($stdout);

        return (new ReflectionMethod(CadanganDatabaseService::class, 'pesanProsesGagal'))
            ->invoke(new CadanganDatabaseService, 'Pemulihan database', $process);
    }
}
