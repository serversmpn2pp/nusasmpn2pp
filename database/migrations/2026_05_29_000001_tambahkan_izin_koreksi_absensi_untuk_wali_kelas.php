<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $peranId = DB::table('peran')->where('kode', 'wali_kelas')->value('id');
        $izinId = DB::table('izin')->where('kode', 'absensi.koreksi')->value('id');

        if (! $peranId || ! $izinId) {
            return;
        }

        DB::table('peran_izin')->insertOrIgnore([
            'peran_id' => $peranId,
            'izin_id' => $izinId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
    }
};
