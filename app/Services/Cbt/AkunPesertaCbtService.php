<?php

namespace App\Services\Cbt;

use App\Models\AkunPesertaCbt;
use App\Models\AnggotaKelas;
use App\Models\KelasUjianCbt;
use App\Models\UjianCbt;
use Illuminate\Support\Str;

class AkunPesertaCbtService
{
    public function ambilAtauBuat(UjianCbt $ujianCbt, KelasUjianCbt $kelasUjianCbt, AnggotaKelas $anggota, int $urutan): AkunPesertaCbt
    {
        $ujianCbt->loadMissing(['jenisUjianCbt', 'tahunPelajaran']);
        $kelasUjianCbt->loadMissing('kelas');
        $anggota->loadMissing('siswa');

        return AkunPesertaCbt::firstOrCreate(
            [
                'jenis_ujian_cbt_id' => $ujianCbt->jenis_ujian_cbt_id,
                'tahun_pelajaran_id' => $ujianCbt->tahun_pelajaran_id,
                'semester' => $ujianCbt->semester,
                'anggota_kelas_id' => $anggota->id,
            ],
            [
                'nomor_peserta' => $this->buatNomorPeserta($ujianCbt, $kelasUjianCbt, $anggota, $urutan),
                'username' => $this->buatUsername($ujianCbt, $anggota),
                'kata_sandi' => $this->buatKataSandi(),
                'kode_qr' => $this->buatKodeQr(),
                'status' => 'aktif',
            ],
        );
    }

    private function buatNomorPeserta(UjianCbt $ujianCbt, KelasUjianCbt $kelasUjianCbt, AnggotaKelas $anggota, int $urutan): string
    {
        $kodeJenis = $this->rapikanKode($ujianCbt->jenisUjianCbt?->kode ?: 'CBT');
        $kodeTahun = $this->rapikanKode($ujianCbt->tahunPelajaran?->nama ?: (string) $ujianCbt->tahun_pelajaran_id);
        $kodeSemester = mb_strtoupper(mb_substr($ujianCbt->semester, 0, 1));
        $kodeKelas = $this->rapikanKode($kelasUjianCbt->kelas?->nama ?: 'KELAS');
        $nomor = $anggota->nomor_absen ?: $urutan;
        $basis = substr("{$kodeJenis}{$kodeSemester}{$kodeTahun}-{$kodeKelas}-" . str_pad((string) $nomor, 3, '0', STR_PAD_LEFT), 0, 74);
        $hasil = $basis;
        $suffix = 2;

        while (AkunPesertaCbt::where('nomor_peserta', $hasil)->exists()) {
            $hasil = substr($basis, 0, 70) . '-' . $suffix;
            $suffix++;
        }

        return $hasil;
    }

    private function buatUsername(UjianCbt $ujianCbt, AnggotaKelas $anggota): string
    {
        $siswa = $anggota->siswa;
        $basis = $this->rapikanKode($siswa?->nisn ?: $siswa?->nis ?: 'SISWA' . $anggota->siswa_id);
        $hasil = substr($basis, 0, 74);
        $suffix = 2;

        while (
            AkunPesertaCbt::query()
                ->where('jenis_ujian_cbt_id', $ujianCbt->jenis_ujian_cbt_id)
                ->where('tahun_pelajaran_id', $ujianCbt->tahun_pelajaran_id)
                ->where('semester', $ujianCbt->semester)
                ->where('username', $hasil)
                ->exists()
        ) {
            $hasil = substr($basis, 0, 70) . '-' . $suffix;
            $suffix++;
        }

        return $hasil;
    }

    private function rapikanKode(string $kode): string
    {
        $hasil = preg_replace('/[^A-Za-z0-9]+/', '', $kode) ?: 'CBT';

        return mb_strtoupper($hasil);
    }

    private function buatKataSandi(): string
    {
        return (string) random_int(100000, 999999);
    }

    private function buatKodeQr(): string
    {
        do {
            $kode = 'CBT-' . Str::upper(Str::random(18));
        } while (AkunPesertaCbt::where('kode_qr', $kode)->exists());

        return $kode;
    }
}
