<?php

namespace App\Services\Pembinaan;

use App\Models\BuktiPelaksanaanSanksi;
use App\Models\SanksiPoinSiswa;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class SimpanBuktiPelaksanaanSanksiService
{
    public function __construct(private CatatRiwayatSanksiPoinService $riwayat) {}

    /** @param array<int, UploadedFile> $daftarFile */
    public function simpanBanyak(SanksiPoinSiswa $sanksi, array $daftarFile, ?string $keterangan, ?int $penggunaId): int
    {
        if ($daftarFile === []) {
            return 0;
        }

        $lokasiBaru = [];

        try {
            return DB::transaction(function () use ($sanksi, $daftarFile, $keterangan, $penggunaId, &$lokasiBaru) {
                foreach ($daftarFile as $file) {
                    $lokasi = $file->store("pembinaan/sanksi/{$sanksi->id}/bukti", 'local');
                    if (! $lokasi) {
                        throw ValidationException::withMessages([
                            'bukti_sanksi' => 'Salah satu bukti gagal disimpan. Silakan coba kembali.',
                        ]);
                    }

                    $lokasiBaru[] = $lokasi;
                    $sanksi->buktiPelaksanaanSanksi()->create([
                        'nama_file_asli' => $file->getClientOriginalName(),
                        'lokasi_file' => $lokasi,
                        'tipe_file' => $file->getMimeType(),
                        'ukuran_file' => $file->getSize(),
                        'keterangan' => filled($keterangan) ? trim($keterangan) : null,
                        'diunggah_oleh_pengguna_id' => $penggunaId,
                        'diunggah_pada' => now(),
                    ]);
                }

                $jumlah = count($daftarFile);
                $this->riwayat->catat(
                    $sanksi,
                    'bukti_ditambahkan',
                    'Bukti pelaksanaan ditambahkan',
                    $sanksi->status,
                    $sanksi->status,
                    $jumlah.' file bukti diunggah.',
                    $penggunaId,
                    ['jumlah_file' => $jumlah],
                );

                return $jumlah;
            });
        } catch (Throwable $e) {
            foreach ($lokasiBaru as $lokasi) {
                Storage::disk('local')->delete($lokasi);
            }
            throw $e;
        }
    }

    public function hapus(BuktiPelaksanaanSanksi $bukti, ?int $penggunaId): void
    {
        DB::transaction(function () use ($bukti, $penggunaId) {
            $sanksi = $bukti->sanksiPoinSiswa;
            $this->riwayat->catat(
                $sanksi,
                'bukti_dihapus',
                'Bukti pelaksanaan dihapus',
                $sanksi->status,
                $sanksi->status,
                'File "'.$bukti->nama_file_asli.'" dihapus.',
                $penggunaId,
            );
            $lokasi = $bukti->lokasi_file;
            $bukti->delete();
            Storage::disk('local')->delete($lokasi);
        });
    }
}
