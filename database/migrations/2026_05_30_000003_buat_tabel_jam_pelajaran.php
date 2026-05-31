<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jam_pelajaran', function (Blueprint $table) {
            $table->id();
            $table->string('hari', 20)->index();
            $table->unsignedTinyInteger('nomor_jam');
            $table->string('label')->nullable();
            $table->time('jam_mulai');
            $table->time('jam_selesai');
            $table->string('jenis', 30)->default('pelajaran');
            $table->boolean('aktif')->default(true);
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['hari', 'nomor_jam']);
            $table->index(['hari', 'aktif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jam_pelajaran');
    }
};
