<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jawaban_peserta_ujian_cbt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peserta_ujian_cbt_id')
                ->constrained('peserta_ujian_cbt')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('soal_ujian_cbt_id')
                ->constrained('soal_ujian_cbt')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('soal_cbt_id')
                ->constrained('soal_cbt')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->json('jawaban')->nullable();
            $table->boolean('ragu')->default(false);
            $table->decimal('skor', 6, 2)->nullable();
            $table->boolean('benar')->nullable();
            $table->dateTime('waktu_dijawab')->nullable();
            $table->timestamps();

            $table->unique(['peserta_ujian_cbt_id', 'soal_ujian_cbt_id'], 'jawaban_peserta_cbt_unik');
            $table->index(['peserta_ujian_cbt_id', 'ragu'], 'jawaban_peserta_cbt_ragu_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jawaban_peserta_ujian_cbt');
    }
};
