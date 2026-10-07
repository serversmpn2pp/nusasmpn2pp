<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('peserta_ujian_cbt', function (Blueprint $table) {
            $table->string('sesi_ujian_hash', 64)->nullable();
            $table->string('sesi_ujian_saluran', 10)->nullable();
            $table->timestamp('sesi_ujian_mulai_pada')->nullable();
        });
        Schema::create('penerimaan_keamanan_ujian_cbt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peserta_ujian_cbt_id')->constrained('peserta_ujian_cbt')->cascadeOnDelete();
            $table->uuid('kejadian_id');
            $table->timestamp('diterima_pada');
            $table->unique(['peserta_ujian_cbt_id', 'kejadian_id'], 'penerimaan_keamanan_peserta_kejadian_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penerimaan_keamanan_ujian_cbt');
        Schema::table('peserta_ujian_cbt', fn (Blueprint $table) => $table->dropColumn([
            'sesi_ujian_hash', 'sesi_ujian_saluran', 'sesi_ujian_mulai_pada',
        ]));
    }
};
