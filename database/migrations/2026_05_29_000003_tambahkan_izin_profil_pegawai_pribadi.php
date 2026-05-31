<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $izinId = DB::table('izin')->where('kode', 'pegawai.profil')->value('id');

        if (! $izinId) {
            $izinId = DB::table('izin')->insertGetId([
                'kelompok' => 'Pegawai',
                'nama' => 'Kelola profil pegawai sendiri',
                'kode' => 'pegawai.profil',
                'deskripsi' => 'Melihat dan memperbarui data pribadi akun pegawai sendiri.',
                'sistem' => true,
                'aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $peranPegawaiId = DB::table('peran')->where('kode', 'pegawai')->value('id');

        if (! $peranPegawaiId) {
            return;
        }

        DB::table('peran_izin')->insertOrIgnore([
            'peran_id' => $peranPegawaiId,
            'izin_id' => $izinId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
    }
};
