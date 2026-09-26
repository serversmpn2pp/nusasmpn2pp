<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rapor_sts_kelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_ujian_cbt_id')->constrained('kegiatan_ujian_cbt')->cascadeOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->date('tanggal_awal_presensi');
            $table->date('tanggal_akhir_presensi');
            $table->date('tanggal_rapor');
            $table->unsignedInteger('versi')->default(1);
            $table->foreignId('diubah_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamps();
            $table->unique(['kegiatan_ujian_cbt_id', 'kelas_id'], 'rapor_sts_kegiatan_kelas_unik');
        });

        Schema::create('kehadiran_rapor_sts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rapor_sts_kelas_id')->constrained('rapor_sts_kelas')->cascadeOnDelete();
            $table->foreignId('anggota_kelas_id')->constrained('anggota_kelas')->cascadeOnDelete();
            $table->unsignedSmallInteger('sakit');
            $table->unsignedSmallInteger('izin');
            $table->unsignedSmallInteger('alfa');
            $table->json('rekap_sumber');
            $table->string('catatan_koreksi', 500)->nullable();
            $table->timestamp('diperiksa_pada')->nullable();
            $table->foreignId('diperiksa_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamps();
            $table->unique(['rapor_sts_kelas_id', 'anggota_kelas_id'], 'kehadiran_rapor_sts_siswa_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kehadiran_rapor_sts');
        Schema::dropIfExists('rapor_sts_kelas');
    }
};
