<?php

namespace App\Services\Kelas;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class UrutkanNomorAbsenKelasService
{
    public function jalankan(int $kelasId): void
    {
        DB::transaction(function () use ($kelasId) {
            $kelasAda = DB::table('kelas')
                ->where('id', $kelasId)
                ->lockForUpdate()
                ->exists();

            if (! $kelasAda) {
                return;
            }

            $anggota = DB::table('anggota_kelas as anggota')
                ->join('siswa', 'siswa.id', '=', 'anggota.siswa_id')
                ->where('anggota.kelas_id', $kelasId)
                ->select(['anggota.id', 'siswa.nama_lengkap'])
                ->lockForUpdate()
                ->get()
                ->sort(function ($pertama, $kedua) {
                    $perbandingan = strnatcasecmp(
                        $this->normalisasiNama($pertama->nama_lengkap),
                        $this->normalisasiNama($kedua->nama_lengkap),
                    );

                    return $perbandingan !== 0
                        ? $perbandingan
                        : $pertama->id <=> $kedua->id;
                })
                ->values();

            if ($anggota->isEmpty()) {
                return;
            }

            $daftarId = $anggota->pluck('id');
            DB::table('anggota_kelas')->whereIn('id', $daftarId)->update(['nomor_absen' => null]);

            foreach ($anggota as $indeks => $item) {
                DB::table('anggota_kelas')
                    ->where('id', $item->id)
                    ->update(['nomor_absen' => $indeks + 1]);
            }
        }, 3);
    }

    private function normalisasiNama(?string $nama): string
    {
        return Str::lower(Str::squish((string) $nama));
    }
}
