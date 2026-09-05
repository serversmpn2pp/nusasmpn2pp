<?php

namespace App\Services\Cbt;

use App\Models\JawabanPesertaUjianCbt;
use App\Models\PesertaUjianCbt;
use App\Models\SoalCbt;
use App\Models\SoalUjianCbt;
use App\Models\UjianCbt;
use Illuminate\Support\Facades\DB;

class KoreksiOtomatisCbtService
{
    public const JENIS_OTOMATIS = [
        'pilihan_ganda',
        'pilihan_ganda_kompleks',
        'benar_salah',
        'menjodohkan',
        'isian_singkat',
        'numerik',
    ];

    public function __construct(private readonly PengacakPenyajianCbt $pengacakPenyajianCbt) {}

    public function koreksiUjian(UjianCbt $ujianCbt): array
    {
        $ringkasan = $this->ringkasanKosong();

        $ujianCbt->pesertaUjianCbt()
            ->with('ujianCbt')
            ->orderBy('id')
            ->chunkById(100, function ($daftarPeserta) use (&$ringkasan) {
                foreach ($daftarPeserta as $peserta) {
                    $hasil = $this->koreksiPeserta($peserta);

                    foreach ($ringkasan as $kunci => $nilai) {
                        $ringkasan[$kunci] = $nilai + ($hasil[$kunci] ?? 0);
                    }
                }
            });

        return $ringkasan;
    }

    public function koreksiPeserta(PesertaUjianCbt $peserta): array
    {
        $peserta->loadMissing('ujianCbt');
        $soalUjian = $this->ambilSoalUjian($peserta->ujianCbt, $peserta);
        $jawabanTersimpan = $peserta->jawabanPesertaUjianCbt()
            ->whereIn('soal_ujian_cbt_id', $soalUjian->pluck('id'))
            ->get()
            ->keyBy('soal_ujian_cbt_id');
        $ringkasan = $this->ringkasanKosong();
        $ringkasan['peserta'] = 1;

        DB::transaction(function () use ($peserta, $soalUjian, $jawabanTersimpan, &$ringkasan) {
            foreach ($soalUjian as $relasiSoal) {
                $soal = $relasiSoal->soalCbt;

                if (! $soal || ! in_array($soal->jenis_soal, self::JENIS_OTOMATIS, true)) {
                    $ringkasan['manual']++;

                    continue;
                }

                $jawabanPeserta = $jawabanTersimpan->get($relasiSoal->id);
                $hasil = $this->koreksiSoal($relasiSoal, $soal, $jawabanPeserta?->jawaban);

                JawabanPesertaUjianCbt::updateOrCreate(
                    [
                        'peserta_ujian_cbt_id' => $peserta->id,
                        'soal_ujian_cbt_id' => $relasiSoal->id,
                    ],
                    [
                        'soal_cbt_id' => $relasiSoal->soal_cbt_id,
                        'jawaban' => $jawabanPeserta?->jawaban,
                        'ragu' => (bool) ($jawabanPeserta?->ragu ?? false),
                        'skor' => $hasil['skor'],
                        'benar' => $hasil['benar'],
                        'waktu_dijawab' => $jawabanPeserta?->waktu_dijawab,
                    ],
                );

                $ringkasan['jawaban_dikoreksi']++;
                $ringkasan['skor_total'] += $hasil['skor'];

                if ($hasil['benar']) {
                    $ringkasan['benar']++;
                } else {
                    $ringkasan['salah']++;
                }
            }
        });

        return $ringkasan;
    }

    private function koreksiSoal(SoalUjianCbt $relasiSoal, SoalCbt $soal, ?array $jawaban): array
    {
        $bobot = (float) $relasiSoal->bobot;

        if ($jawaban === null || $jawaban === []) {
            return ['skor' => 0.0, 'benar' => false];
        }

        return match ($soal->jenis_soal) {
            'pilihan_ganda' => $this->koreksiPilihanGanda($soal, $jawaban, $bobot),
            'pilihan_ganda_kompleks' => $this->koreksiPilihanGandaKompleks($soal, $jawaban, $bobot),
            'benar_salah' => $this->koreksiPemetaan($soal, $jawaban, $bobot, normalisasiNilai: 'boolean'),
            'menjodohkan' => $this->koreksiPemetaan($soal, $jawaban, $bobot, normalisasiNilai: 'teks'),
            'isian_singkat' => $this->koreksiTeks($soal, $jawaban, $bobot),
            'numerik' => $this->koreksiNumerik($soal, $jawaban, $bobot),
            default => ['skor' => 0.0, 'benar' => false],
        };
    }

    private function koreksiPilihanGanda(SoalCbt $soal, array $jawaban, float $bobot): array
    {
        $kunci = $this->normalisasiKodeJawaban($this->nilaiPertama($this->ambilKunciJawaban($soal)));
        $jawabanPeserta = $this->normalisasiKodeJawaban($this->nilaiPertama($jawaban));
        $benar = $kunci !== null && $jawabanPeserta === $kunci;

        return ['skor' => $benar ? $bobot : 0.0, 'benar' => $benar];
    }

    private function koreksiPilihanGandaKompleks(SoalCbt $soal, array $jawaban, float $bobot): array
    {
        $kunci = $this->setKodeJawaban((array) $this->ambilKunciJawaban($soal));
        $jawabanPeserta = $this->setKodeJawaban($jawaban);
        $benar = $kunci !== [] && $jawabanPeserta === $kunci;

        return ['skor' => $benar ? $bobot : 0.0, 'benar' => $benar];
    }

