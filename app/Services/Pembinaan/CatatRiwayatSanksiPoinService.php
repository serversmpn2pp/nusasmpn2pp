<?php

namespace App\Services\Pembinaan;

use App\Models\RiwayatSanksiPoinSiswa;
use App\Models\SanksiPoinSiswa;

class CatatRiwayatSanksiPoinService
{
    public function catat(
        SanksiPoinSiswa $sanksi,
        string $jenisKegiatan,
        string $judul,
        ?string $statusSebelum,
        ?string $statusSesudah,
        ?string $catatan = null,
        ?int $penggunaId = null,
        array $dataTambahan = [],
    ): RiwayatSanksiPoinSiswa {
        return $sanksi->riwayatSanksiPoinSiswa()->create([
            'jenis_kegiatan' => $jenisKegiatan,
            'judul' => $judul,
            'status_sebelum' => $statusSebelum,
            'status_sesudah' => $statusSesudah,
            'catatan' => filled($catatan) ? trim($catatan) : null,
            'data_tambahan' => $dataTambahan ?: null,
            'dibuat_oleh_pengguna_id' => $penggunaId,
            'terjadi_pada' => now(),
        ]);
    }
}
