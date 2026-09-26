<?php

namespace App\Services\Cbt;

use App\Models\SoalUjianCbt;

class RincianPemetaanSoalCbt
{
    public function __construct(private readonly KoreksiOtomatisCbtService $koreksi) {}

    public function siapkan(SoalUjianCbt $relasi): array
    {
        $soal = $relasi->soalCbt;
        $jenis = $soal?->jenis_soal;
        $hasil = [
            'tersedia' => false,
            'jenis' => $jenis,
            'butir' => [],
            'jawaban_tidak_dikenali' => 0,
            'kunci_valid' => true,
            'minimal_peserta' => TingkatKesukaranSoalCbt::MINIMAL_HASIL,
        ];
        if (! in_array($jenis, ['benar_salah', 'menjodohkan'], true)) {
            return $hasil;
        }

        $bs = $jenis === 'benar_salah';
        $opsi = $soal->opsi ?? [];
        $kunci = $soal->kunci_jawaban ?? [];
        $kunci = is_array($kunci) && array_key_exists('jawaban', $kunci) ? $kunci['jawaban'] : $kunci;
        $kunci = is_array($kunci) ? $kunci : [];
        $pilihan = $bs ? [
            'benar' => ['teks' => 'Benar', 'media_key' => null, 'pengecoh_tambahan' => false],
            'salah' => ['teks' => 'Salah', 'media_key' => null, 'pengecoh_tambahan' => false],
        ] : $this->pilihanPasangan($opsi);

        foreach ($opsi[$bs ? 'pernyataan' : 'pasangan'] ?? [] as $index => $butir) {
            $nomor = (string) ($butir['nomor'] ?? $index + 1);
            $nilaiKunci = $kunci[$nomor] ?? null;
            $normalKunci = is_scalar($nilaiKunci)
                ? ($bs ? $this->koreksi->normalisasiBooleanJawaban($nilaiKunci) : $this->koreksi->normalisasiTeksJawaban($nilaiKunci))
                : null;
            $kunciValid = $bs ? $normalKunci !== null
                : ($normalKunci !== null && $normalKunci !== '' && isset($pilihan[$this->identitasTeks($nilaiKunci)]));
            $hasil['kunci_valid'] = $hasil['kunci_valid'] && $kunciValid;
            $pilihanButir = [];
            foreach ($pilihan as $id => $item) {
                $cocok = $bs ? ($id === 'benar') === $normalKunci
                    : $this->koreksi->normalisasiTeksJawaban($item['teks']) === $normalKunci;
                $pilihanButir[$id] = [...$item, 'kunci' => $kunciValid ? $cocok : null, 'dipilih' => 0];
            }
            $hasil['butir'][$nomor] = [
                'nomor' => $nomor,
                'teks' => (string) ($butir[$bs ? 'teks' : 'kiri'] ?? ''),
                'media_key' => $butir[$bs ? 'media_key' : 'media_kiri_key'] ?? null,
                'kunci' => ! $kunciValid ? null : ($bs ? ($normalKunci ? 'Benar' : 'Salah') : (string) $nilaiKunci),
                'kunci_valid' => $kunciValid,
                'benar' => 0,
                'salah' => 0,
                'kosong' => 0,
                'tidak_dikenali' => 0,
                'pilihan' => $pilihanButir,
            ];
        }
        $hasil['tersedia'] = $hasil['butir'] !== [];

        return $hasil;
    }

    public function catat(array &$rincian, ?array $jawaban): void
    {
        if (! $rincian['tersedia']) {
            return;
        }

        $tidakDikenali = false;
        foreach ($rincian['butir'] as $nomor => &$butir) {
            $nilai = $jawaban[$nomor] ?? null;
            if ($this->kosong($nilai)) {
                $butir['kosong']++;

                continue;
            }

            $id = null;
            if (is_scalar($nilai)) {
                if ($rincian['jenis'] === 'benar_salah') {
                    $boolean = $this->koreksi->normalisasiBooleanJawaban($nilai);
                    $id = $boolean === null ? null : ($boolean ? 'benar' : 'salah');
                } else {
                    $id = $this->identitasTeks($nilai);
                }
            }

            if ($id === null || ! isset($butir['pilihan'][$id])) {
                $butir['tidak_dikenali']++;
                $tidakDikenali = true;

                continue;
            }

            $butir['pilihan'][$id]['dipilih']++;
            if ($butir['kunci_valid']) {
                $butir[$butir['pilihan'][$id]['kunci'] ? 'benar' : 'salah']++;
            }
        }
        unset($butir);

        foreach ($jawaban ?? [] as $nomor => $nilai) {
            if (! array_key_exists($nomor, $rincian['butir']) && ! $this->kosong($nilai)) {
                $tidakDikenali = true;
            }
        }
        if ($tidakDikenali) {
            $rincian['jawaban_tidak_dikenali']++;
        }
    }

    public function ringkas(array $rincian, int $disajikan): array
    {
        foreach ($rincian['butir'] as &$butir) {
            $butir['persen_benar'] = $disajikan > 0 && $butir['kunci_valid']
                ? round($butir['benar'] / $disajikan * 100, 2) : null;
            if (! $butir['kunci_valid']) {
                $butir['benar'] = null;
                $butir['salah'] = null;
            }
            foreach ($butir['pilihan'] as &$pilihan) {
                $pilihan['persen'] = $disajikan > 0 ? round($pilihan['dipilih'] / $disajikan * 100, 2) : null;
            }
            unset($pilihan);
            $butir['pilihan'] = array_values($butir['pilihan']);
        }
        unset($butir);
        $rincian['butir'] = array_values($rincian['butir']);

        return $rincian;
    }

    private function pilihanPasangan(array $opsi): array
    {
        $pilihan = [];
        foreach ($opsi['pasangan'] ?? [] as $item) {
            $teks = trim((string) ($item['kanan'] ?? ''));
            $id = $this->identitasTeks($teks);
            if ($teks !== '' && ! isset($pilihan[$id])) {
                $pilihan[$id] = ['teks' => $teks, 'media_key' => $item['media_kanan_key'] ?? null, 'pengecoh_tambahan' => false];
            }
        }
        foreach ($opsi['pengecoh'] ?? [] as $teks) {
            if (! is_scalar($teks) || trim((string) $teks) === '') {
                continue;
            }
            $id = $this->identitasTeks($teks);
            if (isset($pilihan[$id])) {
                continue;
            }
            $media = collect($opsi['pengecoh_media'] ?? [])->first(fn ($item) => $this->identitasTeks($item['teks'] ?? '') === $id);
            $pilihan[$id] = ['teks' => trim((string) $teks), 'media_key' => $media['media_key'] ?? null, 'pengecoh_tambahan' => true];
        }

        return $pilihan;
    }

    private function identitasTeks(mixed $nilai): string
    {
        // Sama dengan identitas opsi menjodohkan di layar siswa; jangan gabungkan rumus berbeda.
        return mb_strtolower(trim((string) $nilai));
    }

    private function kosong(mixed $nilai): bool
    {
        return $nilai === null || $nilai === [] || (is_string($nilai) && trim($nilai) === '');
    }
}
