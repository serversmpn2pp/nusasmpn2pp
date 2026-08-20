<?php

namespace App\Services\Ibadah;

use App\Models\Pengguna;
use App\Models\PenugasanPendampingIbadahSiswi;
use App\Models\TahunPelajaran;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class AksesBerhalanganIbadah
{
    public function dapatMemindai(?Pengguna $pengguna, ?TahunPelajaran $tahunPelajaran = null): bool
    {
        if (! $pengguna || ! $pengguna->aktif) {
            return false;
        }

        if ($pengguna->administrator() || $pengguna->memilikiPeran('wakil_pimpinan_kesiswaan')) {
            return true;
        }

        if (! $pengguna->pegawai_id
            || $pengguna->pegawai?->jenis_kelamin !== 'P'
            || ! $this->guruPerempuanYangDiizinkan($pengguna)
            || ! $pengguna->pegawai?->aktif) {
            return false;
        }

        $tahunPelajaran ??= TahunPelajaran::query()
            ->where('aktif', true)
            ->orderByDesc('tanggal_mulai')
            ->first();

        return $tahunPelajaran && PenugasanPendampingIbadahSiswi::query()
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->where('pegawai_id', $pengguna->pegawai_id)
            ->where('aktif', true)
            ->exists();
    }

    public function dapatMemindaiKelas(
        ?Pengguna $pengguna,
        TahunPelajaran $tahunPelajaran,
        int $kelasId,
    ): bool {
        if (! $this->dapatMemindai($pengguna, $tahunPelajaran)) {
            return false;
        }

        if ($pengguna->administrator() || $pengguna->memilikiPeran('wakil_pimpinan_kesiswaan')) {
            return true;
        }

        return PenugasanPendampingIbadahSiswi::query()
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->where('pegawai_id', $pengguna->pegawai_id)
            ->where('aktif', true)
            ->where(function ($query) use ($kelasId) {
                $query->where('semua_kelas', true)
                    ->orWhereHas('kelas', fn ($query) => $query->whereKey($kelasId));
            })
            ->exists();
    }

    public function dapatMengonfirmasi(?Pengguna $pengguna, ?TahunPelajaran $tahunPelajaran = null): bool
    {
        return $this->dapatMemindai($pengguna, $tahunPelajaran);
    }

    public function dapatMengonfirmasiKelas(
        ?Pengguna $pengguna,
        TahunPelajaran $tahunPelajaran,
        int $kelasId,
    ): bool {
        return $this->dapatMemindaiKelas($pengguna, $tahunPelajaran, $kelasId);
    }

    public function batasiPeriodeSesuaiCakupan(
        Builder $query,
        Pengguna $pengguna,
        TahunPelajaran $tahunPelajaran,
    ): Builder {
        if ($pengguna->administrator() || $pengguna->memilikiPeran('wakil_pimpinan_kesiswaan')) {
            return $query;
        }

        $cakupan = $this->cakupanKelas($pengguna, $tahunPelajaran);

        return $cakupan['semua_kelas']
            ? $query
            : $query->whereIn('kelas_id', $cakupan['kelas_ids']);
    }

    public function kelasTercakup(Pengguna $pengguna, TahunPelajaran $tahunPelajaran): Collection
    {
        $query = $tahunPelajaran->kelas()->where('aktif', true);

        if ($pengguna->administrator() || $pengguna->memilikiPeran('wakil_pimpinan_kesiswaan')) {
            return $query->orderBy('tingkat')->orderBy('nama')->get();
        }

        $cakupan = $this->cakupanKelas($pengguna, $tahunPelajaran);

        return $query
            ->when(! $cakupan['semua_kelas'], fn ($query) => $query->whereIn('id', $cakupan['kelas_ids']))
            ->orderBy('tingkat')
            ->orderBy('nama')
            ->get();
    }

    public function penggunaPendampingUntukKelas(TahunPelajaran $tahunPelajaran, int $kelasId): Collection
    {
        $pegawaiIds = PenugasanPendampingIbadahSiswi::query()
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->where('aktif', true)
            ->where(function ($query) use ($kelasId) {
                $query->where('semua_kelas', true)
                    ->orWhereHas('kelas', fn ($query) => $query->whereKey($kelasId));
            })
            ->pluck('pegawai_id');

        return Pengguna::query()
            ->where('aktif', true)
            ->whereIn('pegawai_id', $pegawaiIds)
            ->whereHas('pegawai', fn ($query) => $query
                ->where('aktif', true)
                ->where('jenis_kelamin', 'P'))
            ->where(function ($query) {
                $query->whereHas('pegawai', fn ($query) => $query->whereRaw('LOWER(jenis_pegawai) = ?', ['guru']))
                    ->orWhereHas('daftarPeran', fn ($query) => $query
                        ->where('kode', 'guru_pl')
                        ->where('aktif', true));
            })
            ->get();
    }

    private function cakupanKelas(Pengguna $pengguna, TahunPelajaran $tahunPelajaran): array
    {
        $penugasan = PenugasanPendampingIbadahSiswi::query()
            ->with('kelas:id')
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->where('pegawai_id', $pengguna->pegawai_id)
            ->where('aktif', true)
            ->get();

        return [
            'semua_kelas' => $penugasan->contains('semua_kelas', true),
            'kelas_ids' => $penugasan->flatMap->kelas->pluck('id')->map(fn ($id) => (int) $id)->unique()->values()->all(),
        ];
    }

    private function guruPerempuanYangDiizinkan(Pengguna $pengguna): bool
    {
        return mb_strtolower(trim((string) $pengguna->pegawai?->jenis_pegawai)) === 'guru'
            || $pengguna->memilikiPeran('guru_pl');
    }
}
