<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $waktu = now();

        DB::table('izin')->updateOrInsert(
            ['kode' => 'survei.hasil_pribadi'],
            [
                'kelompok' => 'Kurikulum',
                'nama' => 'Lihat hasil survei pembelajaran sendiri',
                'deskripsi' => 'Melihat hasil survei anonim untuk mata pelajaran dan kelas yang diampu sendiri.',
                'sistem' => true,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
        );

        $izinId = DB::table('izin')->where('kode', 'survei.hasil_pribadi')->value('id');
        $peranId = DB::table('peran')->where('kode', 'guru_mapel')->value('id');

        if ($izinId && $peranId) {
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
        $izinId = DB::table('izin')->where('kode', 'survei.hasil_pribadi')->value('id');

        if ($izinId) {
            DB::table('peran_izin')->where('izin_id', $izinId)->delete();
            DB::table('izin')->where('id', $izinId)->delete();
        }
    }
};
