<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perangkat_ajar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->foreignId('tahun_pelajaran_id')->constrained('tahun_pelajaran')->cascadeOnDelete();
            $table->unsignedTinyInteger('semester');
            $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajaran')->cascadeOnDelete();
            $table->foreignId('jenis_perangkat_ajar_id')->constrained('jenis_perangkat_ajar')->cascadeOnDelete();
            $table->string('judul', 180);
            $table->text('catatan_guru')->nullable();
            $table->string('lokasi_file');
            $table->string('nama_file_asli');
            $table->string('tipe_file', 100);
            $table->unsignedBigInteger('ukuran_file');
            $table->string('status', 30)->default('menunggu_pemeriksaan');
            $table->foreignId('pemeriksa_pegawai_id')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->text('catatan_pemeriksa')->nullable();
            $table->timestamp('diunggah_pada');
            $table->timestamp('diperiksa_pada')->nullable();
            $table->timestamps();

            $table->unique(
                ['pegawai_id', 'tahun_pelajaran_id', 'semester', 'mata_pelajaran_id', 'jenis_perangkat_ajar_id'],
                'perangkat_ajar_unik_guru_periode_mapel_jenis'
            );
            $table->index(['tahun_pelajaran_id', 'semester', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perangkat_ajar');
    }
};
