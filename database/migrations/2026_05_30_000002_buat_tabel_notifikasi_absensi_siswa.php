<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifikasi_absensi_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('absensi_siswa_id')->nullable()->constrained('absensi_siswa')->nullOnDelete();
            $table->foreignId('log_scan_absensi_id')->nullable()->constrained('log_scan_absensi')->nullOnDelete();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->date('tanggal')->index();
            $table->string('jenis_absensi', 30)->default('masuk');
            $table->string('jenis_pesan', 60);
            $table->string('kanal', 30)->default('whatsapp');
            $table->string('mode_pengiriman', 30)->default('simulasi');
            $table->string('nomor_tujuan', 40)->nullable();
            $table->string('nama_penerima')->nullable();
            $table->string('status', 40)->default('menunggu')->index();
            $table->text('pesan');
            $table->json('payload')->nullable();
            $table->json('respons')->nullable();
            $table->text('pesan_error')->nullable();
            $table->timestamp('dijadwalkan_pada')->nullable();
            $table->timestamp('dikirim_pada')->nullable();
            $table->timestamp('gagal_pada')->nullable();
            $table->unsignedSmallInteger('jumlah_percobaan')->default(0);
            $table->timestamps();

            $table->unique(['absensi_siswa_id', 'jenis_absensi', 'kanal'], 'notifikasi_absensi_siswa_unik');
            $table->index(['status', 'kanal']);
            $table->index(['siswa_id', 'tanggal']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifikasi_absensi_siswa');
    }
};
