<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $labelIzin = [
            'absensi.lihat' => ['Presensi', 'Lihat presensi', 'Melihat data presensi.'],
            'absensi.scan' => ['Presensi', 'Scan presensi', 'Membuka dan menggunakan halaman scan presensi.'],
            'absensi.koreksi' => ['Presensi', 'Koreksi presensi', 'Mengoreksi presensi siswa.'],
            'absensi.pengaturan_kelola' => ['Presensi', 'Kelola pengaturan presensi', 'Mengatur jam dan hari presensi.'],
            'absensi.laporan' => ['Presensi', 'Laporan presensi', 'Melihat laporan presensi.'],
            'absensi_pegawai.pribadi' => ['Presensi Pegawai', 'Lihat presensi pegawai pribadi', 'Melihat rekap dan laporan presensi milik akun pegawai sendiri.'],
        ];

        foreach ($labelIzin as $kode => [$kelompok, $nama, $deskripsi]) {
            DB::table('izin')->where('kode', $kode)->update(compact('kelompok', 'nama', 'deskripsi'));
        }

        DB::table('peran')->where('kode', 'wali_kelas')->update([
            'deskripsi' => 'Melihat data kelas binaan, presensi, nilai, dan perilaku siswa.',
        ]);
        DB::table('peran')->where('kode', 'pegawai')->update([
            'deskripsi' => 'Akses dasar untuk pegawai: beranda, profil, presensi pribadi, dan pelaporan kejadian siswa.',
        ]);
    }

    public function down(): void
    {
        $labelIzin = [
            'absensi.lihat' => ['Absensi', 'Lihat absensi', 'Melihat data absensi.'],
            'absensi.scan' => ['Absensi', 'Scan absensi', 'Membuka dan menggunakan halaman scan absensi.'],
            'absensi.koreksi' => ['Absensi', 'Koreksi absensi', 'Mengoreksi absensi siswa.'],
            'absensi.pengaturan_kelola' => ['Absensi', 'Kelola pengaturan absensi', 'Mengatur jam dan hari absensi.'],
            'absensi.laporan' => ['Absensi', 'Laporan absensi', 'Melihat laporan absensi.'],
            'absensi_pegawai.pribadi' => ['Absensi Pegawai', 'Lihat absensi pegawai pribadi', 'Melihat rekap dan laporan absensi milik akun pegawai sendiri.'],
        ];

        foreach ($labelIzin as $kode => [$kelompok, $nama, $deskripsi]) {
            DB::table('izin')->where('kode', $kode)->update(compact('kelompok', 'nama', 'deskripsi'));
        }

        DB::table('peran')->where('kode', 'wali_kelas')->update([
            'deskripsi' => 'Melihat data kelas binaan, absensi, nilai, dan perilaku siswa.',
        ]);
        DB::table('peran')->where('kode', 'pegawai')->update([
            'deskripsi' => 'Akses dasar untuk pegawai: beranda, profil, absensi pribadi, dan pelaporan kejadian siswa.',
        ]);
    }
};
