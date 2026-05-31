<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $waktu = now();

        DB::table('izin')->updateOrInsert(
            ['kode' => 'jadwal.pribadi'],
            [
                'kelompok' => 'Akademik',
                'nama' => 'Lihat jadwal mengajar pribadi',
                'deskripsi' => 'Melihat jadwal mengajar milik akun pegawai sendiri.',
                'sistem' => true,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
        );

        $izinPribadiId = DB::table('izin')->where('kode', 'jadwal.pribadi')->value('id');
        $izinLihatId = DB::table('izin')->where('kode', 'jadwal.lihat')->value('id');
        $guruMapelId = DB::table('peran')->where('kode', 'guru_mapel')->value('id');

        if ($izinPribadiId && $guruMapelId) {
            DB::table('peran_izin')->insertOrIgnore([
                'peran_id' => $guruMapelId,
                'izin_id' => $izinPribadiId,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ]);
        }

        if ($izinLihatId && $guruMapelId) {
            DB::table('peran_izin')
                ->where('peran_id', $guruMapelId)
                ->where('izin_id', $izinLihatId)
                ->delete();
        }
    }

    public function down(): void
    {
        $izinPribadiId = DB::table('izin')->where('kode', 'jadwal.pribadi')->value('id');
        $izinLihatId = DB::table('izin')->where('kode', 'jadwal.lihat')->value('id');
        $guruMapelId = DB::table('peran')->where('kode', 'guru_mapel')->value('id');

        if ($izinPribadiId) {
            DB::table('peran_izin')->where('izin_id', $izinPribadiId)->delete();
            DB::table('izin')->where('id', $izinPribadiId)->delete();
        }

        if ($izinLihatId && $guruMapelId) {
            DB::table('peran_izin')->insertOrIgnore([
                'peran_id' => $guruMapelId,
                'izin_id' => $izinLihatId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
