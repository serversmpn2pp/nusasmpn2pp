<?php

namespace App\Services\Mobile;

use App\Models\AnggotaKelas;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Models\TahunPelajaran;

class ProfilSayaMobileService
{
    public function siapkan(Pengguna $pengguna): array
    {
        $pengguna->loadMissing(['pegawai', 'siswa', 'orangTuaWali.siswa']);

        return match (true) {
            $pengguna->akunPegawai() => $this->pegawai($pengguna),
            $pengguna->akunSiswa() => $this->siswa($pengguna),
            $pengguna->akunOrangTua() => $this->orangTua($pengguna),
            default => $this->akun($pengguna),
        };
    }

    private function dasar(Pengguna $pengguna, string $jenis, ?string $fotoUrl = null): array
    {
        return [
            'jenis' => $jenis,
            'nama' => $pengguna->nama,
            'username' => $pengguna->username,
            'foto_url' => $fotoUrl,
            'terakhir_login_pada' => $pengguna->terakhir_login_pada?->toISOString(),
        ];
    }

    private function akun(Pengguna $pengguna): array
    {
        return $this->dasar($pengguna, 'akun') + [
            'dapat_ubah_foto' => false,
            'data' => [
                'nama_lengkap' => $pengguna->nama,
            ],
            'sekolah' => null,
            'anak' => [],
        ];
    }

    private function pegawai(Pengguna $pengguna): array
    {
        $pegawai = $pengguna->pegawai;

        return $this->dasar(
            $pengguna,
            'pegawai',
            $pegawai?->foto ? asset('storage/'.$pegawai->foto) : null,
        ) + [
            'dapat_ubah_foto' => true,
            'data' => [
                'nama_lengkap' => $pegawai?->nama_lengkap,
                'nip' => $pegawai?->nip,
                'nuptk' => $pegawai?->nuptk,
                'nik' => $pegawai?->nik,
                'jenis_kelamin' => $pegawai?->jenis_kelamin,
                'tempat_lahir' => $pegawai?->tempat_lahir,
                'tanggal_lahir' => $pegawai?->tanggal_lahir?->toDateString(),
                'alamat' => $pegawai?->alamat,
                'email' => $pegawai?->email,
                'no_hp' => $pegawai?->no_hp,
                'jenis_pegawai' => $pegawai?->jenis_pegawai,
                'jabatan_utama' => $pegawai?->jabatan_utama,
                'pendidikan_terakhir' => $pegawai?->pendidikan_terakhir,
                'jurusan_pendidikan' => $pegawai?->jurusan_pendidikan,
                'tahun_lulus' => $pegawai?->tahun_lulus,
                'keterangan' => $pegawai?->keterangan,
            ],
            'sekolah' => null,
            'anak' => [],
        ];
    }

    private function siswa(Pengguna $pengguna): array
    {
        $siswa = $pengguna->siswa;
        $anggotaKelas = $siswa ? $this->anggotaKelasAktif($siswa) : null;

        return $this->dasar(
            $pengguna,
            'siswa',
            $siswa?->foto ? asset('storage/'.$siswa->foto) : null,
        ) + [
            'dapat_ubah_foto' => false,
            'data' => [
                'nama_lengkap' => $siswa?->nama_lengkap,
                'nis' => $siswa?->nis,
                'nisn' => $siswa?->nisn,
                'jenis_kelamin' => $siswa?->jenis_kelamin,
                'tempat_lahir' => $siswa?->tempat_lahir,
                'tanggal_lahir' => $siswa?->tanggal_lahir?->toDateString(),
                'agama' => $siswa?->agama,
                'alamat' => $siswa?->alamat,
            ],
            'sekolah' => $this->sekolah($anggotaKelas),
            'anak' => [],
        ];
    }

    private function orangTua(Pengguna $pengguna): array
    {
        $orangTua = $pengguna->orangTuaWali;
        $anak = $orangTua?->siswa
            ?->map(function (Siswa $siswa) {
                $anggotaKelas = $this->anggotaKelasAktif($siswa);

                return [
                    'id' => (int) $siswa->id,
                    'nama' => $siswa->nama_lengkap,
                    'nisn' => $siswa->nisn,
                    'hubungan' => $siswa->pivot?->hubungan,
                    'utama' => (bool) ($siswa->pivot?->utama ?? false),
                    'foto_url' => $siswa->foto ? asset('storage/'.$siswa->foto) : null,
                    'sekolah' => $this->sekolah($anggotaKelas),
                ];
            })
            ->sortByDesc(fn (array $item) => $item['utama'])
            ->values()
            ->all() ?? [];

        return $this->dasar($pengguna, 'orang_tua') + [
            'dapat_ubah_foto' => false,
            'data' => [
                'nama_lengkap' => $orangTua?->nama_lengkap,
                'nomor_wa' => $orangTua?->nomor_wa,
            ],
            'sekolah' => null,
            'anak' => $anak,
        ];
    }

    private function anggotaKelasAktif(Siswa $siswa): ?AnggotaKelas
    {
        $tahunPelajaran = TahunPelajaran::query()
            ->where('aktif', true)
            ->latest('tanggal_mulai')
            ->first();
        $query = AnggotaKelas::query()
            ->with(['kelas:id,nama,wali_kelas_id', 'kelas.waliKelas:id,nama_lengkap', 'tahunPelajaran:id,nama'])
            ->where('siswa_id', $siswa->id)
            ->where('status_keanggotaan', 'aktif');

        if ($tahunPelajaran) {
            $aktif = (clone $query)
                ->where('tahun_pelajaran_id', $tahunPelajaran->id)
                ->latest('id')
                ->first();

            if ($aktif) {
                return $aktif;
            }
        }

        return $query->latest('tahun_pelajaran_id')->latest('id')->first();
    }

    private function sekolah(?AnggotaKelas $anggotaKelas): ?array
    {
        if (! $anggotaKelas) {
            return null;
        }

        return [
            'kelas' => $anggotaKelas->kelas?->nama,
            'nomor_absen' => $anggotaKelas->nomor_absen,
            'tahun_pelajaran' => $anggotaKelas->tahunPelajaran?->nama,
            'wali_kelas' => $anggotaKelas->kelas?->waliKelas?->nama_lengkap,
        ];
    }
}
