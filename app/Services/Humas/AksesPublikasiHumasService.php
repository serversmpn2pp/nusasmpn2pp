<?php

namespace App\Services\Humas;

use App\Models\Pengguna;
use App\Models\PublikasiHumas;

class AksesPublikasiHumasService
{
    public function sendiri(Pengguna $u): bool
    {
        return ! $u->memilikiIzin(PublikasiHumas::IZIN_LIHAT);
    }

    public function dapatKelola(Pengguna $u, ?PublikasiHumas $p = null): bool
    {
        return $u->aktif && ! $u->akunSiswa() && ! $u->akunOrangTua() && ($u->memilikiIzin('publikasi_humas.kelola')
            || ($u->memilikiIzin('publikasi_humas.kontribusi') && (! $p || $p->dibuat_oleh_pengguna_id === $u->id)));
    }

    public function pastikan(Pengguna $u, ?PublikasiHumas $p = null, bool $ubah = false): void
    {
        abort_unless($u->aktif && ! $u->akunSiswa() && ! $u->akunOrangTua() && $u->memilikiIzin(PublikasiHumas::IZIN_AKSES), 403);
        if ($p && $this->sendiri($u)) {
            abort_unless($p->dibuat_oleh_pengguna_id === $u->id, 404);
        }
        if ($ubah) {
            abort_unless($this->dapatKelola($u, $p), 403);
        }
    }

    public function query(Pengguna $u)
    {
        $this->pastikan($u);

        return PublikasiHumas::query()->when($this->sendiri($u), fn ($q) => $q->where('dibuat_oleh_pengguna_id', $u->id));
    }
}
