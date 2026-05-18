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
        Schema::create('mata_pelajaran', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 30)->nullable()->unique();
            $table->string('nama')->unique();
            $table->string('kelompok', 50)->nullable();
            $table->unsignedSmallInteger('tingkat')->nullable();
            $table->unsignedSmallInteger('kkm')->nullable();
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->boolean('aktif')->default(true);
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index(['aktif', 'tingkat']);
            $table->index(['kelompok', 'urutan']);
        });
    }

    /**
     * Membatalkan migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('mata_pelajaran');
    }
};