    private function koreksiPemetaan(SoalCbt $soal, array $jawaban, float $bobot, string $normalisasiNilai): array
    {
        $kunci = $this->ambilKunciJawaban($soal);

        if (! is_array($kunci) || $kunci === []) {
            return ['skor' => 0.0, 'benar' => false];
        }

        $jumlahButir = count($kunci);
        $jumlahBenar = 0;

        foreach ($kunci as $nomor => $nilaiKunci) {
            $nilaiJawaban = $jawaban[$nomor] ?? $jawaban[(string) $nomor] ?? null;

            if ($normalisasiNilai === 'boolean') {
                $cocok = $this->normalisasiBooleanJawaban($nilaiJawaban) === $this->normalisasiBooleanJawaban($nilaiKunci);
            } else {
                $cocok = $this->normalisasiTeksJawaban($nilaiJawaban) === $this->normalisasiTeksJawaban($nilaiKunci);
            }

            if ($cocok) {
                $jumlahBenar++;
            }
        }

        $benarPenuh = $jumlahBenar === $jumlahButir;
        $skor = $jumlahButir > 0 ? round($bobot * ($jumlahBenar / $jumlahButir), 2) : 0.0;

        return ['skor' => $skor, 'benar' => $benarPenuh];
    }

    private function koreksiTeks(SoalCbt $soal, array $jawaban, float $bobot): array
    {
        $jawabanPeserta = $this->normalisasiTeksJawaban($this->nilaiPertama($jawaban));
        $kunci = collect($this->daftarKunciTeks($soal))
            ->map(fn ($item) => $this->normalisasiTeksJawaban($item))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $benar = $jawabanPeserta !== '' && in_array($jawabanPeserta, $kunci, true);

        return ['skor' => $benar ? $bobot : 0.0, 'benar' => $benar];
    }

    private function koreksiNumerik(SoalCbt $soal, array $jawaban, float $bobot): array
    {
        $jawabanPeserta = $this->normalisasiAngka($this->nilaiPertama($jawaban));
        $benar = false;

        foreach ($this->daftarKunciTeks($soal) as $kunci) {
            $angkaKunci = $this->normalisasiAngka($kunci);

            if ($jawabanPeserta !== null && $angkaKunci !== null && abs($jawabanPeserta - $angkaKunci) <= 0.0001) {
                $benar = true;
                break;
            }
        }

        if (! $benar) {
            return $this->koreksiTeks($soal, $jawaban, $bobot);
        }

        return ['skor' => $bobot, 'benar' => true];
    }

    private function ambilSoalUjian(UjianCbt $ujianCbt, PesertaUjianCbt $peserta)
    {
        $soal = $ujianCbt->soalUjianCbt()
            ->with('soalCbt')
            ->get();

        return $this->pengacakPenyajianCbt
            ->urutkanSoal($ujianCbt, $peserta, $soal)
            ->take($ujianCbt->jumlah_soal);
    }

    private function ambilKunciJawaban(SoalCbt $soal): mixed
    {
        $kunci = $soal->kunci_jawaban;

        if (is_array($kunci) && array_key_exists('jawaban', $kunci)) {
            return $kunci['jawaban'];
        }

        return $kunci;
    }

    private function daftarKunciTeks(SoalCbt $soal): array
    {
        $kunci = $this->ambilKunciJawaban($soal);

        if (is_array($kunci)) {
            return collect($kunci)
                ->flatten()
                ->map(fn ($item) => trim((string) $item))
                ->filter()
                ->values()
                ->all();
        }

        return collect(preg_split('/\s*\|\s*/', (string) $kunci) ?: [])
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values()
            ->all();
    }

    private function setKodeJawaban(array $jawaban): array
    {
        return collect($jawaban)
            ->map(fn ($item) => $this->normalisasiKodeJawaban($item))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function normalisasiKodeJawaban(mixed $jawaban): ?string
    {
        $jawaban = mb_strtoupper(trim((string) $jawaban));

        return $jawaban === '' ? null : $jawaban;
    }

    private function normalisasiBooleanJawaban(mixed $jawaban): ?bool
    {
        if (is_bool($jawaban)) {
            return $jawaban;
        }

        $jawaban = mb_strtolower(trim((string) $jawaban));

        return match ($jawaban) {
            '1', 'true', 'benar', 'b', 'ya', 'y' => true,
            '0', 'false', 'salah', 's', 'tidak', 't' => false,
            default => null,
        };
    }

    private function normalisasiTeksJawaban(mixed $jawaban): string
    {
        $jawaban = mb_strtolower(trim((string) $jawaban));
        $jawaban = preg_replace('/[^\pL\pN]+/u', ' ', $jawaban) ?: '';

        return trim(preg_replace('/\s+/', ' ', $jawaban) ?: '');
    }

    private function normalisasiAngka(mixed $jawaban): ?float
    {
        $jawaban = trim(str_replace(',', '.', (string) $jawaban));

        return is_numeric($jawaban) ? (float) $jawaban : null;
    }

    private function nilaiPertama(mixed $nilai): mixed
    {
        if (is_array($nilai)) {
            return collect($nilai)->first();
        }

        return $nilai;
    }

    private function ringkasanKosong(): array
    {
        return [
            'peserta' => 0,
            'jawaban_dikoreksi' => 0,
            'benar' => 0,
            'salah' => 0,
            'manual' => 0,
            'skor_total' => 0,
        ];
    }
}
