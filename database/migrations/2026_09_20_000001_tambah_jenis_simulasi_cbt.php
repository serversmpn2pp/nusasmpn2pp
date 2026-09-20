<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('jenis_ujian_cbt')->insertOrIgnore([
            'kode' => 'SIMULASI_CBT', 'nama' => 'Simulasi CBT',
            'deskripsi' => 'Paket latihan umum berisi 12 soal. Tidak masuk nilai akademik.',
            'memerlukan_token' => true, 'dapat_diterapkan_ke_nilai' => false,
            'aktif' => true, 'urutan' => 6, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // Retain the type so an existing simulation does not lose its reference.
    }
};
