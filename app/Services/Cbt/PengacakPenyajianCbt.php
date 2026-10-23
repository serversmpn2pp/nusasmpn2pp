<?php

namespace App\Services\Cbt;

use App\Models\PesertaUjianCbt;
use App\Models\SoalUjianCbt;
use App\Models\UjianCbt;
use Illuminate\Contracts\Encryption\Encrypter;
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
            return $this->pilihanMenjodohkan(
                $ujian,
                $peserta,
                $relasiSoal,
                $opsi['pasangan'] ?? [],
                $opsi['pengecoh'] ?? [],
            );
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
        array $pengecoh,
    ): Collection {
        $pilihanBenar = collect($pasangan)
            ->map(fn ($item) => trim((string) ($item['kanan'] ?? '')));
        $pilihanPengecoh = collect($pengecoh)
            ->map(fn ($item) => trim((string) $item));
        $pilihan = $pilihanBenar
            ->concat($pilihanPengecoh)
            ->filter(fn ($teks) => filled($teks))
            ->unique(fn ($teks) => mb_strtolower($teks))
            ->values();

        // Urutan hanya bergantung pada bank teks, bukan nomor/pemilik pasangan benar.
        // ID publik saja tidak cukup untuk menghitung urutan tanpa rahasia server.
        $kunciServer = app(Encrypter::class)->getKey();
        $identitasPeserta = $ujian->acak_jawaban ? (string) $peserta->id : 'tetap';
        $pilihan = $pilihan->sortBy(fn ($teks) => hash_hmac('sha256', implode('|', [
            'nusa-cbt-menjodohkan-v2',
            $ujian->id,
            $identitasPeserta,
            $relasiSoal->id,
            mb_strtolower($teks),
        ]), $kunciServer))->values();

        // Jangan paksa berbeda dari urutan awal: dua opsi akan selalu tertukar dan bocor.
        // Teks tetap utuh karena autosave dan koreksi menjodohkan memakai teks, bukan huruf.
        return $pilihan->mapWithKeys(fn ($teks, $index) => [
            chr(65 + $index) => $teks,
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
