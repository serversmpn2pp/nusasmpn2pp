<?php

namespace Tests\Unit;

use App\Models\SoalCbt;
use App\Models\SoalUjianCbt;
use App\Services\Cbt\RincianPilihanSoalCbt;
use PHPUnit\Framework\TestCase;

class RincianPilihanSoalCbtTest extends TestCase
{
    public function test_opsi_format_lama_dan_baru_memiliki_kunci_dan_isi_yang_sama(): void
    {
        $layanan = new RincianPilihanSoalCbt;
        foreach ([
            [['pilihan' => ['A' => 'Satu', 'B' => 'Dua']], ['jawaban' => ' b ']],
            [[['kode' => 'a', 'teks' => 'Satu'], ['kode' => 'b', 'label' => 'Dua']], ['B']],
        ] as [$opsi, $kunci]) {
            $hasil = $layanan->hitung($this->soal($opsi, $kunci), ['A' => 2, 'B' => 8], 10);

            $this->assertTrue($hasil['kunci_valid']);
            $this->assertSame(['A', 'B'], array_column($hasil['opsi'], 'kode'));
            $this->assertSame(['Satu', 'Dua'], array_column($hasil['opsi'], 'teks'));
            $this->assertSame([false, true], array_column($hasil['opsi'], 'kunci'));
            $this->assertSame([20.0, 80.0], array_column($hasil['opsi'], 'persen'));
        }
    }

    public function test_pengecoh_ditinjau_di_bawah_lima_persen_sebelum_pembulatan(): void
    {
        $soal = $this->soal(['A' => 'Satu', 'B' => 'Dua', 'C' => 'Tiga', 'D' => 'Empat'], ['A']);
        $hasil = (new RincianPilihanSoalCbt)->hitung($soal, ['A' => 90, 'B' => 5, 'C' => 4, 'D' => 0], 100);

        $this->assertSame(['kunci', 'dipilih', 'jarang_dipilih', 'belum_dipilih'], array_column($hasil['opsi'], 'indikator'));
        $this->assertSame(2, $hasil['perlu_ditinjau']);

        $hasil = (new RincianPilihanSoalCbt)->hitung($soal, ['A' => 200, 'B' => 10], 201);
        $this->assertSame('jarang_dipilih', $hasil['opsi'][1]['indikator']);
        $this->assertSame(4.98, $hasil['opsi'][1]['persen']);

        $hasil = (new RincianPilihanSoalCbt)->hitung($soal, ['B' => 500], 10001);
        $this->assertSame(5.0, $hasil['opsi'][1]['persen']);
        $this->assertSame('jarang_dipilih', $hasil['opsi'][1]['indikator']);
    }

    public function test_pgk_dapat_melebihi_seratus_persen_tanpa_menerapkan_patokan_pengecoh_pg(): void
    {
        $soal = $this->soal(['A' => 'Satu', 'B' => 'Dua', 'C' => 'Tiga'], ['jawaban' => ['A', 'C']], 'pilihan_ganda_kompleks');
        $hasil = (new RincianPilihanSoalCbt)->hitung($soal, ['A' => 10, 'B' => 0, 'C' => 8], 10);

        $this->assertSame([true, false, true], array_column($hasil['opsi'], 'kunci'));
        $this->assertSame([100.0, 0.0, 80.0], array_column($hasil['opsi'], 'persen'));
        $this->assertSame([0, 10, 2], array_column($hasil['opsi'], 'tidak_dipilih'));
        $this->assertSame('sebaran_pgk', $hasil['opsi'][1]['indikator']);
        $this->assertSame(0, $hasil['perlu_ditinjau']);
    }

    public function test_data_kosong_dan_sampel_kecil_tidak_menghasilkan_penilaian_pengecoh(): void
    {
        $soal = $this->soal(['A' => 'Satu', 'B' => 'Dua'], ['A']);
        foreach ([0 => 'belum_ada_data', 1 => 'sampel_terbatas', 9 => 'sampel_terbatas'] as $jumlah => $status) {
            $hasil = (new RincianPilihanSoalCbt)->hitung($soal, ['A' => $jumlah, 'B' => 0], $jumlah);

            $this->assertSame($status, $hasil['opsi'][1]['indikator']);
            $this->assertSame(0, $hasil['perlu_ditinjau']);
            $this->assertSame($jumlah === 0 ? null : 100.0, $hasil['opsi'][0]['persen']);
        }
    }

    public function test_kunci_tidak_valid_tidak_menjadikan_semua_pilihan_sebagai_pengecoh(): void
    {
        foreach ([null, [], ['jawaban' => 'Z'], ['A', 'B'], ['jawaban' => [['A']]]] as $kunci) {
            $soal = $this->soal(['A' => 'Satu', 'B' => 'Dua'], $kunci);
            $hasil = (new RincianPilihanSoalCbt)->hitung($soal, ['A' => 10, 'B' => 0], 10);

            $this->assertFalse($hasil['kunci_valid']);
            $this->assertSame([null, null], array_column($hasil['opsi'], 'kunci'));
            $this->assertSame(['periksa_data', 'periksa_data'], array_column($hasil['opsi'], 'indikator'));
            $this->assertSame(0, $hasil['perlu_ditinjau']);
        }
    }

    public function test_jawaban_tidak_dikenali_menunda_penilaian_pengecoh(): void
    {
        $soal = $this->soal(['A' => 'Satu', 'B' => 'Dua'], ['A']);
        $hasil = (new RincianPilihanSoalCbt)->hitung($soal, ['A' => 9, 'B' => 0], 10, 1);

        $this->assertSame(1, $hasil['jawaban_tidak_dikenali']);
        $this->assertSame(90.0, $hasil['opsi'][0]['persen']);
        $this->assertSame(['periksa_data', 'periksa_data'], array_column($hasil['opsi'], 'indikator'));
        $this->assertSame(0, $hasil['perlu_ditinjau']);
    }

    public function test_jenis_lain_tidak_diberi_label_pengecoh(): void
    {
        $soal = $this->soal(['pernyataan' => [['nomor' => 1, 'teks' => 'Pernyataan']]], ['jawaban' => [1 => true]], 'benar_salah');
        $hasil = (new RincianPilihanSoalCbt)->hitung($soal, [], 10);

        $this->assertFalse($hasil['tersedia']);
        $this->assertSame([], $hasil['opsi']);
        $this->assertSame(0, $hasil['perlu_ditinjau']);
    }

    public function test_pilihan_kosong_yang_tidak_disajikan_ke_siswa_tidak_dianggap_pengecoh(): void
    {
        $soal = $this->soal(['A' => '0', 'B' => 'Satu', 'C' => '', 'D' => '   '], ['A']);
        $hasil = (new RincianPilihanSoalCbt)->hitung($soal, ['A' => 9, 'B' => 1], 10);

        $this->assertSame(['A', 'B'], array_column($hasil['opsi'], 'kode'));
        $this->assertSame(0, $hasil['perlu_ditinjau']);
    }

    private function soal(array $opsi, ?array $kunci, string $jenis = 'pilihan_ganda'): SoalUjianCbt
    {
        $soal = new SoalCbt(['jenis_soal' => $jenis, 'opsi' => $opsi, 'kunci_jawaban' => $kunci]);
        $relasi = new SoalUjianCbt;
        $relasi->setRelation('soalCbt', $soal);

        return $relasi;
    }
}
