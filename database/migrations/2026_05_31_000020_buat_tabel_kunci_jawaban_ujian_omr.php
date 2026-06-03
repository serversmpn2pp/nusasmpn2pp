<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kunci_jawaban_ujian_omr', function (Blueprint $table) {
            $table->id();
            $table->foreignId('versi_soal_ujian_omr_id')
                ->constrained('versi_soal_ujian_omr')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->unsignedSmallInteger('nomor_soal');
            $table->string('jawaban', 1);
            $table->timestamps();

            $table->unique(['versi_soal_ujian_omr_id', 'nomor_soal'], 'kunci_jawaban_ujian_omr_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kunci_jawaban_ujian_omr');
    }
};
