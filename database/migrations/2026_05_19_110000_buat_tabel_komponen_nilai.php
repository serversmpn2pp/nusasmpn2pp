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
        Schema::create('komponen_nilai', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_mata_pelajaran_id')
                ->constrained('guru_mata_pelajaran')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->string('semester', 20);
            $table->string('jenis_komponen', 30);
            $table->string('nama');
            $table->date('tanggal_penilaian')->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique([
                'guru_mata_pelajaran_id',
                'semester',
                'jenis_komponen',
                'nama',
            ], 'komponen_nilai_nama_unik');
            $table->index(['semester', 'jenis_komponen']);
            $table->index(['aktif', 'urutan']);
        });
    }

    /**
     * Membatalkan migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('komponen_nilai');
    }
};
