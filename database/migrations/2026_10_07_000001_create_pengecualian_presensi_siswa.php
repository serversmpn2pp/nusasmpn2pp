<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengecualian_presensi_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_pelajaran_id')->constrained('tahun_pelajaran')->restrictOnDelete();
            $table->foreignId('kelas_id')->nullable()->constrained('kelas')->restrictOnDelete();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->string('jenis', 30);
            $table->string('alasan', 1000);
            $table->boolean('aktif')->default(true);
            $table->uuid('kunci_pratinjau')->unique();
            $table->json('dampak_pratinjau');
            $table->foreignId('dibuat_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->foreignId('dibatalkan_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamp('dibatalkan_pada')->nullable();
            $table->string('alasan_pembatalan', 1000)->nullable();
            $table->timestamps();
            $table->index(['tahun_pelajaran_id', 'aktif', 'tanggal_mulai', 'tanggal_selesai'], 'pengecualian_presensi_periode_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengecualian_presensi_siswa');
    }
};
