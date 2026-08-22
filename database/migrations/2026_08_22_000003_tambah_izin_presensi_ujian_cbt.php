<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const KODE_IZIN = 'cbt.presensi';

    public function up(): void
    {
        $waktu = now();

        DB::table('izin')->updateOrInsert(
            ['kode' => self::KODE_IZIN],
            [
                'kelompok' => 'CBT',
                'nama' => 'Catat presensi ujian CBT',
                'deskripsi' => 'Memindai kartu pelajar dan mencatat kehadiran peserta pada ruang CBT yang diawasi.',
                'sistem' => true,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
        );

        $izinId = DB::table('izin')->where('kode', self::KODE_IZIN)->value('id');
        $peranIds = DB::table('peran')
            ->whereIn('kode', ['administrator', 'wakil_pimpinan_kurikulum', 'guru_mapel'])
            ->pluck('id');

        foreach ($peranIds as $peranId) {
            DB::table('peran_izin')->insertOrIgnore([
                'peran_id' => $peranId,
                'izin_id' => $izinId,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ]);
        }
    }

    public function down(): void
    {
        $izinId = DB::table('izin')->where('kode', self::KODE_IZIN)->value('id');

        if ($izinId) {
            DB::table('peran_izin')->where('izin_id', $izinId)->delete();
            DB::table('izin')->where('id', $izinId)->delete();
        }
    }
};
