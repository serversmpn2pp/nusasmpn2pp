<?php

namespace App\Services\Cbt;

use App\Models\PesertaUjianCbt;
use App\Models\SoalUjianCbt;
use App\Models\UjianCbt;
use Illuminate\Support\Collection;

class PengacakPenyajianCbt
{
    public function urutkanSoal(UjianCbt $ujian, PesertaUjianCbt $peserta, Collection $soal): Collection
    {
        $terurut = $soal
            ->sortBy(fn (SoalUjianCbt $item) => sprintf('%05d|%08d', $item->nomor_urut ?? 9999, $item->id))
            ->values();

        if (! $ujian->acak_soal) {
            return $terurut;
        }

        return $terurut
            ->sortBy(fn (SoalUjianCbt $item) => $this->kunciAcak(
                $ujian,
                $peserta,
                'soal',
                (string) $item->id,
            ))
            ->values();
    }

    public function pilihanJawaban(
        UjianCbt $ujian,
        PesertaUjianCbt $peserta,
        SoalUjianCbt $relasiSoal,
    ): Collection {
        $opsi = $relasiSoal->soalCbt?->opsi ?? [];
        $jenisSoal = $relasiSoal->soalCbt?->jenis_soal;

        if ($jenisSoal === 'menjodohkan') {
            return $this->pilihanMenjodohkan($ujian, $peserta, $relasiSoal, $opsi['pasangan'] ?? []);
        }

        $pilihan = $opsi['pilihan'] ?? $opsi;
        $pilihan = collect($pilihan)
            ->mapWithKeys(function ($item, $key) {
                if (is_array($item)) {
                    $kode = $item['kode'] ?? $key;
                    $teks = $item['teks'] ?? $item['label'] ?? '';
                } else {
                    $kode = $key;
                    $teks = $item;
                }

                return [mb_strtoupper((string) $kode) => (string) $teks];
            })
            ->filter(fn ($teks) => filled($teks))
            ->sortKeys();

        if (! $ujian->acak_jawaban
            || ! in_array($relasiSoal->soalCbt?->jenis_soal, ['pilihan_ganda', 'pilihan_ganda_kompleks'], true)) {
            return $pilihan;
        }

        return $pilihan->sortBy(
            fn ($teks, $kode) => $this->kunciAcak(
                $ujian,
                $peserta,
                'pilihan:'.$relasiSoal->id,
                (string) $kode,
            ),
        );
    }

    private function pilihanMenjodohkan(
        UjianCbt $ujian,
        PesertaUjianCbt $peserta,
        SoalUjianCbt $relasiSoal,
        array $pasangan,
    ): Collection {
        $pilihan = collect($pasangan)
            ->map(fn ($item, $index) => [
                'identitas' => (string) ($item['nomor'] ?? $index + 1),
                'teks' => trim((string) ($item['kanan'] ?? '')),
            ])
            ->filter(fn ($item) => filled($item['teks']))
            ->unique(fn ($item) => mb_strtolower($item['teks']))
            ->values();

        $identitasAwal = $pilihan->pluck('identitas')->all();
        $identitasPeserta = $ujian->acak_jawaban ? (string) $peserta->id : 'tetap';
        $pilihan = $pilihan->sortBy(fn ($item) => hash('sha256', implode('|', [
            'nusa-cbt',
            $ujian->id,
            $identitasPeserta,
            'pasangan:'.$relasiSoal->id,
            $item['identitas'],
        ])))->values();

        if ($pilihan->count() > 1 && $pilihan->pluck('identitas')->all() === $identitasAwal) {
            $pilihan = $pilihan->slice(1)->concat($pilihan->take(1))->values();
        }

        return $pilihan->mapWithKeys(fn ($item, $index) => [
            chr(65 + $index) => $item['teks'],
        ]);
    }

    private function kunciAcak(
        UjianCbt $ujian,
        PesertaUjianCbt $peserta,
        string $jenis,
        string $identitas,
    ): string {
        return hash('sha256', implode('|', [
            'nusa-cbt',
            $ujian->id,
            $peserta->id,
            $jenis,
            $identitas,
        ]));
    }
}
