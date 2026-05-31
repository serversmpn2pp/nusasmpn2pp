<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_pelajaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_pelajaran_id')->constrained('tahun_pelajaran')->cascadeOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->string('hari', 20)->index();
            $table->foreignId('jam_pelajaran_id')->constrained('jam_pelajaran')->cascadeOnDelete();
            $table->foreignId('guru_mata_pelajaran_id')->constrained('guru_mata_pelajaran')->cascadeOnDelete();
            $table->boolean('aktif')->default(true);
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['tahun_pelajaran_id', 'kelas_id', 'hari', 'jam_pelajaran_id'], 'jadwal_pelajaran_unik_kelas_jam');
            $table->index(['tahun_pelajaran_id', 'kelas_id', 'hari']);
            $table->index(['guru_mata_pelajaran_id', 'hari']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jadwal_pelajaran');
    }
};
