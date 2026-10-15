<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('praobservasi_supervisi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sesi_supervisi_id')->unique()->constrained('sesi_supervisi')->restrictOnDelete();
            $t->unsignedInteger('versi')->default(1);
            $t->string('status', 20)->default('draf');
            $t->json('isian');
            $t->text('catatan_supervisor')->nullable();
            $t->timestamp('dikirim_pada')->nullable();
            $t->foreignId('pengubah_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('lampiran_supervisi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('praobservasi_supervisi_id')->constrained('praobservasi_supervisi')->restrictOnDelete();
            $t->string('lokasi_file');
            $t->string('nama_file_asli', 200);
            $t->string('tipe_file', 150);
            $t->unsignedBigInteger('ukuran_file');
            $t->foreignId('pengunggah_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('penilaian_supervisi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sesi_supervisi_id')->unique()->constrained('sesi_supervisi')->restrictOnDelete();
            $t->foreignId('supervisor_pegawai_id')->constrained('pegawai')->restrictOnDelete();
            $t->unsignedInteger('praobservasi_versi');
            $t->unsignedInteger('versi')->default(1);
            $t->string('status', 20)->default('draf');
            $t->date('tanggal_observasi')->nullable();
            $t->json('nilai');
            $t->text('kekuatan')->nullable();
            $t->text('perbaikan')->nullable();
            $t->text('catatan')->nullable();
            $t->timestamp('difinalisasi_pada')->nullable();
            $t->foreignId('pengubah_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penilaian_supervisi');
        Schema::dropIfExists('lampiran_supervisi');
        Schema::dropIfExists('praobservasi_supervisi');
    }
};
