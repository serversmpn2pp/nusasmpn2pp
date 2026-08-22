<?php

namespace App\Services\Cbt;

use App\Models\PanitiaUjianCbt;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;

class SinkronkanPeranPanitiaUjian
{
    public function sinkronkan(Pegawai $pegawai): void
    {
        $pengguna = Pengguna::query()->where('pegawai_id', $pegawai->id)->first();
        $peranId = Peran::query()->where('kode', 'panitia_ujian')->value('id');

        if (! $pengguna || ! $peranId) {
            return;
        }

        $masihDitugaskan = PanitiaUjianCbt::query()
            ->where('pegawai_id', $pegawai->id)
            ->where('aktif', true)
            ->whereHas('kegiatanUjianCbt', fn ($query) => $query->where('status', '!=', 'nonaktif'))
            ->exists();

        if ($masihDitugaskan) {
            $pengguna->daftarPeran()->syncWithoutDetaching([$peranId]);

            return;
        }

        $pengguna->daftarPeran()->detach($peranId);
    }
}
