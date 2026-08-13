<?php

namespace Tests\Unit;

use App\Support\PenulisExcelLaporanAbsensi;
use stdClass;
use Tests\TestCase;
use ZipArchive;

class PenulisExcelLaporanAbsensiTest extends TestCase
{
    public function test_penulis_excel_laporan_absensi_membuat_file_xlsx(): void
    {
        $penulis = app(PenulisExcelLaporanAbsensi::class);
        $lokasiBerkas = $penulis->buat([
            'tahunPelajaran' => $this->objek(['nama' => '2025/2026']),
            'kelasDipilih' => $this->objek(['nama' => 'VII.A']),
            'labelPeriode' => '20 Mei 2026',
            'jumlahHariEfektif' => 1,
            'ringkasan' => [
                'siswa' => 1,
                'hari_efektif' => 1,
                'hadir' => 1,
                'izin' => 0,
                'sakit' => 0,
                'alfa' => 0,
                'terlambat' => 1,
                'menit_terlambat' => 2,
                'pulang_cepat' => 1,
                'menit_pulang_cepat' => 5,
                'rata_persentase_hadir' => 100,
            ],
            'laporanAbsensi' => collect([
                [
                    'anggota_kelas' => $this->objek([
                        'nomor_absen' => 7,
                        'kelas' => $this->objek(['nama' => 'VII.A']),
                        'siswa' => $this->objek([
                            'nama_lengkap' => 'Siswa Contoh',
                            'nis' => '12345',
                            'nisn' => '9876543210',
                        ]),
                    ]),
                    'hari_efektif' => 1,
                    'hadir' => 1,
                    'izin' => 0,
                    'sakit' => 0,
                    'alfa' => 0,
                    'terlambat' => 1,
                    'menit_terlambat' => 2.4,
                    'pulang_cepat' => 1,
                    'menit_pulang_cepat' => 5.2,
                    'persentase_hadir' => 100,
                ],
            ]),
        ]);

        try {
            $this->assertFileExists($lokasiBerkas);
            $this->assertStringStartsWith('laporan-presensi-', basename($lokasiBerkas));

            $zip = new ZipArchive();
            $this->assertTrue($zip->open($lokasiBerkas));

            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            $workbook = $zip->getFromName('xl/workbook.xml');
            $metadata = $zip->getFromName('docProps/core.xml');
            $zip->close();

            $this->assertIsString($sheet);
            $this->assertStringContainsString('LAPORAN PRESENSI', $sheet);
            $this->assertStringContainsString('Laporan Presensi', $workbook);
            $this->assertStringContainsString('Laporan Presensi NUSA', $metadata);
            $this->assertStringContainsString('Siswa Contoh', $sheet);
            $this->assertStringContainsString('9876543210', $sheet);
            $this->assertStringContainsString('<v>2</v>', $sheet);
            $this->assertStringContainsString('<v>5</v>', $sheet);
        } finally {
            if (is_file($lokasiBerkas)) {
                unlink($lokasiBerkas);
            }
        }
    }

    private function objek(array $atribut): stdClass
    {
        $objek = new stdClass();

        foreach ($atribut as $kunci => $nilai) {
            $objek->{$kunci} = $nilai;
        }

        return $objek;
    }
}
