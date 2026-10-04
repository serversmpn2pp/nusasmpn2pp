<?php

namespace App\Services\Humas;

use App\Models\KerjaSamaHumas;
use App\Models\MitraHumas;
use App\Models\Pengguna;
use Illuminate\Validation\ValidationException;

class KelolaKemitraanHumasService
{
    public function pastikanMitraAktif(MitraHumas $mitra): void
    {
        if ($mitra->status !== 'aktif') {
            throw ValidationException::withMessages(['mitra' => 'Mitra diarsipkan. Aktifkan kembali sebelum menambah kerja sama atau kegiatan.']);
        }
    }

    public function pastikanVersi(int $versi, int $terbaru): void
    {
        if ($versi !== $terbaru) {
            throw ValidationException::withMessages(['versi' => 'Data telah berubah. Muat ulang halaman sebelum menyimpan.']);
        }
    }

    public function catat(MitraHumas $mitra, ?KerjaSamaHumas $mou, string $aksi, ?array $sebelum, ?array $sesudah, Pengguna $akun): void
    {
        $mitra->riwayat()->create(['kerja_sama_humas_id' => $mou?->id, 'aksi' => $aksi, 'data_sebelum' => $sebelum,
            'data_sesudah' => $sesudah, 'pengguna_id' => $akun->id, 'created_at' => now()]);
    }
}
