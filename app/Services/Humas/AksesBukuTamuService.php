<?php

namespace App\Services\Humas;

use App\Models\KunjunganTamu;
use App\Models\Pengguna;
use App\Models\TahunPelajaran;
use App\Services\Piket\GuruPiketHariIniService;
use Illuminate\Database\Eloquent\Builder;

class AksesBukuTamuService
{
    public function bolehKelola(?Pengguna $akun): bool
    {
        return $akun?->aktif && $akun->memilikiIzin('buku_tamu.kelola');
    }

    public function bolehRekap(?Pengguna $akun): bool
    {
        return $akun?->aktif && $akun->memilikiIzin(['buku_tamu.lihat', 'buku_tamu.kelola']);
    }

    public function bolehMencatat(?Pengguna $akun): bool
    {
        if (! $akun?->aktif) {
            return false;
        }
        if ($akun->memilikiIzin(['buku_tamu.kelola', 'buku_tamu.catat'])) {
            return true;
        }
        if (! $akun->memilikiIzin('buku_tamu.catat_piket') || ! $akun->pegawai?->aktif) {
            return false;
        }
        $tahun = TahunPelajaran::where('aktif', true)->latest('tanggal_mulai')->latest('id')->first();

        return $tahun && app(GuruPiketHariIniService::class)->jadwalHariIni($akun, $tahun) !== null;
    }

    public function bolehMembuka(?Pengguna $akun): bool
    {
        return $this->bolehRekap($akun) || $this->bolehMencatat($akun);
    }

    public function cakupan(?Pengguna $akun): Builder
    {
        abort_unless($this->bolehMembuka($akun), 403);
        $query = KunjunganTamu::query();
        if (! $this->bolehRekap($akun)) {
            $query->where(fn ($q) => $q->whereBetween('waktu_datang', [today(), today()->endOfDay()])
                ->orWhere(fn ($q) => $q->where('status', 'berkunjung')->where('waktu_datang', '<', today())));
        }

        return $query;
    }

    public function pastikanLihat(?Pengguna $akun, KunjunganTamu $tamu): void
    {
        abort_unless($this->cakupan($akun)->whereKey($tamu->id)->exists(), 403);
    }
}
