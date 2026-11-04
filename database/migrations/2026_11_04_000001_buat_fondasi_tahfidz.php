<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kelompok_tahfidz', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_pelajaran_id')->constrained('tahun_pelajaran')->restrictOnDelete();
            $table->foreignId('pembimbing_pegawai_id')->constrained('pegawai')->restrictOnDelete();
            $table->string('nama', 120);
            $table->boolean('aktif')->default(true);
            $table->unsignedInteger('versi')->default(1);
            $table->timestamps();
            $table->unique(['tahun_pelajaran_id', 'nama']);
            $table->index('pembimbing_pegawai_id');
        });

        Schema::create('anggota_tahfidz', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelompok_tahfidz_id')->constrained('kelompok_tahfidz')->restrictOnDelete();
            $table->foreignId('siswa_id')->constrained('siswa')->restrictOnDelete();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
            $table->unique(['kelompok_tahfidz_id', 'siswa_id']);
            $table->index('siswa_id');
        });

        Schema::create('surah_tahfidz', function (Blueprint $table) {
            $table->unsignedSmallInteger('nomor')->primary();
            $table->string('nama', 80);
        });

        // Urutan surah dalam Juz 30: https://quran.com/id/juz/30
        $namaSurah = [
            "An-Naba'", "An-Nazi'at", "'Abasa", 'At-Takwir', 'Al-Infitar', 'Al-Mutaffifin', 'Al-Insyiqaq',
            'Al-Buruj', 'At-Tariq', "Al-A'la", 'Al-Gasyiyah', 'Al-Fajr', 'Al-Balad', 'Asy-Syams',
            'Al-Lail', 'Ad-Duha', 'Asy-Syarh', 'At-Tin', "Al-'Alaq", 'Al-Qadr', 'Al-Bayyinah',
            'Az-Zalzalah', "Al-'Adiyat", "Al-Qari'ah", 'At-Takasur', "Al-'Asr", 'Al-Humazah',
            'Al-Fil', 'Quraisy', "Al-Ma'un", 'Al-Kausar', 'Al-Kafirun', 'An-Nasr', 'Al-Masad (Al-Lahab)',
            'Al-Ikhlas', 'Al-Falaq', 'An-Nas',
        ];
        DB::table('surah_tahfidz')->insert(array_map(
            fn (int $i, string $nama) => ['nomor' => 78 + $i, 'nama' => $nama],
            array_keys($namaSurah), $namaSurah,
        ));

        $waktu = now();
        $izin = DB::table('izin')->insertGetId([
            'kode' => 'tahfidz.kelola', 'nama' => 'Kelola kelompok Tahfidz', 'kelompok' => 'Tahfidz',
            'deskripsi' => 'Mengatur kelompok, pembimbing, dan peserta Tahfidz.',
            'aktif' => true, 'sistem' => true, 'created_at' => $waktu, 'updated_at' => $waktu,
        ]);
        $peran = DB::table('peran')->insertGetId([
            'kode' => 'koordinator_tahfidz', 'nama' => 'Koordinator Tahfidz',
            'deskripsi' => 'Mengelola program Tahfidz di seluruh sekolah.',
            'aktif' => true, 'sistem' => true, 'created_at' => $waktu, 'updated_at' => $waktu,
        ]);
        DB::table('peran_izin')->insert([
            'peran_id' => $peran, 'izin_id' => $izin, 'created_at' => $waktu, 'updated_at' => $waktu,
        ]);
    }

    public function down(): void
    {
        DB::table('peran')->where('kode', 'koordinator_tahfidz')->delete();
        DB::table('izin')->where('kode', 'tahfidz.kelola')->delete();
        Schema::dropIfExists('anggota_tahfidz');
        Schema::dropIfExists('kelompok_tahfidz');
        Schema::dropIfExists('surah_tahfidz');
    }
};
