<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $peranId = DB::table('peran')->where('kode', 'guru_mapel')->value('id');
        $izinId = DB::table('izin')->where('kode', 'nilai.komponen_kelola')->value('id');

        if ($peranId && $izinId) {
            DB::table('peran_izin')->insertOrIgnore([
                'peran_id' => $peranId,
                'izin_id' => $izinId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        $peranId = DB::table('peran')->where('kode', 'guru_mapel')->value('id');
        $izinId = DB::table('izin')->where('kode', 'nilai.komponen_kelola')->value('id');

        if ($peranId && $izinId) {
            DB::table('peran_izin')
                ->where('peran_id', $peranId)
                ->where('izin_id', $izinId)
                ->delete();
        }
    }
};
