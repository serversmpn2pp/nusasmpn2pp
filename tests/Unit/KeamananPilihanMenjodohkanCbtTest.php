<?php

namespace Tests\Unit;

use App\Models\PesertaUjianCbt;
use App\Models\SoalCbt;
use App\Models\SoalUjianCbt;
use App\Models\UjianCbt;
use App\Services\Cbt\PengacakPenyajianCbt;
use Illuminate\Encryption\Encrypter;
use Illuminate\Encryption\MissingAppKeyException;
use Tests\TestCase;

class KeamananPilihanMenjodohkanCbtTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->pakaiKunci('server-pengujian');
    }

    public function test_urutan_stabil_di_request_baru_dan_memakai_hmac_rahasia_server(): void
    {
        [$ujian, $peserta, $relasi] = $this->dataSoal();
        $pilihan = (new PengacakPenyajianCbt)->pilihanJawaban($ujian, $peserta, $relasi);
        $kunci = hash('sha256', 'server-pengujian', true);
        $diharapkan = collect(['Kanan satu', 'Kanan dua', 'Kanan tiga', 'Pengecoh'])
            ->sortBy(fn ($teks) => hash_hmac('sha256', implode('|', [
                'nusa-cbt-menjodohkan-v2', 11, 21, 31, mb_strtolower($teks),
            ]), $kunci))->values()->all();

        $this->assertSame($diharapkan, $pilihan->values()->all());
        $this->assertSame(['A', 'B', 'C', 'D'], $pilihan->keys()->all());
        $this->assertSame($pilihan->all(), (new PengacakPenyajianCbt)->pilihanJawaban($ujian, $peserta, $relasi)->all());
    }

    public function test_urutan_tidak_mengodekan_nomor_atau_relasi_pasangan_benar(): void
    {
        [$ujian, $peserta, $relasi] = $this->dataSoal();
        $layanan = new PengacakPenyajianCbt;

        foreach ([false, true] as $acak) {
            $ujian->acak_jawaban = $acak;
            $sebelum = $layanan->pilihanJawaban($ujian, $peserta, $relasi)->all();
            // Bank opsi tetap sama, tetapi nomor/urutan dan pemilik opsi kanan berubah.
            $relasiBerubah = clone $relasi;
            $soalBerubah = clone $relasi->soalCbt;
            $soalBerubah->opsi = [
                'pasangan' => [
                    ['nomor' => 80, 'kiri' => 'Kiri tiga', 'kanan' => 'Pengecoh'],
                    ['nomor' => 7, 'kiri' => 'Kiri satu', 'kanan' => 'Kanan tiga'],
                    ['nomor' => 40, 'kiri' => 'Kiri dua', 'kanan' => 'Kanan satu'],
                ],
                'pengecoh' => ['Kanan dua'],
            ];
            $relasiBerubah->setRelation('soalCbt', $soalBerubah);

            $this->assertSame($sebelum, $layanan->pilihanJawaban($ujian, $peserta, $relasiBerubah)->all());
        }
    }

    public function test_secret_server_mempengaruhi_urutan_dengan_data_publik_yang_sama(): void
    {
        [$ujian, $peserta, $relasi] = $this->dataSoal();
        $urutan = [];

        foreach (range(1, 16) as $nomor) {
            $this->pakaiKunci('server-'.$nomor);
            $urutan[] = (new PengacakPenyajianCbt)->pilihanJawaban($ujian, $peserta, $relasi)->values()->all();
        }

        $this->assertGreaterThan(1, count(array_unique(array_map('serialize', $urutan))));
    }

    public function test_dua_opsi_tidak_dipaksa_bertukar_sehingga_relasi_tidak_bocor(): void
    {
        [$ujian, $peserta, $relasi] = $this->dataSoal();
        $opsi = $relasi->soalCbt->opsi;
        $opsi['pasangan'] = array_slice($opsi['pasangan'], 0, 2);
        $opsi['pengecoh'] = [];
        $relasi->soalCbt->opsi = $opsi;

        foreach ([false, true] as $acak) {
            $ujian->acak_jawaban = $acak;
            $urutan = [];
            foreach (range(1, 16) as $nomor) {
                $this->pakaiKunci('server-'.$nomor);
                $urutan[] = (new PengacakPenyajianCbt)->pilihanJawaban($ujian, $peserta, $relasi)->values()->all();
            }

            $this->assertContains(['Kanan satu', 'Kanan dua'], $urutan);
            $this->assertContains(['Kanan dua', 'Kanan satu'], $urutan);
        }
    }

    public function test_mode_tetap_sama_antar_peserta_dan_mode_acak_memakai_konteks_peserta(): void
    {
        [$ujian, $peserta, $relasi] = $this->dataSoal();
        $layanan = new PengacakPenyajianCbt;
        $ujian->acak_jawaban = false;
        $tetap = $layanan->pilihanJawaban($ujian, $peserta, $relasi)->all();
        $urutanAcak = [];

        foreach (range(21, 36) as $id) {
            $peserta->id = $id;
            $this->assertSame($tetap, $layanan->pilihanJawaban($ujian, $peserta, $relasi)->all());
            $ujian->acak_jawaban = true;
            $urutanAcak[] = $layanan->pilihanJawaban($ujian, $peserta, $relasi)->values()->all();
            $ujian->acak_jawaban = false;
        }

        $this->assertGreaterThan(1, count(array_unique(array_map('serialize', $urutanAcak))));
    }

    public function test_opsi_kosong_dan_duplikat_tidak_mengubah_isi_jawaban(): void
    {
        [$ujian, $peserta, $relasi] = $this->dataSoal();
        $opsi = $relasi->soalCbt->opsi;
        $opsi['pasangan'][] = ['nomor' => 9, 'kiri' => 'Duplikat', 'kanan' => ' KANAN SATU '];
        $opsi['pasangan'][] = ['nomor' => 10, 'kiri' => 'Kosong', 'kanan' => ' '];
        $opsi['pengecoh'] = [' Pengecoh ', '', 'kanan dua'];
        $relasi->soalCbt->opsi = $opsi;

        $this->assertEqualsCanonicalizing(
            ['Kanan satu', 'Kanan dua', 'Kanan tiga', 'Pengecoh'],
            (new PengacakPenyajianCbt)->pilihanJawaban($ujian, $peserta, $relasi)->values()->all(),
        );
    }

    public function test_tidak_kembali_ke_hash_publik_saat_kunci_server_tidak_tersedia(): void
    {
        [$ujian, $peserta, $relasi] = $this->dataSoal();
        config(['app.key' => null]);
        $this->app->forgetInstance('encrypter');
        $this->expectException(MissingAppKeyException::class);

        (new PengacakPenyajianCbt)->pilihanJawaban($ujian, $peserta, $relasi);
    }

    private function pakaiKunci(string $label): void
    {
        $this->app->instance('encrypter', new Encrypter(hash('sha256', $label, true), 'AES-256-CBC'));
    }

    private function dataSoal(): array
    {
        $ujian = new UjianCbt(['acak_jawaban' => true]);
        $ujian->id = 11;
        $peserta = new PesertaUjianCbt;
        $peserta->id = 21;
        $soal = new SoalCbt([
            'jenis_soal' => 'menjodohkan',
            'opsi' => [
                'pasangan' => [
                    ['nomor' => 1, 'kiri' => 'Kiri satu', 'kanan' => 'Kanan satu'],
                    ['nomor' => 2, 'kiri' => 'Kiri dua', 'kanan' => 'Kanan dua'],
                    ['nomor' => 3, 'kiri' => 'Kiri tiga', 'kanan' => 'Kanan tiga'],
                ],
                'pengecoh' => ['Pengecoh'],
            ],
        ]);
        $relasi = new SoalUjianCbt;
        $relasi->id = 31;
        $relasi->setRelation('soalCbt', $soal);

        return [$ujian, $peserta, $relasi];
    }
}
