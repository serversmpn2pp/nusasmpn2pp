<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jenis_ujian_cbt', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 40)->unique();
            $table->string('nama', 120)->unique();
            $table->text('deskripsi')->nullable();
            $table->boolean('memerlukan_token')->default(true);
            $table->boolean('dapat_diterapkan_ke_nilai')->default(true);
            $table->boolean('tampil_di_kartu_peserta')->default(true);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true)->index();
            $table->timestamps();
        });

        $waktu = now();
        DB::table('jenis_ujian_cbt')->insert([
            [
                'kode' => 'STS',
                'nama' => 'Sumatif Tengah Semester',
                'deskripsi' => 'Ujian tengah semester yang nilainya dapat diterapkan ke komponen STS.',
                'memerlukan_token' => true,
                'dapat_diterapkan_ke_nilai' => true,
                'tampil_di_kartu_peserta' => true,
                'urutan' => 1,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
            [
                'kode' => 'SAS',
                'nama' => 'Sumatif Akhir Semester',
                'deskripsi' => 'Ujian akhir semester yang nilainya dapat diterapkan ke komponen SAS.',
                'memerlukan_token' => true,
                'dapat_diterapkan_ke_nilai' => true,
                'tampil_di_kartu_peserta' => true,
                'urutan' => 2,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
            [
                'kode' => 'SAJ',
                'nama' => 'Sumatif Akhir Jenjang',
                'deskripsi' => 'Ujian akhir jenjang untuk kelas IX yang nilainya dapat diterapkan ke komponen SAJ.',
                'memerlukan_token' => true,
                'dapat_diterapkan_ke_nilai' => true,
                'tampil_di_kartu_peserta' => true,
                'urutan' => 3,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
            [
                'kode' => 'TKA',
                'nama' => 'Tes Kemampuan Akademik',
                'deskripsi' => 'Profil ujian untuk simulasi atau pelaksanaan TKA berbasis CBT.',
                'memerlukan_token' => true,
                'dapat_diterapkan_ke_nilai' => true,
                'tampil_di_kartu_peserta' => true,
                'urutan' => 4,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
            [
                'kode' => 'SIMULASI_AN',
                'nama' => 'Simulasi AN',
                'deskripsi' => 'Latihan literasi dan numerasi yang dapat digunakan untuk persiapan asesmen nasional.',
                'memerlukan_token' => true,
                'dapat_diterapkan_ke_nilai' => false,
                'tampil_di_kartu_peserta' => true,
                'urutan' => 5,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
            [
                'kode' => 'OSN',
                'nama' => 'OSN',
                'deskripsi' => 'Profil ujian untuk latihan atau seleksi olimpiade sains.',
                'memerlukan_token' => true,
                'dapat_diterapkan_ke_nilai' => false,
                'tampil_di_kartu_peserta' => true,
                'urutan' => 6,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('jenis_ujian_cbt');
    }
};
