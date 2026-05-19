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
        Schema::create('absensi_siswa', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->foreignId('tahun_pelajaran_id')
                ->constrained('tahun_pelajaran')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('kelas_id')
                ->constrained('kelas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('anggota_kelas_id')
                ->nullable()
                ->constrained('anggota_kelas')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreignId('siswa_id')
                ->constrained('siswa')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->time('jam_masuk')->nullable();
            $table->string('status_masuk', 30)->nullable();
            $table->unsignedSmallInteger('menit_terlambat')->default(0);
            $table->time('jam_pulang')->nullable();
            $table->string('status_pulang', 30)->nullable();
            $table->unsignedSmallInteger('menit_pulang_cepat')->default(0);
            $table->string('status_kehadiran', 30)->default('hadir');
            $table->string('sumber', 30)->default('scan');
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['tanggal', 'siswa_id'], 'absensi_siswa_tanggal_unik');
            $table->index(['tanggal', 'kelas_id']);
            $table->index(['status_kehadiran', 'tanggal']);
        });
    }

    /**
     * Membatalkan migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('absensi_siswa');
    }
};
