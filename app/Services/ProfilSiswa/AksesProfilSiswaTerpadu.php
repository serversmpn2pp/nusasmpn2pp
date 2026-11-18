<?php

namespace App\Services\ProfilSiswa;

use App\Models\AnggotaKelas;
use App\Models\AnggotaTahfidz;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Pembinaan\PenugasanGuruBkTingkatService;
use App\Services\Tahfidz\AksesTahfidz;
use Illuminate\Database\Eloquent\Builder;

class AksesProfilSiswaTerpadu
{
    public function __construct(
        private readonly PenugasanGuruBkTingkatService $bk,
        private readonly AksesTahfidz $tahfidz,
    ) {}

    public function lingkup(?Pengguna $user): array
    {
        if (! $user?->aktif || $user->akunSiswa() || $user->akunOrangTua()
            || in_array($user->peran, ['siswa', 'orang_tua'], true)
            || ($user->pegawai_id && ! $user->pegawai?->aktif)) {
            return [];
        }

        $user->loadMissing('daftarPeran.izin');
        if (! $user->administrator() && ! $user->akunPegawai()) {
            return [];
        }

        return [
            'admin' => $user->administrator(),
            'pimpinan' => $user->memilikiPeran('pimpinan'),
            'tu' => $user->memilikiPeran('tata_usaha') && $user->memilikiIzin(['siswa.kelola', 'siswa.lihat']),
            'kurikulum' => $user->memilikiPeran('wakil_pimpinan_kurikulum') && $user->memilikiIzin(['nilai.rekap', 'nilai.lihat']),
            'kesiswaan' => $user->memilikiPeran('wakil_pimpinan_kesiswaan') && $user->memilikiIzin(['siswa.lihat', 'absensi.laporan', 'bk.lihat']),
            'wali' => $user->memilikiPeran('wali_kelas') && $user->memilikiIzin('siswa.lihat'),
            'guru_wali' => $user->memilikiPeran('guru_wali') && $user->memilikiIzin('guru_wali.lihat'),
            'bk' => $this->bk->petugasBk($user) && $user->memilikiIzin(['bk.lihat', 'poin_siswa.verifikasi_bk']),
            'mapel' => $user->memilikiPeran('guru_mapel') && $user->memilikiIzin(['nilai.lihat', 'nilai.rekap']),
            'tahfidz' => $this->tahfidz->lihat($user),
        ];
    }

    public function dapatMembuka(?Pengguna $user): bool
    {
        return in_array(true, $this->lingkup($user), true);
    }

    public function tautan(?Pengguna $user, Siswa $siswa): ?string
    {
        $lingkup = $this->lingkup($user);
        if (! in_array(true, $lingkup, true)) {
            return null;
        }

        $tahun = $this->tahun($user, $lingkup)->orderByDesc('aktif')->orderByDesc('tanggal_mulai')->get();
        $riwayat = $siswa->anggotaKelas()->pluck('tahun_pelajaran_id');
        foreach ($tahun as $periode) {
            if (($periode->aktif || $riwayat->contains($periode->id))
                && $this->siswa($user, $lingkup, $periode)->whereKey($siswa->id)->exists()) {
                return route('profil-siswa-terpadu.show', ['siswa' => $siswa, 'tahun' => $periode->id]);
            }
        }

        return null;
    }

    public function semuaIdentitas(array $lingkup): bool
    {
        return $this->salahSatu($lingkup, ['admin', 'pimpinan', 'tu', 'kurikulum', 'kesiswaan']);
    }

    public function tahun(Pengguna $user, array $lingkup): Builder
    {
        return TahunPelajaran::query()->when(! $this->semuaIdentitas($lingkup) && ! $this->tahfidz->kelola($user),
            fn (Builder $query) => $query->where('aktif', true));
    }

