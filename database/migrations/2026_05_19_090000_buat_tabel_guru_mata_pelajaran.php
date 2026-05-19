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
        Schema::create('guru_mata_pelajaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_pelajaran_id')
                ->constrained('tahun_pelajaran')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('kelas_id')
                ->constrained('kelas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('mata_pelajaran_id')
                ->constrained('mata_pelajaran')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('pegawai_id')
                ->constrained('pegawai')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('jenis_penugasan', 50)->default('pengampu');
            $table->boolean('aktif')->default(true);
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique([
                'tahun_pelajaran_id',
                'kelas_id',
                'mata_pelajaran_id',
                'pegawai_id',
            ], 'guru_mapel_unik');
            $table->index(['tahun_pelajaran_id', 'kelas_id']);
            $table->index(['mata_pelajaran_id', 'pegawai_id']);
            $table->index(['aktif', 'jenis_penugasan']);
        });
    }

    /**
     * Membatalkan migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('guru_mata_pelajaran');
    }
};
