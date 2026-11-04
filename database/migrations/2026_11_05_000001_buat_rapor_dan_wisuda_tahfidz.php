<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('target_rapor_tahfidz', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_pelajaran_id')->constrained('tahun_pelajaran')->restrictOnDelete();
            $table->unsignedSmallInteger('tingkat');
            $table->unsignedSmallInteger('target');
            $table->string('mode', 12);
            $table->json('surah');
            $table->date('tanggal_rapor');
            $table->unsignedSmallInteger('batas_cukup')->default(70);
            $table->unsignedSmallInteger('batas_baik')->default(80);
            $table->unsignedSmallInteger('batas_sangat_baik')->default(90);
            $table->timestamps();
            $table->unique(['tahun_pelajaran_id', 'tingkat']);
        });

        Schema::create('wisuda_tahfidz', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 150);
            $table->date('tanggal');
            $table->string('lokasi', 120);
            $table->string('kepala_sekolah', 150);
            $table->timestamps();
        });

        Schema::create('kelulusan_tahfidz', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->unique()->constrained('siswa')->restrictOnDelete();
            $table->foreignId('anggota_tahfidz_id')->constrained('anggota_tahfidz')->restrictOnDelete();
            $table->foreignId('pembimbing_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->string('nama_pembimbing', 200);
            $table->timestamp('diverifikasi_pada');
            $table->foreignId('wisuda_tahfidz_id')->nullable()->constrained('wisuda_tahfidz')->restrictOnDelete();
            $table->string('nomor_sertifikat', 40)->nullable()->unique();
            $table->string('nama_penerima', 200)->nullable();
            $table->foreignId('penerbit_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamp('diterbitkan_pada')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelulusan_tahfidz');
        Schema::dropIfExists('wisuda_tahfidz');
        Schema::dropIfExists('target_rapor_tahfidz');
    }
};
