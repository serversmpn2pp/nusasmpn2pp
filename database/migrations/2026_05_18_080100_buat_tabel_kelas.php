<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menjalankan migration.
     */
    public function up(): void
    {
        Schema::create('kelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_pelajaran_id')
                ->constrained('tahun_pelajaran')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('wali_kelas_id')
                ->nullable()
                ->constrained('pegawai')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->string('nama', 50);
            $table->unsignedTinyInteger('tingkat')->nullable();
            $table->unsignedSmallInteger('kapasitas')->nullable();
            $table->boolean('aktif')->default(true)->index();
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['tahun_pelajaran_id', 'nama']);
            $table->index(['tahun_pelajaran_id', 'tingkat']);
        });
    }

    /**
     * Membatalkan migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('kelas');
    }
};
