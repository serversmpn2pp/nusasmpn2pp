<?php

namespace Tests\Unit;

use App\Services\Cbt\TingkatKesukaranSoalCbt;
use PHPUnit\Framework\TestCase;

class TingkatKesukaranSoalCbtTest extends TestCase
{
    public function test_kategori_mengikuti_indeks_termasuk_batas_dan_skor_ekstrem(): void
    {
        $layanan = new TingkatKesukaranSoalCbt;

        foreach ([
            [0, 'sukar'],
            [0.30, 'sukar'],
            [0.30001, 'sedang'],
            [0.50, 'sedang'],
            [0.70, 'sedang'],
            [0.70001, 'mudah'],
            [1, 'mudah'],
        ] as [$indeks, $kategori]) {
            $hasil = $layanan->hitung($indeks * 10 * 4, 10, 4);

            $this->assertEqualsWithDelta($indeks, $hasil['indeks'], 1e-10);
            $this->assertSame($kategori, $hasil['kategori']);
            $this->assertSame('siap', $hasil['status']);
        }
    }

    public function test_skor_parsial_dinormalisasi_terhadap_bobot_soal(): void
    {
        $layanan = new TingkatKesukaranSoalCbt;

        foreach ([1, 2, 3, 4] as $bobot) {
            $hasil = $layanan->hitung(10 * $bobot * 0.5, 10, $bobot);

            $this->assertSame(0.5, $hasil['indeks']);
            $this->assertSame('sedang', $hasil['kategori']);
        }
    }

    public function test_sampel_kecil_menampilkan_indeks_tanpa_kategori(): void
    {
        $layanan = new TingkatKesukaranSoalCbt;

        foreach ([1, 9] as $dinilai) {
            $hasil = $layanan->hitung($dinilai * 2, $dinilai, 2);

            $this->assertSame(1.0, $hasil['indeks']);
            $this->assertNull($hasil['kategori']);
            $this->assertSame('sampel_terbatas', $hasil['status']);
        }
    }

    public function test_koreksi_tertunda_tidak_dihitung_nol_dan_menunda_kategori(): void
    {
        $hasil = (new TingkatKesukaranSoalCbt)->hitung(40, 10, 4, 5);

        $this->assertSame(1.0, $hasil['indeks']);
        $this->assertNull($hasil['kategori']);
        $this->assertSame('menunggu_koreksi', $hasil['status']);
    }

    public function test_belum_ada_nilai_tidak_dianggap_soal_sukar(): void
    {
        $hasil = (new TingkatKesukaranSoalCbt)->hitung(0, 0, 2, 10);

        $this->assertNull($hasil['indeks']);
        $this->assertNull($hasil['kategori']);
        $this->assertSame('belum_ada_nilai', $hasil['status']);
    }

    public function test_skor_tidak_valid_tidak_menghasilkan_kategori(): void
    {
        $layanan = new TingkatKesukaranSoalCbt;

        foreach ([[0, 0], [1, 0], [1, -1], [-1, 2], [21, 2], [NAN, 2], [10, INF]] as [$skor, $bobot]) {
            $hasil = $layanan->hitung($skor, 10, $bobot);

            $this->assertNull($hasil['indeks']);
            $this->assertNull($hasil['kategori']);
            $this->assertSame('skor_tidak_valid', $hasil['status']);
        }

        $hasil = $layanan->hitung(10, 10, 2, 0, 1);
        $this->assertSame('skor_tidak_valid', $hasil['status']);
        $this->assertNull($hasil['kategori']);
    }

    public function test_galat_penjumlahan_desimal_tidak_menggeser_batas_kategori(): void
    {
        $total = array_sum(array_fill(0, 100, 0.3));
        $hasil = (new TingkatKesukaranSoalCbt)->hitung($total, 100, 1);

        $this->assertSame(0.3, $hasil['indeks']);
        $this->assertSame('sukar', $hasil['kategori']);
    }
}
