<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lembar_jawab_ujian_omr', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ujian_omr_id')
                ->constrained('ujian_omr')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('kelas_ujian_omr_id')
                ->constrained('kelas_ujian_omr')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('anggota_kelas_id')
                ->constrained('anggota_kelas')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('versi_soal_ujian_omr_id')
                ->constrained('versi_soal_ujian_omr')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->string('token', 24)->unique();
            $table->string('status', 30)->default('siap_dicetak')->index();
            $table->foreignId('dibuat_oleh_pengguna_id')
                ->nullable()
                ->constrained('pengguna')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();

            $table->unique(['ujian_omr_id', 'anggota_kelas_id'], 'lembar_jawab_ujian_anggota_unik');
            $table->index(['kelas_ujian_omr_id', 'versi_soal_ujian_omr_id'], 'lembar_jawab_ujian_kelas_versi_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lembar_jawab_ujian_omr');
    }
};
