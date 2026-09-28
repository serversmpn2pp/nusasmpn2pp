<?php

namespace Tests\Unit;

use App\Support\PenulisExcelHasilUjianCbt;
use Carbon\Carbon;
use stdClass;
use Tests\TestCase;
use ZipArchive;

class PenulisExcelHasilUjianCbtTest extends TestCase
{
    public function test_penulis_membuat_xlsx_hasil_ujian_per_kelas(): void
    {
        $ujian = new class
        {
            public string $nama = 'Sumatif Tengah Semester Matematika';

            public int $kkm = 75;

            public object $mataPelajaran;

            public object $tahunPelajaran;

            public function labelWaktu(): string
            {
                return '15-09-2026 07:30 sampai 15-09-2026 09:00';
            }
        };
        $ujian->mataPelajaran = $this->objek(['nama' => 'Matematika']);
        $ujian->tahunPelajaran = $this->objek(['nama' => '2026/2027']);
        $peserta = new class
        {
            public object $anggotaKelas;

            public object $kelasUjianCbt;

            public Carbon $waktu_mulai;

            public Carbon $waktu_selesai;

            public string $cara_selesai = 'manual';

            public function labelStatusKehadiranUjian(): string
            {
                return 'Hadir';
            }
        };
        $peserta->anggotaKelas = $this->objek([
            'nomor_absen' => 7,
            'siswa' => $this->objek([
                'nama_lengkap' => 'Siswa Contoh',
                'nis' => '12345',
                'nisn' => '9876543210',
            ]),
        ]);
        $peserta->kelasUjianCbt = $this->objek(['kelas' => $this->objek(['nama' => 'VII.A'])]);
        $peserta->waktu_mulai = Carbon::parse('2026-09-15 07:30:00');
        $peserta->waktu_selesai = Carbon::parse('2026-09-15 08:15:00');
        $kelasUjian = $this->objek(['kelas' => $this->objek(['nama' => 'VII.A'])]);

        $lokasiBerkas = app(PenulisExcelHasilUjianCbt::class)->buat([
            'ujianCbt' => $ujian,
            'kelasUjian' => $kelasUjian,
            'jumlahSoalTampil' => 20,
            'bobotTotal' => 20,
            'ringkasan' => [
                'total_peserta' => 1,
                'hasil_final' => 1,
                'rata_rata' => 85,
                'nilai_tertinggi' => 85,
                'nilai_terendah' => 85,
                'tuntas' => 1,
                'belum_tuntas' => 0,
                'belum_mengikuti' => 0,
            ],
            'rekapSemua' => collect([[
                'peserta' => $peserta,
                'kode_status_hasil' => 'tuntas',
                'label_status_hasil' => 'Tuntas',
                'jawaban_tersimpan' => 20,
                'benar' => 17,
                'salah' => 3,
                'belum_jawab' => 0,
                'skor_total' => 17,
                'nilai_tersedia' => true,
                'nilai' => 85,
            ]]),
        ]);

        try {
            $this->assertFileExists($lokasiBerkas);
            $this->assertStringStartsWith('hasil-ujian-cbt-', basename($lokasiBerkas));

            $zip = new ZipArchive;
            $this->assertTrue($zip->open($lokasiBerkas));

            $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
            $workbook = $zip->getFromName('xl/workbook.xml');
            $metadata = $zip->getFromName('docProps/core.xml');
            $zip->close();

            $this->assertIsString($sheet);
            $this->assertStringContainsString('HASIL UJIAN CBT', $sheet);
            $this->assertStringContainsString('Matematika', $sheet);
            $this->assertStringContainsString('VII.A', $sheet);
            $this->assertStringContainsString('Siswa Contoh', $sheet);
            $this->assertStringContainsString('9876543210', $sheet);
            $this->assertStringContainsString('<autoFilter ref="A10:R11"/>', $sheet);
            $this->assertStringContainsString('<v>85</v>', $sheet);
            $this->assertStringContainsString('Hasil VII.A', $workbook);
            $this->assertStringContainsString('Hasil Ujian CBT NUSA', $metadata);
        } finally {
            if (is_file($lokasiBerkas)) {
                unlink($lokasiBerkas);
            }
        }
    }

    private function objek(array $atribut): stdClass
    {
        $objek = new stdClass;

        foreach ($atribut as $kunci => $nilai) {
            $objek->{$kunci} = $nilai;
        }

        return $objek;
    }
}
