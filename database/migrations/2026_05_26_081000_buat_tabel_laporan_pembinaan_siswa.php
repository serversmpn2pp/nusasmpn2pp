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
        Schema::create('laporan_pembinaan_siswa', function (Blueprint $table) {
            $table->id();
            $table->string('nomor_laporan', 50)->unique();
            $table->date('tanggal_kejadian');
            $table->time('waktu_kejadian')->nullable();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('kategori_pembinaan_siswa_id')->nullable()->constrained('kategori_pembinaan_siswa')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('tahun_pelajaran_id')->nullable()->constrained('tahun_pelajaran')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('kelas_id')->nullable()->constrained('kelas')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('anggota_kelas_id')->nullable()->constrained('anggota_kelas')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('pelapor_pegawai_id')->nullable()->constrained('pegawai')->cascadeOnUpdate()->nullOnDelete();
            $table->string('tingkat', 30)->default('ringan');
            $table->string('status', 40)->default('baru');
            $table->text('kronologi');
            $table->text('tindakan_awal')->nullable();
            $table->text('catatan_rahasia')->nullable();
            $table->foreignId('dibuat_oleh_pengguna_id')->nullable()->constrained('pengguna')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();

            $table->index(['tanggal_kejadian', 'status']);
            $table->index(['siswa_id', 'tanggal_kejadian']);
            $table->index(['kategori_pembinaan_siswa_id', 'status']);
            $table->index(['kelas_id', 'status']);
        });
    }

    /**
     * Membatalkan migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('laporan_pembinaan_siswa');
    }
};
