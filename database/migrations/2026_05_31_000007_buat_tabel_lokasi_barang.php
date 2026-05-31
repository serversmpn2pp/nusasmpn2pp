<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lokasi_barang', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 40)->unique();
            $table->string('nama', 120)->unique();
            $table->string('jenis', 30)->index();
            $table->foreignId('penanggung_jawab_pegawai_id')
                ->nullable()
                ->constrained('pegawai')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->text('deskripsi')->nullable();
            $table->boolean('aktif')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lokasi_barang');
    }
};
