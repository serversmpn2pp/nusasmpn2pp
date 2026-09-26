<?php

namespace App\Services\Cbt;

use App\Models\SoalUjianCbt;

class RincianPilihanSoalCbt
{
    // Pengaman sampel kecil, bukan ukuran kecukupan statistik.
    public const MINIMAL_PESERTA = 10;

    public function pilihan(SoalUjianCbt $soal): array
    {
        if (! in_array($soal->soalCbt?->jenis_soal, ['pilihan_ganda', 'pilihan_ganda_kompleks'], true)) {
            return [];
        }

        $opsi = $soal->soalCbt->opsi ?? [];
        $pilihan = [];

        foreach ($opsi['pilihan'] ?? $opsi as $kode => $item) {
            $kode = is_array($item) ? ($item['kode'] ?? $kode) : $kode;
            $teks = is_array($item) ? ($item['teks'] ?? $item['label'] ?? '') : $item;
            $kode = mb_strtoupper(trim((string) $kode));
            if ($kode !== '' && is_scalar($teks) && trim((string) $teks) !== '') {
                $pilihan[$kode] = (string) $teks;
            }
        }

        ksort($pilihan);

        return $pilihan;
    }

    public function hitung(SoalUjianCbt $soal, array $jumlahPilihan, int $disajikan, int $jawabanTidakDikenali = 0): array
    {
        $pilihan = $this->pilihan($soal);
        $jenis = $soal->soalCbt?->jenis_soal;
        $dataKunci = $soal->soalCbt?->kunci_jawaban;
        $dataKunci = is_array($dataKunci) && array_key_exists('jawaban', $dataKunci) ? $dataKunci['jawaban'] : $dataKunci;
        $dataKunci = (array) $dataKunci;
        $kunci = collect($dataKunci)->filter(fn ($nilai) => is_scalar($nilai))
            ->map(fn ($nilai) => mb_strtoupper(trim((string) $nilai)))
            ->filter(fn ($nilai) => $nilai !== '')->unique()->values()->all();
        $kunciValid = $kunci !== []
            && count(array_filter($dataKunci, 'is_scalar')) === count($dataKunci)
            && array_diff($kunci, array_keys($pilihan)) === []
            && ($jenis !== 'pilihan_ganda' || count($kunci) === 1);
        $opsi = [];

        foreach ($pilihan as $kode => $teks) {
            $jumlah = $jumlahPilihan[$kode] ?? 0;
            $persen = $disajikan > 0 ? $jumlah / $disajikan * 100 : null;
            $adalahKunci = $kunciValid ? in_array((string) $kode, $kunci, true) : null;
            $indikator = match (true) {
                ! $kunciValid || $jawabanTidakDikenali > 0 => 'periksa_data',
                $adalahKunci => 'kunci',
                $disajikan === 0 => 'belum_ada_data',
                $disajikan < self::MINIMAL_PESERTA => 'sampel_terbatas',
                $jenis === 'pilihan_ganda_kompleks' => 'sebaran_pgk',
                $jumlah === 0 => 'belum_dipilih',
                $persen < 5 => 'jarang_dipilih',
                default => 'dipilih',
            };
            $opsi[] = [
                'kode' => (string) $kode,
                'teks' => $teks,
                'kunci' => $adalahKunci,
                'dipilih' => $jumlah,
                'tidak_dipilih' => max(0, $disajikan - $jumlah),
                'persen' => $persen === null ? null : round($persen, 2),
                'indikator' => $indikator,
            ];
        }

        return [
            'tersedia' => $pilihan !== [],
            'kunci_valid' => $kunciValid,
            'opsi' => $opsi,
            'perlu_ditinjau' => count(array_filter($opsi, fn ($item) => in_array($item['indikator'], ['belum_dipilih', 'jarang_dipilih'], true))),
            'jawaban_tidak_dikenali' => $jawabanTidakDikenali,
            'minimal_peserta' => self::MINIMAL_PESERTA,
        ];
    }
}
