<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $sekarang = now();

        DB::table('izin')->updateOrInsert(
            ['kode' => 'poin_siswa.sahkan_wakil'],
            [
                'kelompok' => 'Pembinaan dan Poin',
                'nama' => 'Sahkan pelanggaran berpoin',
                'deskripsi' => 'Mengesahkan atau mengembalikan rekomendasi pelanggaran berpoin dari BK sebagai Wakil Kesiswaan.',
                'sistem' => true,
                'aktif' => true,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ],
        );

        $izinId = DB::table('izin')->where('kode', 'poin_siswa.sahkan_wakil')->value('id');
        $peranIds = DB::table('peran')
            ->whereIn('kode', ['administrator', 'wakil_pimpinan_kesiswaan'])
            ->pluck('id');

        foreach ($peranIds as $peranId) {
            DB::table('peran_izin')->insertOrIgnore([
                'peran_id' => $peranId,
                'izin_id' => $izinId,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ]);
        }
    }

    public function down(): void
    {
        $izinId = DB::table('izin')->where('kode', 'poin_siswa.sahkan_wakil')->value('id');

        if ($izinId) {
            DB::table('peran_izin')->where('izin_id', $izinId)->delete();
            DB::table('izin')->where('id', $izinId)->delete();
        }
    }
};
