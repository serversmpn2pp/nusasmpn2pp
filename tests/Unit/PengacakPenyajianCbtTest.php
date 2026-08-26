<?php

namespace Tests\Unit;

use App\Models\PesertaUjianCbt;
use App\Models\SoalCbt;
use App\Models\SoalUjianCbt;
use App\Models\UjianCbt;
use App\Services\Cbt\PengacakPenyajianCbt;
use PHPUnit\Framework\TestCase;

class PengacakPenyajianCbtTest extends TestCase
{
    public function test_urutan_soal_diacak_secara_stabil_untuk_setiap_peserta(): void
    {
        $layanan = new PengacakPenyajianCbt;
        $ujian = $this->ujian(true, true);
        $peserta = $this->peserta(21);
        $soal = collect(range(1, 8))->map(fn (int $id) => $this->relasiSoal($id));

        $urutanPertama = $layanan->urutkanSoal($ujian, $peserta, $soal)->pluck('id')->all();
        $urutanKedua = $layanan->urutkanSoal($ujian, $peserta, $soal)->pluck('id')->all();

        $this->assertSame($urutanPertama, $urutanKedua);
        $this->assertNotSame(range(1, 8), $urutanPertama);
        $this->assertEqualsCanonicalizing(range(1, 8), $urutanPertama);
    }

    public function test_urutan_soal_tetap_mengikuti_nomor_saat_pengacakan_dimatikan(): void
    {
        $layanan = new PengacakPenyajianCbt;
        $ujian = $this->ujian(false, false);
        $peserta = $this->peserta(21);
        $soal = collect([
            $this->relasiSoal(3, 3),
            $this->relasiSoal(1, 1),
            $this->relasiSoal(2, 2),
        ]);

        $this->assertSame(
            [1, 2, 3],
            $layanan->urutkanSoal($ujian, $peserta, $soal)->pluck('id')->all(),
        );
    }

    public function test_pilihan_ganda_diacak_stabil_tanpa_mengubah_kode_jawaban_asli(): void
    {
        $layanan = new PengacakPenyajianCbt;
        $ujian = $this->ujian(true, true);
        $peserta = $this->peserta(21);
        $relasiSoal = $this->relasiSoal(1);

        $pilihanPertama = $layanan->pilihanJawaban($ujian, $peserta, $relasiSoal);
        $pilihanKedua = $layanan->pilihanJawaban($ujian, $peserta, $relasiSoal);

        $this->assertSame($pilihanPertama->keys()->all(), $pilihanKedua->keys()->all());
        $this->assertNotSame(['A', 'B', 'C', 'D'], $pilihanPertama->keys()->all());
        $this->assertEqualsCanonicalizing(['A', 'B', 'C', 'D'], $pilihanPertama->keys()->all());
        $this->assertSame('Jawaban benar', $pilihanPertama->get('B'));
    }

    private function ujian(bool $acakSoal, bool $acakJawaban): UjianCbt
    {
        $ujian = new UjianCbt([
            'acak_soal' => $acakSoal,
            'acak_jawaban' => $acakJawaban,
        ]);
        $ujian->id = 11;

        return $ujian;
    }

    private function peserta(int $id): PesertaUjianCbt
    {
        $peserta = new PesertaUjianCbt;
        $peserta->id = $id;

        return $peserta;
    }

    private function relasiSoal(int $id, ?int $nomorUrut = null): SoalUjianCbt
    {
        $soal = new SoalCbt([
            'jenis_soal' => 'pilihan_ganda',
            'opsi' => [
                'pilihan' => [
                    'A' => 'Pilihan satu',
                    'B' => 'Jawaban benar',
                    'C' => 'Pilihan tiga',
                    'D' => 'Pilihan empat',
                ],
            ],
        ]);
        $relasi = new SoalUjianCbt(['nomor_urut' => $nomorUrut ?? $id]);
        $relasi->id = $id;
        $relasi->setRelation('soalCbt', $soal);

        return $relasi;
    }
}
