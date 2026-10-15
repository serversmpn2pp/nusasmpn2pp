<?php

use Database\Seeders\SupervisiAwalSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('instrumen_supervisi', function (Blueprint $t) {
            $t->id();
            $t->uuid('token_pembuatan')->unique();
            $t->string('sidik_pembuatan', 64)->nullable();
            $t->foreignId('pembuat_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $t->string('nama', 180);
            $t->boolean('aktif')->default(true);
            $t->timestamps();
        });
        Schema::create('versi_instrumen_supervisi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('instrumen_supervisi_id')->constrained('instrumen_supervisi')->restrictOnDelete();
            $t->unsignedInteger('nomor');
            $t->string('nama', 180);
            $t->unsignedInteger('versi')->default(1);
            $t->string('status', 20)->default('draf');
            $t->text('catatan');
            $t->json('skala');
            $t->json('kelompok');
            $t->timestamp('diterbitkan_pada')->nullable();
            $t->foreignId('pengubah_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $t->timestamps();
            $t->unique(['instrumen_supervisi_id', 'nomor'], 'versi_instrumen_supervisi_unik');
        });
        Schema::create('program_supervisi', function (Blueprint $t) {
            $t->id();
            $t->uuid('token_pembuatan')->unique();
            $t->string('sidik_pembuatan', 64);
            $t->foreignId('pembuat_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $t->foreignId('tahun_pelajaran_id')->constrained('tahun_pelajaran')->restrictOnDelete();
            $t->string('semester', 10);
            $t->string('nama', 180);
            $t->text('tujuan');
            $t->date('tanggal_mulai');
            $t->date('tanggal_selesai');
            $t->string('status', 20)->default('draf');
            $t->unsignedInteger('versi')->default(1);
            $t->timestamps();
        });
        Schema::create('sesi_supervisi', function (Blueprint $t) {
            $t->id();
            $t->uuid('token_pembuatan')->unique();
            $t->string('sidik_pembuatan', 64);
            $t->foreignId('pembuat_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $t->foreignId('program_supervisi_id')->constrained('program_supervisi')->restrictOnDelete();
            $t->foreignId('versi_instrumen_supervisi_id')->constrained('versi_instrumen_supervisi')->restrictOnDelete();
            $t->foreignId('guru_mata_pelajaran_id')->constrained('guru_mata_pelajaran')->restrictOnDelete();
            $t->foreignId('guru_pegawai_id')->constrained('pegawai')->restrictOnDelete();
            $t->foreignId('supervisor_pegawai_id')->constrained('pegawai')->restrictOnDelete();
            $t->foreignId('kelas_id')->constrained('kelas')->restrictOnDelete();
            $t->timestamp('waktu_mulai');
            $t->timestamp('waktu_selesai');
            $t->string('tempat', 180);
            $t->string('topik', 500)->nullable();
            $t->text('alasan_di_luar_jadwal')->nullable();
            $t->json('konteks');
            $t->string('status', 20)->default('draf');
            $t->unsignedInteger('versi')->default(1);
            $t->timestamp('diterbitkan_pada')->nullable();
            $t->timestamps();
            $t->index(['guru_pegawai_id', 'waktu_mulai']);
            $t->index(['supervisor_pegawai_id', 'waktu_mulai']);
            $t->index(['kelas_id', 'waktu_mulai']);
        });
        Schema::create('riwayat_supervisi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('program_supervisi_id')->nullable()->constrained('program_supervisi')->restrictOnDelete();
            $t->foreignId('sesi_supervisi_id')->nullable()->constrained('sesi_supervisi')->restrictOnDelete();
            $t->foreignId('versi_instrumen_supervisi_id')->nullable()->constrained('versi_instrumen_supervisi')->restrictOnDelete();
            $t->foreignId('pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $t->string('aksi', 100);
            $t->text('alasan')->nullable();
            $t->json('salinan');
            $t->timestamp('created_at');
        });
        app(SupervisiAwalSeeder::class)->run();
    }

    public function down(): void
    {
        foreach (['riwayat_supervisi', 'sesi_supervisi', 'program_supervisi', 'versi_instrumen_supervisi', 'instrumen_supervisi'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
