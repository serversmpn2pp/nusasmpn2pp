<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $peranId = DB::table('peran')
            ->where('kode', 'wakil_pimpinan_kurikulum')
            ->value('id');
        $izinId = DB::table('izin')
            ->where('kode', 'guru_mapel.kelola')
            ->value('id');

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
        $peranId = DB::table('peran')
            ->where('kode', 'wakil_pimpinan_kurikulum')
            ->value('id');
        $izinId = DB::table('izin')
            ->where('kode', 'guru_mapel.kelola')
            ->value('id');

        if ($peranId && $izinId) {
            DB::table('peran_izin')
                ->where('peran_id', $peranId)
                ->where('izin_id', $izinId)
                ->delete();
        }
    }
};
