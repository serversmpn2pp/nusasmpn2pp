<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengecualian_rapor_sts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rapor_sts_kelas_id')->constrained('rapor_sts_kelas')->cascadeOnDelete();
            $table->foreignId('anggota_kelas_id')->constrained('anggota_kelas')->cascadeOnDelete();
            $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajaran')->cascadeOnDelete();
            $table->foreignId('peserta_ujian_cbt_id')->constrained('peserta_ujian_cbt')->cascadeOnDelete();
            $table->boolean('aktif')->default(true);
            $table->string('alasan', 500);
            $table->string('sidik_kondisi', 64);
            $table->timestamp('ditetapkan_pada');
            $table->foreignId('ditetapkan_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamp('dibatalkan_pada')->nullable();
            $table->foreignId('dibatalkan_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamps();
            $table->unique(['rapor_sts_kelas_id', 'anggota_kelas_id', 'mata_pelajaran_id'], 'pengecualian_sts_siswa_mapel_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengecualian_rapor_sts');
    }
};
