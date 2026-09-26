<?php

namespace App\Services\Cbt;

use App\Models\SoalUjianCbt;
use App\Models\UjianCbt;

class AnalisisSoalCbtService
{
    public function __construct(
        private readonly PengacakPenyajianCbt $pengacakPenyajianCbt,
        private readonly TingkatKesukaranSoalCbt $tingkatKesukaran,
        private readonly RincianPilihanSoalCbt $rincianPilihan,
        private readonly RincianPemetaanSoalCbt $rincianPemetaan,
    ) {}

    public function untukUjian(UjianCbt $ujianCbt, ?int $kelasId = null): array
    {
        $soalPaket = $ujianCbt->soalUjianCbt()
            ->with('soalCbt')
            ->get()
            ->sortBy(fn (SoalUjianCbt $soal) => sprintf('%05d|%08d', $soal->nomor_urut ?? 9999, $soal->id))
            ->values();
        $jumlahSoal = (int) $ujianCbt->jumlah_soal;
        $soalDitampilkan = $ujianCbt->acak_soal ? $soalPaket : $soalPaket->take($jumlahSoal);
        $baris = $soalDitampilkan->mapWithKeys(fn (SoalUjianCbt $soal) => [
            $soal->id => [
                'soal' => $soal,
                'disajikan' => 0,
                'terjawab' => 0,
                'belum_dijawab' => 0,
                'dinilai' => 0,
                'belum_dinilai' => 0,
                'skor_penuh' => 0,
                'skor_sebagian' => 0,
                'skor_nol' => 0,
                'total_skor' => 0.0,
                'skor_tidak_valid' => 0,
                'pilihan' => array_fill_keys(array_keys($this->rincianPilihan->pilihan($soal)), 0),
                'jawaban_tidak_dikenali' => 0,
                'rincian_pemetaan' => $this->rincianPemetaan->siapkan($soal),
            ],
        ])->all();
        $pesertaSelesai = 0;

        $ujianCbt->pesertaUjianCbt()
            ->where('status', 'selesai')
            ->when($kelasId, fn ($query) => $query->whereHas(
                'kelasUjianCbt',
                fn ($kelas) => $kelas->where('kelas_id', $kelasId),
            ))
            ->with(['jawabanPesertaUjianCbt' => fn ($query) => $query->whereIn('soal_ujian_cbt_id', array_keys($baris))])
            ->chunkById(100, function ($peserta) use ($ujianCbt, $soalPaket, $jumlahSoal, &$pesertaSelesai, &$baris) {
                foreach ($peserta as $siswa) {
                    $pesertaSelesai++;
                    $jawaban = $siswa->jawabanPesertaUjianCbt->keyBy('soal_ujian_cbt_id');

                    foreach ($this->pengacakPenyajianCbt->urutkanSoal($ujianCbt, $siswa, $soalPaket)->take($jumlahSoal) as $soal) {
                        $baris[$soal->id]['disajikan']++;
                        $hasil = $jawaban->get($soal->id);
                        $this->rincianPemetaan->catat($baris[$soal->id]['rincian_pemetaan'], $hasil?->jawaban);
                        $terjawab = $hasil && ($this->adaJawaban($hasil->jawaban) || filled($hasil->lokasi_file));

                        if ($terjawab) {
                            $baris[$soal->id]['terjawab']++;
                            $this->hitungPilihan($baris[$soal->id], $soal, $hasil->jawaban);
                        } else {
                            $baris[$soal->id]['belum_dijawab']++;
                        }

                        $manualKosong = ! $terjawab && ! in_array(
                            $soal->soalCbt?->jenis_soal,
                            KoreksiOtomatisCbtService::JENIS_OTOMATIS,
                            true,
                        );

                        if ($hasil?->skor === null && ! $manualKosong) {
                            $baris[$soal->id]['belum_dinilai']++;

                            continue;
                        }

                        $skor = (float) ($hasil?->skor ?? 0);
                        $bobot = (float) $soal->bobot;
                        $baris[$soal->id]['dinilai']++;
                        $baris[$soal->id]['total_skor'] += $skor;

                        if (! is_finite($skor) || $skor < 0 || $skor > $bobot) {
                            $baris[$soal->id]['skor_tidak_valid']++;
                        }

                        if ($bobot > 0 && $skor >= $bobot) {
                            $baris[$soal->id]['skor_penuh']++;
                        } elseif ($skor > 0) {
                            $baris[$soal->id]['skor_sebagian']++;
                        } else {
                            $baris[$soal->id]['skor_nol']++;
                        }
                    }
                }
            });

        $hasil = collect($baris)->map(function (array $baris) {
            $dinilai = $baris['dinilai'];
            $bobot = (float) $baris['soal']->bobot;
            $baris['rata_rata_skor'] = $dinilai > 0 ? round($baris['total_skor'] / $dinilai, 2) : null;
            $baris['persen_skor_penuh'] = $dinilai > 0 ? round($baris['skor_penuh'] / $dinilai * 100, 2) : null;
            $baris['persen_rata_rata_skor'] = $dinilai > 0 && $bobot > 0
                ? round($baris['total_skor'] / ($dinilai * $bobot) * 100, 2)
                : null;
            $baris['kesukaran'] = $this->tingkatKesukaran->hitung(
                $baris['total_skor'], $dinilai, $bobot, $baris['belum_dinilai'], $baris['skor_tidak_valid'],
            );
            $baris['rincian_pilihan'] = $this->rincianPilihan->hitung(
                $baris['soal'], $baris['pilihan'], $baris['disajikan'], $baris['jawaban_tidak_dikenali'],
            );
            $baris['rincian_pemetaan'] = $this->rincianPemetaan->ringkas($baris['rincian_pemetaan'], $baris['disajikan']);

            return $baris;
        })->values();

        return [
            'peserta_selesai' => $pesertaSelesai,
            'jumlah_soal' => $hasil->count(),
            'soal_belum_lengkap' => $hasil->filter(fn ($baris) => $baris['belum_dinilai'] > 0)->count(),
            'minimal_hasil_kesukaran' => TingkatKesukaranSoalCbt::MINIMAL_HASIL,
            'ringkasan_kesukaran' => array_replace(
                ['sukar' => 0, 'sedang' => 0, 'mudah' => 0, 'belum_dikategorikan' => 0],
                $hasil->countBy(fn ($baris) => $baris['kesukaran']['kategori'] ?? 'belum_dikategorikan')->all(),
            ),
            'soal' => $hasil,
        ];
    }

