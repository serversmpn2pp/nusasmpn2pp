<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sesi_ujian_cbt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ujian_cbt_id')
                ->constrained('ujian_cbt')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('kode', 50);
            $table->string('nama', 120);
            $table->dateTime('waktu_mulai')->nullable();
            $table->dateTime('waktu_selesai')->nullable();
            $table->unsignedSmallInteger('kapasitas')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['ujian_cbt_id', 'kode'], 'sesi_ujian_cbt_kode_unik');
            $table->index(['ujian_cbt_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sesi_ujian_cbt');
    }
};
