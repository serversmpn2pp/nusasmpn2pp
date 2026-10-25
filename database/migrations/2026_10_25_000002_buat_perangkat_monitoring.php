<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perangkat_monitoring', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100);
            $table->char('token_hash', 64)->unique();
            $table->foreignId('diaktifkan_oleh')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamp('terakhir_digunakan_pada')->nullable();
            $table->timestamp('dicabut_pada')->nullable()->index();
            $table->foreignId('dicabut_oleh')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->string('alasan_pencabutan', 500)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perangkat_monitoring');
    }
};
