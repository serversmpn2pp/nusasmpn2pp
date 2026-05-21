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
        Schema::create('absensi_pegawai', function (Blueprint $table) {
            $table->id();
            $table->date('tanggal');
            $table->foreignId('pegawai_id')
                ->constrained('pegawai')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('pengaturan_absensi_pegawai_id')
                ->nullable()
                ->constrained('pengaturan_absensi_pegawai')
                ->cascadeOnUpdate()
                ->nullOnDelete();
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

            $table->unique(['tanggal', 'pegawai_id'], 'absensi_pegawai_tanggal_unik');
            $table->index(['tanggal', 'status_kehadiran']);
            $table->index(['pegawai_id', 'tanggal']);
            $table->index(['pengaturan_absensi_pegawai_id', 'tanggal'], 'absensi_pegawai_pengaturan_tanggal_idx');
        });
    }

    /**
     * Membatalkan migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('absensi_pegawai');
    }
};
