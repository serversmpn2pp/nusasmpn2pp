<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kelas_ujian_omr', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ujian_omr_id')
                ->constrained('ujian_omr')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('kelas_id')
                ->constrained('kelas')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('komponen_nilai_id')
                ->constrained('komponen_nilai')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();

            $table->unique(['ujian_omr_id', 'kelas_id'], 'kelas_ujian_omr_unik');
            $table->index('komponen_nilai_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas_ujian_omr');
    }
};
