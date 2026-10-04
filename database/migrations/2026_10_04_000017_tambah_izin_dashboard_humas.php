<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('izin')->updateOrInsert(['kode' => 'dashboard_humas.lihat'], [
            'nama' => 'Lihat dashboard dan ringkasan kinerja Humas', 'kelompok' => 'Humas',
            'deskripsi' => 'Dashboard mengikuti izin tiap sumber data; tidak membuka identitas pengaduan atau kontak privat.',
            'sistem' => true, 'aktif' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $izinId = DB::table('izin')->where('kode', 'dashboard_humas.lihat')->value('id');
        foreach (DB::table('peran')->whereIn('kode', ['administrator', 'wakil_pimpinan_humas', 'pimpinan'])->pluck('id') as $id) {
            DB::table('peran_izin')->insertOrIgnore(['peran_id' => $id, 'izin_id' => $izinId, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $id = DB::table('izin')->where('kode', 'dashboard_humas.lihat')->value('id');
        DB::table('peran_izin')->where('izin_id', $id)->delete();
        DB::table('izin')->where('id', $id)->delete();
    }
};
