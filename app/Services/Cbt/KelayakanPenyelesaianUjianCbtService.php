<?php

namespace App\Services\Cbt;

use App\Models\JawabanPesertaUjianCbt;
use App\Models\PesertaUjianCbt;
use App\Models\SoalUjianCbt;
use Illuminate\Support\Collection;

class KelayakanPenyelesaianUjianCbtService
{
    public const BATAS_PENGUMPULAN_DETIK = 15 * 60;

    public function ringkasan(PesertaUjianCbt $peserta, Collection $soalUjian, int $sisaDetik): array
    {
        $jawaban = $peserta->jawabanPesertaUjianCbt()
            ->whereIn('soal_ujian_cbt_id', $soalUjian->pluck('id'))
            ->get()
            ->keyBy('soal_ujian_cbt_id');
        $lengkap = 0;
        $sebagian = 0;

        foreach ($soalUjian as $relasiSoal) {
            $status = $this->statusJawaban($relasiSoal, $jawaban->get($relasiSoal->id));
            $lengkap += $status['lengkap'] ? 1 : 0;
            $sebagian += $status['sebagian'] ? 1 : 0;
        }

        $jumlahSoal = $soalUjian->count();
        $belumLengkap = max(0, $jumlahSoal - $lengkap);
        $dalamBatasAkhir = $sisaDetik <= self::BATAS_PENGUMPULAN_DETIK;
        $semuaLengkap = $jumlahSoal > 0 && $belumLengkap === 0;

        return [
            'jumlah_soal' => $jumlahSoal,
            'lengkap' => $lengkap,
            'belum_lengkap' => $belumLengkap,
            'sebagian' => $sebagian,
            'ragu' => $jawaban->where('ragu', true)->count(),
            'sisa_detik' => max(0, $sisaDetik),
            'batas_pengumpulan_detik' => self::BATAS_PENGUMPULAN_DETIK,
            'semua_lengkap' => $semuaLengkap,
            'dalam_batas_akhir' => $dalamBatasAkhir,
            'boleh_selesai' => $semuaLengkap || $dalamBatasAkhir,
        ];
    }

    public function pesanPenolakan(): string
    {
        return 'Ujian belum dapat dikumpulkan. Lengkapi seluruh soal atau tunggu hingga 15 menit terakhir sebelum waktu ujian berakhir.';
    }

    private function statusJawaban(SoalUjianCbt $relasiSoal, ?JawabanPesertaUjianCbt $jawaban): array
    {
        $soal = $relasiSoal->soalCbt;

        if ($soal?->jenis_soal === 'upload_file') {
            return [
                'lengkap' => filled($jawaban?->lokasi_file),
                'sebagian' => false,
            ];
        }

        $nilai = is_array($jawaban?->jawaban) ? $jawaban->jawaban : [];

        if ($soal?->jenis_soal === 'benar_salah') {
            return $this->statusJawabanBerbutir($soal->opsi['pernyataan'] ?? [], $nilai);
        }

        if ($soal?->jenis_soal === 'menjodohkan') {
            return $this->statusJawabanBerbutir($soal->opsi['pasangan'] ?? [], $nilai);
        }

        return [
            'lengkap' => collect($nilai)->contains(fn ($item) => filled($item)),
            'sebagian' => false,
        ];
    }

    private function statusJawabanBerbutir(array $butir, array $jawaban): array
    {
        $nomorButir = collect($butir)
            ->values()
            ->map(fn ($item, int $index) => (string) ($item['nomor'] ?? $index + 1));

        if ($nomorButir->isEmpty()) {
            $terisi = collect($jawaban)->filter(fn ($item) => filled($item))->count();

            return ['lengkap' => $terisi > 0, 'sebagian' => false];
        }

        $terisi = $nomorButir->filter(function (string $nomor) use ($jawaban) {
            $nilai = $jawaban[$nomor] ?? $jawaban[(int) $nomor] ?? null;

            return filled($nilai);
        })->count();

        return [
            'lengkap' => $terisi === $nomorButir->count(),
            'sebagian' => $terisi > 0 && $terisi < $nomorButir->count(),
        ];
    }
}
