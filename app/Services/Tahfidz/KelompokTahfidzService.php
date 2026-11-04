<?php

namespace App\Services\Tahfidz;

use App\Models\AnggotaKelas;
use App\Models\AnggotaTahfidz;
use App\Models\KelompokTahfidz;
use App\Models\Pegawai;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KelompokTahfidzService
{
    public function pembimbing(): Builder
    {
        return Pegawai::query()->where('aktif', true)->where(function (Builder $q) {
            $q->whereRaw('LOWER(TRIM(jenis_pegawai)) = ?', ['guru'])
                ->orWhereHas('guruMataPelajaran', fn (Builder $guru) => $guru->where('aktif', true));
        });
    }

    public function simpan(array $data, ?KelompokTahfidz $kelompok = null): KelompokTahfidz
    {
        return DB::transaction(function () use ($data, $kelompok) {
            $tahunId = $kelompok?->tahun_pelajaran_id ?? $data['tahun_pelajaran_id'];
            // Mutasi kelompok dan anggota pada tahun yang sama memakai kunci yang sama.
            TahunPelajaran::lockForUpdate()->findOrFail($tahunId);
            if ($kelompok) {
                $kelompok = $this->kunciVersi($kelompok, (int) $data['versi']);
            }

            $aktif = (bool) $data['aktif'];
            $pembimbingId = (int) $data['pembimbing_pegawai_id'];
            if (($aktif || (int) $kelompok?->pembimbing_pegawai_id !== $pembimbingId)
                && ! $this->pembimbing()->whereKey($pembimbingId)->exists()) {
                throw ValidationException::withMessages(['pembimbing_pegawai_id' => 'Pilih guru pembimbing yang masih aktif.']);
            }

            if (KelompokTahfidz::where('tahun_pelajaran_id', $tahunId)->where('nama', $data['nama'])
                ->when($kelompok, fn (Builder $q) => $q->whereKeyNot($kelompok->id))->exists()) {
                throw ValidationException::withMessages(['nama' => 'Nama kelompok sudah digunakan pada tahun pelajaran ini.']);
            }

            if ($aktif && $kelompok && ! $kelompok->aktif) {
                $this->pastikanTidakRangkap($kelompok, $kelompok->anggotaAktif()->pluck('siswa_id')->all());
            }

            $isian = ['nama' => $data['nama'], 'pembimbing_pegawai_id' => $pembimbingId, 'aktif' => $aktif];
            if (! $kelompok) {
                return KelompokTahfidz::create(['tahun_pelajaran_id' => $tahunId, ...$isian]);
            }

            $kelompok->fill($isian);
            if ($kelompok->isDirty()) {
                $kelompok->versi++;
                $kelompok->save();
            }

            return $kelompok;
        });
    }

    public function daftarAnggota(KelompokTahfidz $kelompok): Collection
    {
        return $this->siswa($kelompok, $kelompok->anggotaAktif()->pluck('siswa_id'));
    }

    public function pilihanAnggota(KelompokTahfidz $kelompok): array
    {
        $terpilih = $kelompok->anggotaAktif()->pluck('siswa_id');
        $calon = $this->penempatanAktif($kelompok)->pluck('siswa_id');

        return [
            'pilihan' => $this->siswa($kelompok, $calon->merge($terpilih)->unique()),
            'terpilih' => $terpilih,
            'calon' => $calon->flip(),
            'kelompokLain' => $this->kelompokLain($kelompok)->pluck('kelompok.nama', 'anggota.siswa_id'),
        ];
    }

    public function simpanAnggota(KelompokTahfidz $kelompok, array $siswaIds, int $versi): void
    {
        DB::transaction(function () use ($kelompok, $siswaIds, $versi) {
            TahunPelajaran::lockForUpdate()->findOrFail($kelompok->tahun_pelajaran_id);
            $kelompok = $this->kunciVersi($kelompok, $versi);
            if (! $kelompok->aktif) {
                throw ValidationException::withMessages(['siswa_ids' => 'Kelompok nonaktif tidak dapat diubah anggotanya.']);
            }

            $lama = $kelompok->anggotaAktif()->pluck('siswa_id')->all();
            $masuk = array_diff($siswaIds, $lama);
            $keluar = array_diff($lama, $siswaIds);
            if ($masuk && $this->penempatanAktif($kelompok)->whereIn('siswa_id', $masuk)->count() !== count($masuk)) {
                throw ValidationException::withMessages(['siswa_ids' => 'Ada siswa baru yang tidak tercatat di kelas aktif pada tahun pelajaran ini.']);
            }
            $this->pastikanTidakRangkap($kelompok, $siswaIds);

            if ($keluar) {
                $kelompok->anggotaAktif()->whereIn('siswa_id', $keluar)->update(['aktif' => false]);
            }
            if ($masuk) {
                AnggotaTahfidz::upsert(array_map(fn (int $id) => [
                    'kelompok_tahfidz_id' => $kelompok->id, 'siswa_id' => $id, 'aktif' => true,
                ], array_values($masuk)), ['kelompok_tahfidz_id', 'siswa_id'], ['aktif']);
            }
            if ($masuk || $keluar) {
                $kelompok->increment('versi');
            }
        });
    }

    private function kunciVersi(KelompokTahfidz $kelompok, int $versi): KelompokTahfidz
    {
        $terbaru = KelompokTahfidz::lockForUpdate()->findOrFail($kelompok->id);
        if ($terbaru->versi !== $versi) {
            throw ValidationException::withMessages(['versi' => 'Kelompok sudah diperbarui. Muat ulang halaman sebelum menyimpan.']);
        }

        return $terbaru;
    }

    private function penempatanAktif(KelompokTahfidz $kelompok): Builder
    {
        return AnggotaKelas::where('tahun_pelajaran_id', $kelompok->tahun_pelajaran_id)
            ->where('status_keanggotaan', 'aktif')
            ->whereHas('kelas', fn (Builder $q) => $q->where('aktif', true)->where('tahun_pelajaran_id', $kelompok->tahun_pelajaran_id))
            ->whereHas('siswa', fn (Builder $q) => $q->where('aktif', true));
    }

    private function siswa(KelompokTahfidz $kelompok, Collection $ids): Collection
    {
        return Siswa::whereIn('id', $ids)->with(['anggotaKelas' => fn ($q) => $q
            ->where('tahun_pelajaran_id', $kelompok->tahun_pelajaran_id)->with('kelas:id,nama,tingkat')])
            ->orderBy('nama_lengkap')->orderBy('id')->get(['id', 'nama_lengkap', 'nis', 'nisn']);
    }

    private function kelompokLain(KelompokTahfidz $kelompok): QueryBuilder
    {
        return DB::table('anggota_tahfidz as anggota')
            ->join('kelompok_tahfidz as kelompok', 'kelompok.id', '=', 'anggota.kelompok_tahfidz_id')
            ->where('kelompok.tahun_pelajaran_id', $kelompok->tahun_pelajaran_id)
            ->where('kelompok.id', '!=', $kelompok->id)->where('kelompok.aktif', true)->where('anggota.aktif', true);
    }

    private function pastikanTidakRangkap(KelompokTahfidz $kelompok, array $siswaIds): void
    {
        if ($siswaIds && $this->kelompokLain($kelompok)->whereIn('anggota.siswa_id', $siswaIds)->exists()) {
            throw ValidationException::withMessages(['siswa_ids' => 'Ada siswa yang sudah terdaftar di kelompok aktif lain. Periksa daftar anggotanya.']);
        }
    }
}
