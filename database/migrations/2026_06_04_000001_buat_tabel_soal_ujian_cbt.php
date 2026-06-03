<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('soal_ujian_cbt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ujian_cbt_id')
                ->constrained('ujian_cbt')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('soal_cbt_id')
                ->constrained('soal_cbt')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->unsignedSmallInteger('nomor_urut')->nullable();
            $table->decimal('bobot', 6, 2)->default(1);
            $table->timestamps();

            $table->unique(['ujian_cbt_id', 'soal_cbt_id'], 'soal_ujian_cbt_unik');
            $table->index(['ujian_cbt_id', 'nomor_urut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('soal_ujian_cbt');
    }
};
