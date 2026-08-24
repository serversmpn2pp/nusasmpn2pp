<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PenggunaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $peranAktif = $this->daftarPeran->where('aktif', true);

        return [
            'id' => (int) $this->id,
            'nama' => $this->nama,
            'username' => $this->username,
            'jenis_akun' => $this->labelJenisAkun(),
            'administrator' => $this->administrator(),
            'wajib_ganti_kata_sandi' => $this->harusMenggantiKataSandi(),
            'peran' => $peranAktif
                ->pluck('kode')
                ->filter()
                ->unique()
                ->sort()
                ->values(),
            'izin' => $peranAktif
                ->flatMap(fn ($peran) => $peran->izin->where('aktif', true)->pluck('kode'))
                ->filter()
                ->unique()
                ->sort()
                ->values(),
            'terakhir_login_pada' => $this->terakhir_login_pada?->toISOString(),
        ];
    }
}
