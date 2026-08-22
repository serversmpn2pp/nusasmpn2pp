<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ujian_cbt', function (Blueprint $table) {
            $table->string('alur', 30)->default('terpusat')->after('id')->index();
        });

        DB::table('ujian_cbt')
            ->whereIn('jenis_ujian_cbt_id', DB::table('jenis_ujian_cbt')->where('kode', 'ASESMEN_KELAS')->select('id'))
            ->update(['alur' => 'kelas']);

        $waktu = now();
        DB::table('izin')->updateOrInsert(
            ['kode' => 'cbt.asesmen_kelola'],
            [
                'kelompok' => 'CBT',
                'nama' => 'Kelola asesmen kelas',
                'deskripsi' => 'Membuat dan mengelola asesmen CBT pada kelas yang diampu.',
                'sistem' => true,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
        );

        $izinId = DB::table('izin')->where('kode', 'cbt.asesmen_kelola')->value('id');
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
        $izinId = DB::table('izin')->where('kode', 'cbt.asesmen_kelola')->value('id');

        if ($izinId) {
            DB::table('peran_izin')->where('izin_id', $izinId)->delete();
            DB::table('izin')->where('id', $izinId)->delete();
        }

        Schema::table('ujian_cbt', function (Blueprint $table) {
            $table->dropColumn('alur');
        });
    }
};
