<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ujian_omr', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_pelajaran_id')
                ->constrained('tahun_pelajaran')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('mata_pelajaran_id')
                ->constrained('mata_pelajaran')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->string('kode', 50)->unique();
            $table->string('nama', 180);
            $table->string('semester', 20);
            $table->date('tanggal_ujian')->nullable();
            $table->unsignedSmallInteger('jumlah_soal')->default(50);
            $table->unsignedTinyInteger('jumlah_pilihan')->default(4);
            $table->string('status', 30)->default('draft')->index();
            $table->text('keterangan')->nullable();
            $table->foreignId('dibuat_oleh_pengguna_id')
                ->nullable()
                ->constrained('pengguna')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();

            $table->index(['tahun_pelajaran_id', 'semester']);
            $table->index(['mata_pelajaran_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ujian_omr');
    }
};
