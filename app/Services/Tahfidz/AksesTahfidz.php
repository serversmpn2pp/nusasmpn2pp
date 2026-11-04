<?php

namespace App\Services\Tahfidz;

use App\Models\AnggotaTahfidz;
use App\Models\KelompokTahfidz;
use App\Models\Pengguna;
use Illuminate\Database\Eloquent\Builder;

class AksesTahfidz
{
    public function kelola(Pengguna $pengguna): bool
    {
        return $this->akunBerwenang($pengguna) && $pengguna->memilikiIzin('tahfidz.kelola');
    }

    public function lihat(Pengguna $pengguna): bool
    {
        return $this->akunBerwenang($pengguna) && ($this->kelola($pengguna)
            || KelompokTahfidz::where('pembimbing_pegawai_id', $pengguna->pegawai_id)->exists());
    }

    public function cakupan(Pengguna $pengguna): Builder
    {
        abort_unless($this->lihat($pengguna), 403);

        return KelompokTahfidz::query()->when(! $this->kelola($pengguna),
            fn (Builder $q) => $q->where('pembimbing_pegawai_id', $pengguna->pegawai_id));
    }

    public function catat(Pengguna $pengguna, KelompokTahfidz $kelompok): bool
    {
        return $kelompok->aktif && $pengguna->akunPegawai() && $this->akunBerwenang($pengguna)
            && (int) $pengguna->pegawai_id === (int) $kelompok->pembimbing_pegawai_id;
    }

    public function dokumen(Pengguna $pengguna, KelompokTahfidz $kelompok, AnggotaTahfidz $anggota): bool
    {
        return (int) $anggota->kelompok_tahfidz_id === (int) $kelompok->id
            && ($this->kelola($pengguna) || ($anggota->aktif && $this->catat($pengguna, $kelompok)));
    }

    public function pesertaDokumen(Pengguna $pengguna, int $kelompokId, int $anggotaId): array
    {
        $kelompok = $this->cakupan($pengguna)->with(['tahunPelajaran', 'pembimbing'])->findOrFail($kelompokId);
        $anggota = $kelompok->anggota()->with('siswa')->findOrFail($anggotaId);
        abort_unless($this->dokumen($pengguna, $kelompok, $anggota), 403);

        return [$kelompok, $anggota];
    }

    public function lihatWisuda(Pengguna $pengguna, KelompokTahfidz $kelompok, AnggotaTahfidz $anggota): bool
    {
        return $this->dokumen($pengguna, $kelompok, $anggota)
            && ($this->kelola($pengguna) || $kelompok->tahunPelajaran->aktif);
    }

    public function verifikasiWisuda(Pengguna $pengguna, KelompokTahfidz $kelompok, AnggotaTahfidz $anggota): bool
    {
        return $anggota->aktif && $kelompok->tahunPelajaran->aktif && $this->catat($pengguna, $kelompok);
    }

    private function akunBerwenang(Pengguna $pengguna): bool
    {
        return $pengguna->aktif && ! $pengguna->akunSiswa() && ! $pengguna->akunOrangTua()
            && ($pengguna->akun_sistem || ($pengguna->akunPegawai() && $pengguna->pegawai?->aktif)
                || (! $pengguna->pegawai_id && $pengguna->administrator()));
    }
}
