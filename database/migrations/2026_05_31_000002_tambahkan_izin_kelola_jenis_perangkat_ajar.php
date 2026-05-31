<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $waktu = now();

        DB::table('izin')->updateOrInsert(
            ['kode' => 'perangkat_ajar.jenis_kelola'],
            [
                'kelompok' => 'Kurikulum',
                'nama' => 'Kelola jenis perangkat ajar',
                'deskripsi' => 'Mengelola daftar jenis dokumen perangkat ajar yang perlu diunggah guru.',
                'sistem' => true,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
        );

        $izinId = DB::table('izin')->where('kode', 'perangkat_ajar.jenis_kelola')->value('id');
        $peranId = DB::table('peran')->where('kode', 'wakil_pimpinan_kurikulum')->value('id');

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
        $izinId = DB::table('izin')->where('kode', 'perangkat_ajar.jenis_kelola')->value('id');

        if ($izinId) {
            DB::table('peran_izin')->where('izin_id', $izinId)->delete();
            DB::table('izin')->where('id', $izinId)->delete();
        }
    }
};
