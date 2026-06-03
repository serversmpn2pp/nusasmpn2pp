<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('versi_soal_ujian_omr', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ujian_omr_id')
                ->constrained('ujian_omr')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('kode', 10);
            $table->boolean('aktif')->default(true)->index();
            $table->timestamps();

            $table->unique(['ujian_omr_id', 'kode'], 'versi_soal_ujian_omr_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('versi_soal_ujian_omr');
    }
};
