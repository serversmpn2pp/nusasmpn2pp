<?php

namespace App\Services\Mobile;

use App\Models\SoalCbt;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/** One student-facing projection for exam delivery and authorized teacher previews. */
class PenyajianSoalMobileService
{
    public function konten(?SoalCbt $soal, int $id, int $nomor, Collection $pilihan): array
    {
        return [
            'id' => (int) $id,
            'nomor' => $nomor,
            'jenis' => $soal?->jenis_soal ?? 'uraian',
            'label_jenis' => $soal?->labelJenis() ?? 'Soal',
            'stimulus' => $soal?->stimulus,
            'stimulus_media' => $this->mediaKonten($soal, 'stimulus'),
            'pertanyaan' => $soal?->pertanyaan ?? 'Soal tidak ditemukan.',
            'media' => $this->media($soal),
            'pilihan' => $soal && in_array($soal->jenis_soal, ['pilihan_ganda', 'pilihan_ganda_kompleks', 'menjodohkan'], true)
                ? $pilihan
                    ->map(fn ($teks, $kode) => [
                        'kode' => (string) $kode,
                        'teks' => (string) $teks,
                        'media' => $this->mediaPilihan($soal, (string) $kode, (string) $teks),
                    ])
                    ->values()
                    ->map(fn (array $pilihan, int $urutan) => [
                        ...$pilihan,
                        'label' => chr(65 + $urutan),
                    ])
                : [],
            'pernyataan' => collect($soal?->opsi['pernyataan'] ?? [])->map(fn ($item, $index) => [
                'nomor' => (string) ($item['nomor'] ?? $index + 1),
                'teks' => (string) ($item['teks'] ?? '-'),
                'media' => $this->mediaKonten($soal, $item['media_key'] ?? null),
            ])->values(),
            'pasangan' => collect($soal?->opsi['pasangan'] ?? [])->map(fn ($item, $index) => [
                'nomor' => (string) ($item['nomor'] ?? $index + 1),
                'kiri' => (string) ($item['kiri'] ?? '-'),
                'media' => $this->mediaKonten($soal, $item['media_kiri_key'] ?? null),
            ])->values(),
        ];
    }

    public function media(?SoalCbt $soal): array
    {
        return $this->mediaBundle($soal?->media ?? []);
    }

    public function mediaKonten(?SoalCbt $soal, mixed $kunci): array
    {
        if (! is_string($kunci) || $kunci === '') {
            return $this->mediaBundle([]);
        }

        return $this->mediaBundle(data_get($soal?->media, 'konten.'.$kunci, []));
    }

    public function mediaPilihan(?SoalCbt $soal, string $kode, string $teks): array
    {
        if (in_array($soal?->jenis_soal, ['pilihan_ganda', 'pilihan_ganda_kompleks'], true)) {
            return $this->mediaKonten($soal, 'pilihan_'.mb_strtoupper($kode));
        }

        $normal = mb_strtolower(trim($teks));
        $pasangan = collect($soal?->opsi['pasangan'] ?? [])->first(
            fn ($item) => mb_strtolower(trim((string) ($item['kanan'] ?? ''))) === $normal,
        );
        if ($pasangan) {
            return $this->mediaKonten($soal, $pasangan['media_kanan_key'] ?? null);
        }

        $pengecoh = collect($soal?->opsi['pengecoh_media'] ?? [])->first(
            fn ($item) => mb_strtolower(trim((string) ($item['teks'] ?? ''))) === $normal,
        );

        return $this->mediaKonten($soal, $pengecoh['media_key'] ?? null);
    }

    private function mediaBundle(array $media): array
    {
        $path = data_get($media, 'gambar.path');
        $keterangan = data_get($media, 'gambar.keterangan');
        if (blank($keterangan) && filled(data_get($media, 'gambar.alt')) && data_get($media, 'gambar.alt') !== 'Gambar pendukung soal') {
            $keterangan = data_get($media, 'gambar.alt');
        }

        return [
            'gambar' => $path ? [
                'url' => url(Storage::disk('public')->url($path)),
                'alt' => data_get($media, 'gambar.alt'),
                'keterangan' => $keterangan,
            ] : null,
            'tabel' => data_get($media, 'tabel'),
            'rumus' => data_get($media, 'rumus'),
        ];
    }
}
