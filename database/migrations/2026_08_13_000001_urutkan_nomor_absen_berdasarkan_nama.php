<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('anggota_kelas')
            ->distinct()
            ->orderBy('kelas_id')
            ->pluck('kelas_id')
            ->each(function ($kelasId) {
                $anggota = DB::table('anggota_kelas as anggota')
                    ->join('siswa', 'siswa.id', '=', 'anggota.siswa_id')
                    ->where('anggota.kelas_id', $kelasId)
                    ->orderByRaw('LOWER(TRIM(siswa.nama_lengkap))')
                    ->orderBy('anggota.id')
                    ->pluck('anggota.id');

                DB::table('anggota_kelas')->whereIn('id', $anggota)->update(['nomor_absen' => null]);

                $anggota->each(function ($anggotaId, $indeks) {
                    DB::table('anggota_kelas')
                        ->where('id', $anggotaId)
                        ->update(['nomor_absen' => $indeks + 1]);
                });
            });
    }

    public function down(): void
    {
        // Nomor lama tidak dipulihkan karena urutan alfabetis sudah menjadi aturan kelas.
    }
};
