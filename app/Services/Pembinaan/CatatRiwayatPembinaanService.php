<?php

namespace App\Services\Pembinaan;

use App\Models\LaporanPembinaanSiswa;
use App\Models\RiwayatProsesPembinaanSiswa;

class CatatRiwayatPembinaanService
{
    public function catat(
        LaporanPembinaanSiswa $laporan,
        string $kode,
        string $judul,
        ?string $keterangan = null,
        ?string $statusSebelum = null,
        ?string $statusSesudah = null,
        ?int $penggunaId = null,
        ?array $data = null,
    ): RiwayatProsesPembinaanSiswa {
        return $laporan->riwayatProsesPembinaanSiswa()->create([
            'kode_kegiatan' => $kode,
            'judul' => $judul,
            'keterangan' => filled($keterangan) ? trim($keterangan) : null,
            'status_sebelum' => $statusSebelum,
            'status_sesudah' => $statusSesudah,
            'pengguna_id' => $penggunaId ?? auth()->id(),
            'terjadi_pada' => now(),
            'data' => $data,
        ]);
    }
}
