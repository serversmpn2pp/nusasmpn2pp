<?php

namespace Tests\Unit;

use App\Models\SoalCbt;
use App\Models\SoalUjianCbt;
use App\Services\Cbt\KoreksiOtomatisCbtService;
use App\Services\Cbt\PengacakPenyajianCbt;
use App\Services\Cbt\RincianPemetaanSoalCbt;
use PHPUnit\Framework\TestCase;

class RincianPemetaanSoalCbtTest extends TestCase
{
    public function test_benar_salah_membedakan_pilihan_dari_kebenaran_jawaban_dan_kosong(): void
    {
        $layanan = $this->layanan();
        $data = $layanan->siapkan($this->benarSalah());
        foreach ([[2 => true, 5 => false], [2 => 'salah', 5 => 0], [2 => '1', 5 => 'benar'], [2 => '']] as $jawaban) {
            $layanan->catat($data, $jawaban);
        }
        $hasil = $layanan->ringkas($data, 4);
        [$satu, $dua] = $hasil['butir'];

        $this->assertTrue($hasil['kunci_valid']);
        $this->assertSame(['2', '5'], array_column($hasil['butir'], 'nomor'));
        $this->assertSame([2, 1, 1], [$satu['benar'], $satu['salah'], $satu['kosong']]);
        $this->assertSame('Salah', $dua['kunci']);
        $this->assertSame([2, 1, 1], [$dua['benar'], $dua['salah'], $dua['kosong']]);
        $this->assertSame([1, 2], array_column($dua['pilihan'], 'dipilih'));
        $this->assertSame([25.0, 50.0], array_column($dua['pilihan'], 'persen'));
        $this->assertSame(50.0, $dua['persen_benar']);
        $this->assertSame('bs-lima', $dua['media_key']);
    }

    public function test_jawaban_tidak_dikenali_tidak_dijadikan_salah_atau_kosong(): void
    {
        $layanan = $this->layanan();
        $data = $layanan->siapkan($this->benarSalah());
        $layanan->catat($data, [2 => 'mungkin', 5 => ['benar']]);
        $layanan->catat($data, [2 => 'ya', 5 => 'tidak', 99 => 'benar']);
        $hasil = $layanan->ringkas($data, 2);

        $this->assertSame(2, $hasil['jawaban_tidak_dikenali']);
        foreach ($hasil['butir'] as $butir) {
            $this->assertSame([1, 0, 0, 1], [$butir['benar'], $butir['salah'], $butir['kosong'], $butir['tidak_dikenali']]);
        }
    }

    public function test_menjodohkan_menghitung_isi_pasangan_dan_pengecoh_per_butir(): void
    {
        $layanan = $this->layanan();
        $data = $layanan->siapkan($this->menjodohkan());
        foreach ([[1 => '  JAKARTA ', 3 => 'Bandung'], [1 => 'Surabaya', 3 => 'Jakarta'], [3 => 'Surabaya'], null] as $jawaban) {
            $layanan->catat($data, $jawaban);
        }
        $hasil = $layanan->ringkas($data, 4);
        [$satu, $dua] = $hasil['butir'];

        $this->assertSame([1, 1, 2], [$satu['benar'], $satu['salah'], $satu['kosong']]);
        $this->assertSame([1, 2, 1], [$dua['benar'], $dua['salah'], $dua['kosong']]);
        $this->assertSame([1, 0, 1], array_column($satu['pilihan'], 'dipilih'));
        $this->assertSame([1, 1, 1], array_column($dua['pilihan'], 'dipilih'));
        $this->assertSame([false, false, true], array_column($dua['pilihan'], 'pengecoh_tambahan'));
        $this->assertSame('pengecoh-kota', $dua['pilihan'][2]['media_key']);
        $this->assertSame('pasangan-satu', $satu['media_key']);
        $this->assertSame(25.0, $dua['persen_benar']);
    }

    public function test_pasangan_yang_sudah_tidak_tersedia_dipisahkan_dan_huruf_acak_tidak_ditebak(): void
    {
        $layanan = $this->layanan();
        $data = $layanan->siapkan($this->menjodohkan());
        $layanan->catat($data, [1 => 'A', 3 => 'Kota lama']);
        $hasil = $layanan->ringkas($data, 1);

        $this->assertSame(1, $hasil['jawaban_tidak_dikenali']);
        foreach ($hasil['butir'] as $butir) {
            $this->assertSame(1, $butir['tidak_dikenali']);
            $this->assertSame(0, $butir['salah']);
            $this->assertSame(0, $butir['kosong']);
            $this->assertSame(0, array_sum(array_column($butir['pilihan'], 'dipilih')));
        }
    }

