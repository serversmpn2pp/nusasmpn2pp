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
        Schema::create('tindak_lanjut_pembinaan_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_pembinaan_siswa_id')->constrained('laporan_pembinaan_siswa')->cascadeOnUpdate()->cascadeOnDelete();
            $table->date('tanggal_tindak_lanjut');
            $table->time('waktu_tindak_lanjut')->nullable();
            $table->string('jenis_tindak_lanjut', 60);
            $table->foreignId('petugas_pegawai_id')->nullable()->constrained('pegawai')->cascadeOnUpdate()->nullOnDelete();
            $table->string('pihak_terlibat', 180)->nullable();
            $table->text('ringkasan');
            $table->text('hasil')->nullable();
            $table->text('rencana_lanjutan')->nullable();
            $table->string('status_laporan', 40)->default('diproses');
            $table->text('catatan_rahasia')->nullable();
            $table->foreignId('dibuat_oleh_pengguna_id')->nullable()->constrained('pengguna')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();

            $table->index(['laporan_pembinaan_siswa_id', 'tanggal_tindak_lanjut']);
            $table->index(['jenis_tindak_lanjut', 'tanggal_tindak_lanjut']);
            $table->index(['petugas_pegawai_id', 'tanggal_tindak_lanjut']);
            $table->index(['status_laporan', 'tanggal_tindak_lanjut']);
        });
    }

    /**
     * Membatalkan migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('tindak_lanjut_pembinaan_siswa');
    }
};
