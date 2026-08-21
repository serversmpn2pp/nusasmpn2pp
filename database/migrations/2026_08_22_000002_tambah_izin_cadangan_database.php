<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const KODE_IZIN = 'cadangan_database.kelola';

    public function up(): void
    {
        $waktu = now();

        DB::table('izin')->updateOrInsert(
            ['kode' => self::KODE_IZIN],
            [
                'kelompok' => 'Keamanan',
                'nama' => 'Kelola backup dan restore database',
                'deskripsi' => 'Membuat, mengunduh, menghapus, dan memulihkan cadangan database NUSA.',
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
        );

        $peranId = DB::table('peran')->where('kode', 'administrator')->value('id');
        $izinId = DB::table('izin')->where('kode', self::KODE_IZIN)->value('id');

        if ($peranId && $izinId) {
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
