<?php

namespace App\Services\Pembinaan;

use App\Models\BuktiLaporanPembinaanSiswa;
use App\Models\LaporanPembinaanSiswa;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class SimpanBuktiLaporanService
{
    public function __construct(private CatatRiwayatPembinaanService $riwayat)
    {
    }

    /** @param array<int, UploadedFile> $daftarFile */
    public function simpanBanyak(LaporanPembinaanSiswa $laporan, array $daftarFile, ?string $keterangan, ?int $penggunaId): int
    {
        return $this->simpanBanyakUntukLaporan([$laporan], $daftarFile, $keterangan, $penggunaId);
    }

    /**
     * Satu file fisik dapat ditautkan ke beberapa laporan dalam kejadian kolektif.
     *
     * @param iterable<int, LaporanPembinaanSiswa> $daftarLaporan
     * @param array<int, UploadedFile> $daftarFile
     */
    public function simpanBanyakUntukLaporan(
        iterable $daftarLaporan,
        array $daftarFile,
        ?string $keterangan,
        ?int $penggunaId,
    ): int
    {
        if ($daftarFile === []) {
            return 0;
        }

        $laporan = collect($daftarLaporan)->filter()->values();
        if ($laporan->isEmpty()) {
            return 0;
        }

        $lokasiBaru = [];
        $folder = $laporan->count() === 1
            ? "pembinaan/{$laporan->first()->id}/bukti"
            : 'pembinaan/kelompok/'.Str::uuid().'/bukti';

        try {
            return DB::transaction(function () use ($laporan, $daftarFile, $keterangan, $penggunaId, $folder, &$lokasiBaru) {
                foreach ($daftarFile as $file) {
                    $lokasi = $file->store($folder, 'local');
                    if (! $lokasi) {
                        throw ValidationException::withMessages(['bukti_laporan' => 'Salah satu bukti gagal disimpan. Silakan coba kembali.']);
                    }

                    $lokasiBaru[] = $lokasi;
                    foreach ($laporan as $item) {
                        $item->buktiLaporanPembinaanSiswa()->create([
                            'jenis' => str_starts_with((string) $file->getMimeType(), 'image/') ? 'foto' : 'dokumen',
                            'nama_file_asli' => $file->getClientOriginalName(),
                            'lokasi_file' => $lokasi,
                            'tipe_file' => $file->getMimeType(),
                            'ukuran_file' => $file->getSize(),
                            'keterangan' => filled($keterangan) ? trim($keterangan) : null,
                            'diunggah_oleh_pengguna_id' => $penggunaId,
                            'diunggah_pada' => now(),
                        ]);
                    }
                }

                $jumlah = count($daftarFile);
                foreach ($laporan as $item) {
                    $this->riwayat->catat(
                        $item,
                        'bukti_ditambahkan',
                        'Bukti pendukung ditambahkan',
                        $jumlah.' file bukti diunggah.',
                        $item->status_verifikasi,
                        $item->status_verifikasi,
                        $penggunaId,
                        ['jumlah_file' => $jumlah],
                    );
                }

                return $jumlah;
            });
        } catch (Throwable $e) {
            foreach ($lokasiBaru as $lokasi) {
                Storage::disk('local')->delete($lokasi);
            }
            throw $e;
        }
    }

    public function hapus(BuktiLaporanPembinaanSiswa $bukti, ?int $penggunaId): void
    {
        DB::transaction(function () use ($bukti, $penggunaId) {
            $laporan = $bukti->laporanPembinaanSiswa;
            $this->riwayat->catat(
                $laporan,
                'bukti_dihapus',
                'Bukti pendukung dihapus',
                'File "' . $bukti->nama_file_asli . '" dihapus.',
                $laporan->status_verifikasi,
                $laporan->status_verifikasi,
                $penggunaId,
            );
            $lokasi = $bukti->lokasi_file;
            $bukti->delete();
            if (! BuktiLaporanPembinaanSiswa::where('lokasi_file', $lokasi)->exists()) {
                Storage::disk('local')->delete($lokasi);
            }
        });
    }
}
