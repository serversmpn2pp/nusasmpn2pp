<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ujian_cbt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_ujian_cbt_id')
                ->constrained('jenis_ujian_cbt')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('tahun_pelajaran_id')
                ->constrained('tahun_pelajaran')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('mata_pelajaran_id')
                ->constrained('mata_pelajaran')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->string('kode', 50)->unique();
            $table->string('nama', 180);
            $table->string('semester', 20);
            $table->unsignedTinyInteger('tingkat');
            $table->dateTime('tanggal_mulai')->nullable();
            $table->dateTime('tanggal_selesai')->nullable();
            $table->unsignedSmallInteger('durasi_menit')->default(120);
            $table->unsignedSmallInteger('jumlah_soal')->default(50);
            $table->unsignedTinyInteger('kkm')->nullable();
            $table->string('token', 20)->nullable();
            $table->boolean('acak_soal')->default(true);
            $table->boolean('acak_jawaban')->default(true);
            $table->boolean('batasi_satu_perangkat')->default(false);
            $table->boolean('deteksi_pindah_tab')->default(false);
            $table->boolean('wajib_fullscreen')->default(false);
            $table->boolean('tampilkan_hasil')->default(false);
            $table->string('status', 30)->default('draft')->index();
            $table->text('petunjuk')->nullable();
            $table->text('keterangan')->nullable();
            $table->foreignId('dibuat_oleh_pengguna_id')
                ->nullable()
                ->constrained('pengguna')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();

            $table->index(['tahun_pelajaran_id', 'semester']);
            $table->index(['mata_pelajaran_id', 'tingkat']);
            $table->index(['jenis_ujian_cbt_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ujian_cbt');
    }
};
