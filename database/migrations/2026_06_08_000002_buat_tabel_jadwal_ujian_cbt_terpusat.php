<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kegiatan_ujian_cbt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_ujian_cbt_id')
                ->constrained('jenis_ujian_cbt')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('tahun_pelajaran_id')
                ->constrained('tahun_pelajaran')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->string('kode', 50)->unique();
            $table->string('nama', 180);
            $table->string('semester', 20);
            $table->date('tanggal_mulai')->nullable();
            $table->date('tanggal_selesai')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->text('keterangan')->nullable();
            $table->foreignId('dibuat_oleh_pengguna_id')
                ->nullable()
                ->constrained('pengguna')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();

            $table->index(['tahun_pelajaran_id', 'semester']);
            $table->index(['jenis_ujian_cbt_id', 'status']);
        });

        Schema::create('jadwal_ujian_cbt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_ujian_cbt_id')
                ->constrained('kegiatan_ujian_cbt')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('ujian_cbt_id')
                ->nullable()
                ->constrained('ujian_cbt')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('mata_pelajaran_id')
                ->nullable()
                ->constrained('mata_pelajaran')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->date('tanggal');
            $table->time('waktu_mulai');
            $table->time('waktu_selesai');
            $table->string('label_sesi', 80)->nullable();
            $table->unsignedTinyInteger('tingkat')->nullable();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->string('status', 30)->default('draft')->index();
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index(['kegiatan_ujian_cbt_id', 'tanggal', 'waktu_mulai']);
            $table->index(['ujian_cbt_id', 'status']);
        });

        Schema::create('jadwal_ujian_cbt_kelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jadwal_ujian_cbt_id')
                ->constrained('jadwal_ujian_cbt')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('kelas_id')
                ->constrained('kelas')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();

            $table->unique(['jadwal_ujian_cbt_id', 'kelas_id'], 'jadwal_ujian_cbt_kelas_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_ujian_cbt_kelas');
        Schema::dropIfExists('jadwal_ujian_cbt');
        Schema::dropIfExists('kegiatan_ujian_cbt');
    }
};