    public function siswa(Pengguna $user, array $lingkup, TahunPelajaran $tahun, ?int $kelas = null): Builder
    {
        $query = Siswa::query();
        if ($this->semuaIdentitas($lingkup) && $kelas === null) {
            return $query->where(fn (Builder $q) => $q
                ->whereHas('anggotaKelas', fn (Builder $a) => $a->where('tahun_pelajaran_id', $tahun->id))
                ->orWhereDoesntHave('anggotaKelas')
                ->when($lingkup['tahfidz'], fn (Builder $q) => $q->orWhereIn('siswa.id',
                    $this->anggotaTahfidz($user, $tahun)->select('siswa_id'))));
        }

        return $query->where(fn (Builder $q) => $q->whereIn('siswa.id',
            $this->anggotaKelas($user, $lingkup, $tahun, 'identitas')
                ->when($kelas !== null, fn (Builder $a) => $a->where('kelas_id', $kelas))
                ->select('siswa_id'))
            ->when($kelas === null && $lingkup['tahfidz'], fn (Builder $q) => $q->orWhereIn('siswa.id',
                $this->anggotaTahfidz($user, $tahun)->select('siswa_id'))));
    }

    public function anggotaKelas(Pengguna $user, array $lingkup, TahunPelajaran $tahun, string $bagian): Builder
    {
        $query = AnggotaKelas::query()->where('anggota_kelas.tahun_pelajaran_id', $tahun->id);
        $sekolah = match ($bagian) {
            'identitas' => $this->semuaIdentitas($lingkup),
            'presensi' => $this->salahSatu($lingkup, ['admin', 'pimpinan', 'kesiswaan']),
            'akademik', 'ujian' => $this->salahSatu($lingkup, ['admin', 'pimpinan', 'kurikulum']),
            'pembinaan', 'ibadah' => $this->salahSatu($lingkup, ['admin', 'pimpinan', 'kesiswaan']),
            'pemeriksaan' => $lingkup['kesiswaan'],
            default => false,
        };
        if ($sekolah) {
            return $query;
        }
        if (! $tahun->aktif || ! $user->pegawai_id) {
            return $query->whereRaw('1 = 0');
        }

        $pendampingan = ! in_array($bagian, ['pemeriksaan', 'mapel'], true);
        $wali = $lingkup['wali'] && $pendampingan;
        $guruWali = $lingkup['guru_wali'] && $pendampingan;
        $tingkatBk = $lingkup['bk'] && in_array($bagian, ['identitas', 'presensi', 'pembinaan', 'pemeriksaan'], true)
            ? $this->bk->tingkatDitugaskan($user, $tahun->id)->all() : [];
        $mapel = $lingkup['mapel'] && in_array($bagian, ['identitas', 'mapel'], true);

        return $query->where('status_keanggotaan', 'aktif')->where(function (Builder $q) use ($user, $wali, $guruWali, $tingkatBk, $mapel, $tahun) {
            $q->whereRaw('1 = 0');
            if ($wali) {
                $q->orWhereHas('kelas', fn (Builder $k) => $k->where('aktif', true)->where('wali_kelas_id', $user->pegawai_id));
            }
            if ($guruWali) {
                $q->orWhereHas('siswa.penugasanGuruWaliSiswa', fn (Builder $a) => $a
                    ->where('guru_wali_pegawai_id', $user->pegawai_id)->where('aktif', true));
            }
            if ($tingkatBk !== []) {
                $q->orWhereHas('kelas', fn (Builder $k) => $k->whereIn('tingkat', $tingkatBk));
            }
            if ($mapel) {
                $q->orWhereHas('kelas.guruMataPelajaran', fn (Builder $g) => $g->where('aktif', true)
                    ->where('tahun_pelajaran_id', $tahun->id)->where('pegawai_id', $user->pegawai_id));
            }
        });
    }

    public function anggotaTahfidz(Pengguna $user, TahunPelajaran $tahun): Builder
    {
        $kelola = $this->tahfidz->kelola($user);

        return AnggotaTahfidz::query()
            ->when(! $kelola, fn (Builder $q) => $q->where('aktif', true))
            ->whereHas('kelompok', fn (Builder $q) => $q->where('tahun_pelajaran_id', $tahun->id)
                ->when(! $kelola, fn (Builder $k) => $k->where('aktif', true)->where('pembimbing_pegawai_id', $user->pegawai_id)));
    }

    public function salahSatu(array $lingkup, array $peran): bool
    {
        foreach ($peran as $kode) {
            if ($lingkup[$kode] ?? false) {
                return true;
            }
        }

        return false;
    }
}
