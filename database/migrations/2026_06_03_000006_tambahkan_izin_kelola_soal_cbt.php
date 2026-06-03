<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $waktu = now();

        DB::table('izin')->updateOrInsert(
            ['kode' => 'cbt.soal_kelola'],
            [
                'kelompok' => 'CBT',
                'nama' => 'Kelola soal CBT',
                'deskripsi' => 'Membuat dan mengelola bank soal CBT sesuai cakupan mata pelajaran.',
                'sistem' => true,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
        );

        $izinId = DB::table('izin')->where('kode', 'cbt.soal_kelola')->value('id');
        $peranIds = DB::table('peran')
            ->whereIn('kode', ['administrator', 'wakil_pimpinan_kurikulum', 'guru_mapel'])
            ->pluck('id', 'kode');

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
        $izinId = DB::table('izin')->where('kode', 'cbt.soal_kelola')->value('id');

        if ($izinId) {
            DB::table('peran_izin')->where('izin_id', $izinId)->delete();
            DB::table('izin')->where('id', $izinId)->delete();
        }
    }
};