    private function adaJawaban(?array $jawaban): bool
    {
        return $jawaban !== null && $jawaban !== [];
    }

    private function hitungPilihan(array &$baris, SoalUjianCbt $soal, ?array $jawaban): void
    {
        if (! in_array($soal->soalCbt?->jenis_soal, ['pilihan_ganda', 'pilihan_ganda_kompleks'], true) || ! $jawaban) {
            return;
        }

        $kodeTerpilih = [];
        $tidakDikenali = false;
        foreach ($jawaban as $pilihan) {
            if (! is_scalar($pilihan)) {
                $tidakDikenali = true;

                continue;
            }

            $kode = mb_strtoupper(trim((string) $pilihan));
            if ($kode !== '') {
                $kodeTerpilih[$kode] = true;
            }
        }
        if ($soal->soalCbt?->jenis_soal === 'pilihan_ganda' && count($kodeTerpilih) > 1) {
            $baris['jawaban_tidak_dikenali']++;

            return;
        }

        // Satu siswa hanya dihitung sekali pada setiap opsi, meskipun data lama memuat duplikat.
        foreach (array_keys($kodeTerpilih) as $kode) {
            if (array_key_exists($kode, $baris['pilihan'])) {
                $baris['pilihan'][$kode]++;
            } else {
                $tidakDikenali = true;
            }
        }
        if ($tidakDikenali || $kodeTerpilih === []) {
            $baris['jawaban_tidak_dikenali']++;
        }
    }
}
