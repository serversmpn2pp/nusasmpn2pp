<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_pembukaan_susulan_cbt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peserta_ujian_cbt_id')->constrained('peserta_ujian_cbt')->cascadeOnDelete();
            $table->foreignId('nilai_siswa_id')->nullable()->constrained('nilai_siswa')->nullOnDelete();
            $table->json('nilai_sebelumnya');
            $table->json('keadaan_peserta_sebelumnya');
            $table->text('alasan');
            $table->foreignId('dibuka_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_pembukaan_susulan_cbt');
    }
};
