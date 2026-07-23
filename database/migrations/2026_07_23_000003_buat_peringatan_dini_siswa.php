<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan_peringatan_dini_poin', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_pelajaran_id')->unique()->constrained('tahun_pelajaran')->cascadeOnUpdate()->cascadeOnDelete();
            $table->boolean('aktif')->default(true);
            $table->unsignedTinyInteger('persentase_mendekati_ambang')->default(80);
            $table->unsignedSmallInteger('jumlah_pelanggaran_berulang')->default(3);
            $table->unsignedSmallInteger('periode_pelanggaran_hari')->default(30);
            $table->unsignedSmallInteger('jumlah_keterlambatan_berulang')->default(3);
            $table->unsignedSmallInteger('periode_keterlambatan_hari')->default(30);
            $table->boolean('notifikasi_aktif')->default(true);
            $table->foreignId('diperbarui_oleh_pengguna_id')->nullable()->constrained('pengguna')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('peringatan_dini_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('tahun_pelajaran_id')->constrained('tahun_pelajaran')->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('sanksi_poin_siswa_id')->nullable()->constrained('sanksi_poin_siswa')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('jenis', 50);
            $table->string('tingkat', 20)->default('peringatan');
            $table->string('status', 20)->default('aktif');
            $table->string('kunci_unik', 190)->unique();
            $table->string('judul', 160);
            $table->text('pesan');
            $table->json('data_pendukung')->nullable();
            $table->unsignedSmallInteger('siklus')->default(1);
            $table->timestamp('terdeteksi_pada');
            $table->timestamp('terakhir_terdeteksi_pada');
            $table->timestamp('diselesaikan_pada')->nullable();
            $table->timestamps();

            $table->index(['tahun_pelajaran_id', 'status', 'tingkat'], 'peringatan_dini_tahun_status');
            $table->index(['siswa_id', 'tahun_pelajaran_id', 'status'], 'peringatan_dini_siswa_tahun');
            $table->index(['jenis', 'status'], 'peringatan_dini_jenis_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peringatan_dini_siswa');
        Schema::dropIfExists('pengaturan_peringatan_dini_poin');
    }
};
