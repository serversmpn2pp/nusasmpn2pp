<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('peran_izin')
            ->whereIn('peran_id', DB::table('peran')->where('kode', 'guru_mapel')->select('id'))
            ->whereIn('izin_id', DB::table('izin')->whereIn('kode', ['absensi.koreksi', 'absensi.koreksi_hari_ini'])->select('id'))
            ->delete();
    }

    public function down(): void
    {
        // Do not restore an over-broad permission on rollback.
    }
};
