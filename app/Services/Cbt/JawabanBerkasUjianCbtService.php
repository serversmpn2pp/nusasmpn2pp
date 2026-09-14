<?php

namespace App\Services\Cbt;

use App\Models\JawabanPesertaUjianCbt;
use App\Models\PesertaUjianCbt;
use App\Models\SoalUjianCbt;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class JawabanBerkasUjianCbtService
{
    public const MAKSIMAL_KILOBYTE = 10240;

    public const EKSTENSI = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];

    public function simpan(
        PesertaUjianCbt $peserta,
        SoalUjianCbt $soalUjian,
        UploadedFile $berkas,
        bool $ragu,
    ): JawabanPesertaUjianCbt {
        abort_unless($soalUjian->soalCbt?->jenis_soal === 'upload_file', 422, 'Soal ini bukan soal unggahan berkas.');

        $jawabanLama = JawabanPesertaUjianCbt::query()
            ->where('peserta_ujian_cbt_id', $peserta->id)
            ->where('soal_ujian_cbt_id', $soalUjian->id)
            ->first();
        $lokasiLama = $jawabanLama?->lokasi_file;
        $ekstensi = mb_strtolower($berkas->extension() ?: $berkas->getClientOriginalExtension());
        $namaTersimpan = Str::uuid().($ekstensi !== '' ? '.'.$ekstensi : '');
        $direktori = "jawaban-ujian-cbt/{$peserta->ujian_cbt_id}/{$peserta->id}";
        $lokasiBaru = $berkas->storeAs($direktori, $namaTersimpan, 'local');

        abort_unless($lokasiBaru, 500, 'Berkas jawaban belum dapat disimpan.');

        try {
            $jawaban = JawabanPesertaUjianCbt::updateOrCreate(
                [
                    'peserta_ujian_cbt_id' => $peserta->id,
                    'soal_ujian_cbt_id' => $soalUjian->id,
                ],
                [
                    'soal_cbt_id' => $soalUjian->soal_cbt_id,
                    'jawaban' => ['berkas' => basename($berkas->getClientOriginalName())],
                    'lokasi_file' => $lokasiBaru,
                    'nama_file_asli' => basename($berkas->getClientOriginalName()),
                    'tipe_file' => $berkas->getMimeType(),
                    'ukuran_file' => $berkas->getSize(),
                    'ragu' => $ragu,
                    'skor' => null,
                    'benar' => null,
                    'waktu_dijawab' => now(),
                ],
            );
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($lokasiBaru);

            throw $exception;
        }

        if ($lokasiLama && $lokasiLama !== $lokasiBaru) {
            Storage::disk('local')->delete($lokasiLama);
        }

        return $jawaban;
    }

    public function metadata(?JawabanPesertaUjianCbt $jawaban): ?array
    {
        if (! $jawaban?->lokasi_file) {
            return null;
        }

        return [
            'nama' => $jawaban->nama_file_asli,
            'tipe' => $jawaban->tipe_file,
            'ukuran' => (int) $jawaban->ukuran_file,
            'ukuran_label' => $this->formatUkuran((int) $jawaban->ukuran_file),
        ];
    }

    private function formatUkuran(int $byte): string
    {
        if ($byte >= 1024 * 1024) {
            return number_format($byte / (1024 * 1024), 2, ',', '.').' MB';
        }

        return number_format(max(0, $byte) / 1024, 1, ',', '.').' KB';
    }
}
