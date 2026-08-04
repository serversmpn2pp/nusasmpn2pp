<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $waktu = now();

        DB::table('izin')
            ->where('kode', 'poin_siswa.lapor')
            ->update([
                'nama' => 'Laporkan kejadian siswa',
                'deskripsi' => 'Membuat laporan kejadian siswa untuk diperiksa BK.',
                'updated_at' => $waktu,
            ]);

        $peranId = DB::table('peran')->where('kode', 'pegawai')->value('id');
        $izinId = DB::table('izin')->where('kode', 'poin_siswa.lapor')->value('id');

        if ($peranId && $izinId) {
            DB::table('peran_izin')->insertOrIgnore([
                'peran_id' => $peranId,
                'izin_id' => $izinId,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ]);
        }
    }

    public function down(): void
    {
        $peranId = DB::table('peran')->where('kode', 'pegawai')->value('id');
        $izinId = DB::table('izin')->where('kode', 'poin_siswa.lapor')->value('id');

        if ($peranId && $izinId) {
            DB::table('peran_izin')
                ->where('peran_id', $peranId)
                ->where('izin_id', $izinId)
                ->delete();
        }

        DB::table('izin')
            ->where('kode', 'poin_siswa.lapor')
            ->update([
                'nama' => 'Laporkan pelanggaran siswa',
                'deskripsi' => 'Membuat laporan pembinaan atau pelanggaran siswa.',
                'updated_at' => now(),
            ]);
    }
};
