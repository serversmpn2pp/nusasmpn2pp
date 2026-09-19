<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('folder_soal_cbt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mata_pelajaran_id')->constrained('mata_pelajaran')->cascadeOnDelete();
            $table->unsignedTinyInteger('tingkat');
            $table->string('nama', 120);
            $table->string('keterangan', 500)->nullable();
            $table->timestamps();
            $table->unique(['mata_pelajaran_id', 'tingkat', 'nama'], 'folder_soal_cbt_nama_unik');
        });
        Schema::create('anggota_folder_soal_cbt', function (Blueprint $table) {
            $table->foreignId('folder_soal_cbt_id')->constrained('folder_soal_cbt')->cascadeOnDelete();
            $table->foreignId('soal_cbt_id')->constrained('soal_cbt')->cascadeOnDelete();
            $table->primary(['folder_soal_cbt_id', 'soal_cbt_id']);
            $table->index('soal_cbt_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anggota_folder_soal_cbt');
        Schema::dropIfExists('folder_soal_cbt');
    }
};
