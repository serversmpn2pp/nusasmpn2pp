<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $izinId = DB::table('izin')->where('kode', 'absensi.scan')->value('id');
        $peranIds = DB::table('peran')
            ->whereIn('kode', ['satpam', 'petugas_kebersihan'])
            ->pluck('id');

        if (! $izinId || $peranIds->isEmpty()) {
            return;
        }

        $waktu = now();
        $baris = $peranIds->map(fn ($peranId) => [
            'peran_id' => $peranId,
            'izin_id' => $izinId,
            'created_at' => $waktu,
            'updated_at' => $waktu,
        ])->all();

        DB::table('peran_izin')->insertOrIgnore($baris);
    }

    public function down(): void
    {
        $izinId = DB::table('izin')->where('kode', 'absensi.scan')->value('id');
        $peranKebersihanId = DB::table('peran')->where('kode', 'petugas_kebersihan')->value('id');

        if ($izinId && $peranKebersihanId) {
            DB::table('peran_izin')
                ->where('peran_id', $peranKebersihanId)
                ->where('izin_id', $izinId)
                ->delete();
        }
    }
};
