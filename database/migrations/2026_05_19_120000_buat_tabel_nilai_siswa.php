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
        Schema::create('nilai_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('komponen_nilai_id')
                ->constrained('komponen_nilai')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('siswa_id')
                ->constrained('siswa')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->decimal('nilai', 5, 2)->nullable();
            $table->string('catatan')->nullable();
            $table->timestamps();

            $table->unique(['komponen_nilai_id', 'siswa_id'], 'nilai_siswa_unik');
            $table->index('siswa_id');
        });
    }

    /**
     * Membatalkan migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('nilai_siswa');
    }
};
