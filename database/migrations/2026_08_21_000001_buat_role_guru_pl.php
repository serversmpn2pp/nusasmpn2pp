<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const KODE_PERAN = 'guru_pl';

    private const KODE_IZIN_TERBATAS = 'absensi.koreksi_hari_ini';

    public function up(): void
    {
        $waktu = now();

        DB::table('izin')->updateOrInsert(
            ['kode' => self::KODE_IZIN_TERBATAS],
            [
                'kelompok' => 'Presensi',
                'nama' => 'Koreksi presensi siswa hari ini',
                'deskripsi' => 'Melihat dan mengoreksi presensi siswa hanya pada hari berjalan tanpa akses koreksi presensi pegawai.',
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
        );

        DB::table('peran')->updateOrInsert(
            ['kode' => self::KODE_PERAN],
            [
                'nama' => 'Guru PL',
                'deskripsi' => 'Guru praktik lapangan yang membantu operasional sekolah dengan akses terbatas dan tetap tercatat sebagai pegawai.',
                'sistem' => true,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
        );

        $peranId = DB::table('peran')->where('kode', self::KODE_PERAN)->value('id');
        $izinIds = DB::table('izin')
            ->whereIn('kode', [
                'beranda.akses',
                'pegawai.profil',
                'absensi_pegawai.pribadi',
                'poin_siswa.lapor',
                'ibadah.scan',
                self::KODE_IZIN_TERBATAS,
            ])
            ->pluck('id');

        foreach ($izinIds as $izinId) {
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
        $peranId = DB::table('peran')->where('kode', self::KODE_PERAN)->value('id');
        $izinId = DB::table('izin')->where('kode', self::KODE_IZIN_TERBATAS)->value('id');

        if ($peranId) {
            DB::table('pengguna_peran')->where('peran_id', $peranId)->delete();
            DB::table('peran_izin')->where('peran_id', $peranId)->delete();
            DB::table('peran')->where('id', $peranId)->delete();
        }

        if ($izinId) {
            DB::table('peran_izin')->where('izin_id', $izinId)->delete();
            DB::table('izin')->where('id', $izinId)->delete();
        }
    }
};
