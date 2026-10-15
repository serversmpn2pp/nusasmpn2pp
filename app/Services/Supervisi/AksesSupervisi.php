<?php

namespace App\Services\Supervisi;

use App\Models\Izin;
use App\Models\Pengguna;
use App\Models\RiwayatSupervisi;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class AksesSupervisi
{
    public function staff(Pengguna $u): void
    {
        abort_unless($u->aktif && ! $u->akunSiswa() && ! $u->akunOrangTua() && ($u->akun_sistem || $u->akunPegawai()), 403);
    }

    public function kelola(Pengguna $u, bool $instrumen = false): void
    {
        $this->staff($u);
        abort_unless($u->memilikiIzin($instrumen ? 'supervisi.instrumen_kelola' : 'supervisi.kelola'), 403);
    }

    public function global(Pengguna $u): bool
    {
        return $u->memilikiIzin(['supervisi.lihat', 'supervisi.kelola']);
    }

    public function sesi(Builder $query, Pengguna $u): Builder
    {
        $this->staff($u);

        return $this->global($u) ? $query : $query->whereNotNull('diterbitkan_pada')->where(function ($q) use ($u) {
            $q->where('guru_pegawai_id', $u->pegawai_id ?: 0)->orWhere('supervisor_pegawai_id', $u->pegawai_id ?: 0);
        });
    }

    public function kunci(): void
    {
        // One school-wide lock serializes scheduling across different programs.
        Izin::where('kode', 'supervisi.kelola')->lockForUpdate()->firstOrFail();
    }

    public function versi(Model $m, int $versi): void
    {
        if ((int) $m->versi !== $versi) {
            throw ValidationException::withMessages(['versi' => 'Data telah berubah. Muat ulang dan periksa kembali sebelum menyimpan.']);
        }
    }

    public function catat(Pengguna $u, Model $m, string $aksi, ?string $alasan = null): void
    {
        $kolom = match ($m->getTable()) {
            'program_supervisi' => 'program_supervisi_id',
            'sesi_supervisi' => 'sesi_supervisi_id',
            'praobservasi_supervisi', 'penilaian_supervisi', 'pascaobservasi_supervisi', 'tindak_lanjut_supervisi' => 'sesi_supervisi_id',
            default => 'versi_instrumen_supervisi_id',
        };
        $id = in_array($m->getTable(), ['praobservasi_supervisi', 'penilaian_supervisi', 'pascaobservasi_supervisi', 'tindak_lanjut_supervisi'], true) ? $m->sesi_supervisi_id : $m->id;
        RiwayatSupervisi::create([$kolom => $id, 'pengguna_id' => $u->id, 'aksi' => $aksi, 'alasan' => $alasan,
            'salinan' => $m->attributesToArray(), 'created_at' => now()]);
    }

    public function sidik(array $data): string
    {
        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    public function pengulangan(Model $m, Pengguna $u, string $sidik): void
    {
        abort_unless($m->pembuat_id === $u->id, 403);
        if (! hash_equals($m->sidik_pembuatan, $sidik)) {
            throw ValidationException::withMessages(['token_pembuatan' => 'Token ini sudah digunakan untuk data lain. Muat ulang formulir untuk membuat data baru.']);
        }
    }
}
