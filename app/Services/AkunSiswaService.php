<?php

namespace App\Services;

use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AkunSiswaService
{
    public function buat(Siswa $siswa): Pengguna
    {
        if ($siswa->pengguna()->exists()) {
            throw ValidationException::withMessages([
                'akun' => 'Siswa ini sudah memiliki akun.',
            ]);
        }

        $username = $this->usernameDariNisn($siswa->nisn);

        if (! $username) {
            throw ValidationException::withMessages([
                'akun' => 'Akun belum dapat dibuat karena NISN siswa masih kosong.',
            ]);
        }

        if (Pengguna::where('username', $username)->exists()) {
            throw ValidationException::withMessages([
                'akun' => 'NISN '.$username.' sudah digunakan sebagai username akun lain.',
            ]);
        }

        return DB::transaction(function () use ($siswa, $username) {
            $kataSandiAwal = $this->buatKataSandiAcak();
            $pengguna = Pengguna::create([
                'siswa_id' => $siswa->id,
                'nama' => $siswa->nama_lengkap,
                'username' => $username,
                'kata_sandi' => Hash::make($kataSandiAwal),
                'kata_sandi_awal' => $kataSandiAwal,
                'wajib_ganti_kata_sandi' => true,
                'peran' => 'siswa',
                'aktif' => $siswa->aktif,
                'akun_sistem' => false,
            ]);

            $this->pasangPeranSiswa($pengguna);

            return $pengguna;
        });
    }

    public function resetKataSandi(Pengguna $pengguna): string
    {
        if (! $pengguna->siswa_id || $pengguna->akun_sistem) {
            throw ValidationException::withMessages([
                'akun' => 'Akun yang dipilih bukan akun siswa.',
            ]);
        }

        $kataSandiAwal = $this->buatKataSandiAcak();

        $pengguna->forceFill([
            'kata_sandi' => Hash::make($kataSandiAwal),
            'kata_sandi_awal' => $kataSandiAwal,
            'wajib_ganti_kata_sandi' => true,
        ])->save();

        $this->pasangPeranSiswa($pengguna);

        return $kataSandiAwal;
    }

    public function usernameDariNisn(?string $nisn): ?string
    {
        $username = preg_replace('/\s+/', '', trim((string) $nisn));

        return $username === '' ? null : $username;
    }

    public function buatKataSandiAcak(): string
    {
        return str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT);
    }

    private function pasangPeranSiswa(Pengguna $pengguna): void
    {
        $peranSiswa = Peran::where('kode', 'siswa')->first();

        if ($peranSiswa) {
            $pengguna->daftarPeran()->syncWithoutDetaching([$peranSiswa->id]);
        }
    }
}
