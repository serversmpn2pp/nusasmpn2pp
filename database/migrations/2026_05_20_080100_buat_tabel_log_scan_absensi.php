<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menjalankan migration.
     */
    public function up(): void
    {
        Schema::create('log_scan_absensi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('absensi_siswa_id')
                ->nullable()
                ->constrained('absensi_siswa')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreignId('siswa_id')
                ->nullable()
                ->constrained('siswa')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->string('isi_scan');
            $table->string('nisn')->nullable();
            $table->string('scanner_id', 30)->nullable();
            $table->string('jenis_scan', 20)->nullable();
            $table->timestamp('waktu_scan');
            $table->date('tanggal');
            $table->boolean('berhasil')->default(false);
            $table->string('status_scan', 50);
            $table->text('pesan')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['tanggal', 'berhasil']);
            $table->index(['nisn', 'waktu_scan']);
            $table->index(['scanner_id', 'waktu_scan']);
        });
    }

    /**
     * Membatalkan migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('log_scan_absensi');
    }
};
