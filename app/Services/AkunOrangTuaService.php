<?php

namespace App\Services;

use App\Models\OrangTuaWali;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AkunOrangTuaService
{
    public function buat(Siswa $siswa): Pengguna
    {
        if ($siswa->orangTuaWali()->exists()) {
            throw ValidationException::withMessages([
                'akun' => 'Siswa ini sudah terhubung dengan akun orang tua.',
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
                'akun' => 'Username '.$username.' sudah digunakan akun lain.',
            ]);
        }

        return DB::transaction(function () use ($siswa, $username) {
            $identitas = $this->identitasDariSiswa($siswa);
            $kataSandiAwal = $this->buatKataSandiAcak();
            $pengguna = Pengguna::create([
                'nama' => $identitas['nama'],
                'username' => $username,
                'kata_sandi' => Hash::make($kataSandiAwal),
                'kata_sandi_awal' => $kataSandiAwal,
                'wajib_ganti_kata_sandi' => true,
                'peran' => 'orang_tua',
                'aktif' => $siswa->aktif,
                'akun_sistem' => false,
            ]);
            $orangTua = OrangTuaWali::create([
                'pengguna_id' => $pengguna->id,
                'siswa_acuan_username_id' => $siswa->id,
                'nama_lengkap' => $identitas['nama'],
                'nomor_wa' => $identitas['nomor_wa'],
            ]);

            $orangTua->siswa()->attach($siswa->id, [
                'hubungan' => $identitas['hubungan'],
                'utama' => true,
            ]);
            $this->pasangPeranOrangTua($pengguna);

            return $pengguna;
        });
    }

    public function resetKataSandi(Pengguna $pengguna): string
    {
        if ($pengguna->akun_sistem || ! $pengguna->orangTuaWali()->exists()) {
            throw ValidationException::withMessages([
                'akun' => 'Akun yang dipilih bukan akun orang tua.',
            ]);
        }

        $kataSandiAwal = $this->buatKataSandiAcak();

        $pengguna->forceFill([
            'kata_sandi' => Hash::make($kataSandiAwal),
            'kata_sandi_awal' => $kataSandiAwal,
            'wajib_ganti_kata_sandi' => true,
        ])->save();

        $this->pasangPeranOrangTua($pengguna);

        return $kataSandiAwal;
    }

    public function usernameDariNisn(?string $nisn): ?string
    {
        $nisn = preg_replace('/\s+/', '', trim((string) $nisn));

        return $nisn === '' ? null : 'ORT-'.mb_strtoupper($nisn);
    }

    public function buatKataSandiAcak(): string
    {
        return str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT);
    }

    private function identitasDariSiswa(Siswa $siswa): array
    {
        $daftar = [
            'ayah' => [
                'nama' => $siswa->nama_ayah,
                'nomor_wa' => $siswa->nomor_wa_ayah,
                'hubungan' => 'ayah',
            ],
            'ibu' => [
                'nama' => $siswa->nama_ibu,
                'nomor_wa' => $siswa->nomor_wa_ibu,
                'hubungan' => 'ibu',
            ],
            'wali' => [
                'nama' => $siswa->nama_wali,
                'nomor_wa' => $siswa->nomor_wa_wali,
                'hubungan' => $siswa->hubungan_wali ?: 'wali',
            ],
        ];
        $kontakUtama = $siswa->kontak_absensi_utama;
        $identitas = isset($daftar[$kontakUtama]) ? $daftar[$kontakUtama] : null;

        if (! $identitas || (blank($identitas['nama']) && blank($identitas['nomor_wa']))) {
            $identitas = collect($daftar)
                ->first(fn ($item) => filled($item['nama']) || filled($item['nomor_wa']));
        }

        $identitas ??= [
            'nama' => null,
            'nomor_wa' => null,
            'hubungan' => 'wali',
        ];
        $identitas['nama'] = filled($identitas['nama'])
            ? trim((string) $identitas['nama'])
            : 'Orang Tua/Wali '.$siswa->nama_lengkap;

        return $identitas;
    }

    private function pasangPeranOrangTua(Pengguna $pengguna): void
    {
        $peran = Peran::where('kode', 'orang_tua')->first();

        if ($peran) {
            $pengguna->daftarPeran()->syncWithoutDetaching([$peran->id]);
        }
    }
}
