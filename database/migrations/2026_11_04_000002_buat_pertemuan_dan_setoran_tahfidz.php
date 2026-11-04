<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pertemuan_tahfidz', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelompok_tahfidz_id')->constrained('kelompok_tahfidz')->restrictOnDelete();
            $table->date('tanggal');
            $table->timestamps();
            $table->unique(['kelompok_tahfidz_id', 'tanggal']);
        });

        Schema::create('setoran_tahfidz', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pertemuan_tahfidz_id')->constrained('pertemuan_tahfidz')->restrictOnDelete();
            $table->foreignId('anggota_tahfidz_id')->constrained('anggota_tahfidz')->restrictOnDelete();
            $table->unsignedSmallInteger('nomor_surah');
            $table->foreign('nomor_surah')->references('nomor')->on('surah_tahfidz')->restrictOnDelete();
            $table->foreignId('penilai_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->decimal('nilai', 5, 2);
            $table->string('catatan', 500)->nullable();
            $table->uuid('token_setoran');
            $table->timestamps();
            $table->unique(['pertemuan_tahfidz_id', 'token_setoran']);
            $table->index('anggota_tahfidz_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('setoran_tahfidz');
        Schema::dropIfExists('pertemuan_tahfidz');
    }
};
