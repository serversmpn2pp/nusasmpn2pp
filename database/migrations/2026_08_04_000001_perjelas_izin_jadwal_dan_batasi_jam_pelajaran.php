<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('izin')
            ->where('kode', 'jadwal.lihat')
            ->update([
                'deskripsi' => 'Melihat jadwal pelajaran sesuai cakupan pengguna.',
                'updated_at' => now(),
            ]);

        DB::table('izin')
            ->where('kode', 'jadwal.kelola')
            ->update([
                'deskripsi' => 'Mengelola jadwal pelajaran.',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('izin')
            ->where('kode', 'jadwal.lihat')
            ->update([
                'deskripsi' => 'Melihat jam pelajaran dan jadwal pelajaran.',
                'updated_at' => now(),
            ]);

        DB::table('izin')
            ->where('kode', 'jadwal.kelola')
            ->update([
                'deskripsi' => 'Mengelola jam pelajaran dan jadwal pelajaran.',
                'updated_at' => now(),
            ]);
    }
};