    public function test_kunci_hilang_hanya_menunda_kebenaran_butir_tersebut(): void
    {
        $layanan = $this->layanan();
        $soal = $this->benarSalah();
        $soal->soalCbt->kunci_jawaban = [5 => false];
        $data = $layanan->siapkan($soal);
        $layanan->catat($data, [2 => 'benar', 5 => 'salah']);
        $hasil = $layanan->ringkas($data, 1);

        $this->assertFalse($hasil['kunci_valid']);
        $this->assertNull($hasil['butir'][0]['benar']);
        $this->assertNull($hasil['butir'][0]['salah']);
        $this->assertNull($hasil['butir'][0]['persen_benar']);
        $this->assertSame(1, $hasil['butir'][0]['pilihan'][0]['dipilih']);
        $this->assertSame(1, $hasil['butir'][1]['benar']);

        $soal = $this->menjodohkan();
        $soal->soalCbt->kunci_jawaban = ['jawaban' => [1 => 'Kota dihapus', 3 => 'Bandung']];
        $data = $layanan->siapkan($soal);
        $this->assertFalse($data['kunci_valid']);
        $this->assertFalse($data['butir'][1]['kunci_valid']);
    }

    public function test_tanpa_peserta_persentase_null_dan_jawaban_kosong_dihitung_per_butir(): void
    {
        $layanan = $this->layanan();
        foreach ([$this->benarSalah(), $this->menjodohkan()] as $soal) {
            $data = $layanan->siapkan($soal);
            $hasil = $layanan->ringkas($data, 0);
            $this->assertNull($hasil['butir'][0]['persen_benar']);
            $this->assertNull($hasil['butir'][0]['pilihan'][0]['persen']);
            $layanan->catat($data, null);
            $layanan->catat($data, []);
            $hasil = $layanan->ringkas($data, 2);
            $this->assertSame([2, 2], array_column($hasil['butir'], 'kosong'));
            $this->assertSame([0, 0], array_column($hasil['butir'], 'salah'));
        }
    }

    public function test_pilihan_pasangan_duplikat_tidak_menggandakan_hitungan(): void
    {
        $layanan = $this->layanan();
        $soal = $this->menjodohkan();
        $opsi = $soal->soalCbt->opsi;
        $opsi['pasangan'][] = ['nomor' => 8, 'kiri' => 'Kota pusat', 'kanan' => ' JAKARTA '];
        $opsi['pengecoh'] = ['Surabaya', 'surabaya', 'Bandung'];
        $soal->soalCbt->opsi = $opsi;
        $soal->soalCbt->kunci_jawaban = ['jawaban' => [1 => 'Jakarta', 3 => 'Bandung', 8 => 'Jakarta']];
        $data = $layanan->siapkan($soal);
        $layanan->catat($data, [1 => 'Jakarta', 3 => 'Jakarta', 8 => 'Jakarta']);
        $hasil = $layanan->ringkas($data, 1);

        $this->assertCount(3, $hasil['butir'][0]['pilihan']);
        $this->assertSame([1, 0, 1], array_column($hasil['butir'], 'benar'));
        $this->assertSame([1, 1, 1], array_map(fn ($butir) => $butir['pilihan'][0]['dipilih'], $hasil['butir']));
    }

    public function test_jenis_lain_tidak_memiliki_rincian_pemetaan(): void
    {
        $layanan = $this->layanan();
        $soal = $this->benarSalah();
        $soal->soalCbt->jenis_soal = 'pilihan_ganda';
        $data = $layanan->siapkan($soal);
        $layanan->catat($data, ['A']);
        $this->assertFalse($data['tersedia']);
        $this->assertSame([], $layanan->ringkas($data, 1)['butir']);
    }

    private function layanan(): RincianPemetaanSoalCbt
    {
        return new RincianPemetaanSoalCbt(new KoreksiOtomatisCbtService(new PengacakPenyajianCbt));
    }

    private function benarSalah(): SoalUjianCbt
    {
        return $this->soal('benar_salah', ['pernyataan' => [
            ['nomor' => 2, 'teks' => 'Pernyataan pertama'],
            ['nomor' => 5, 'teks' => 'Pernyataan kedua', 'media_key' => 'bs-lima'],
        ]], ['jawaban' => [2 => true, 5 => false]]);
    }

    private function menjodohkan(): SoalUjianCbt
    {
        return $this->soal('menjodohkan', [
            'pasangan' => [
                ['nomor' => 1, 'kiri' => 'Ibu kota Indonesia', 'kanan' => 'Jakarta', 'media_kiri_key' => 'pasangan-satu'],
                ['nomor' => 3, 'kiri' => 'Ibu kota Jawa Barat', 'kanan' => 'Bandung'],
            ],
            'pengecoh' => ['Surabaya'],
            'pengecoh_media' => [['teks' => 'Surabaya', 'media_key' => 'pengecoh-kota']],
        ], ['jawaban' => [1 => 'Jakarta', 3 => 'Bandung']]);
    }

    private function soal(string $jenis, array $opsi, array $kunci): SoalUjianCbt
    {
        $soal = new SoalUjianCbt;
        $soal->setRelation('soalCbt', new SoalCbt(['jenis_soal' => $jenis, 'opsi' => $opsi, 'kunci_jawaban' => $kunci]));

        return $soal;
    }
}
