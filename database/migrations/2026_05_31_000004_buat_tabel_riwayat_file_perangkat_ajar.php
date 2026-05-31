<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_file_perangkat_ajar', function (Blueprint $table) {
            $table->id();
            $table->foreignId('perangkat_ajar_id')->constrained('perangkat_ajar')->cascadeOnDelete();
            $table->foreignId('diunggah_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->string('lokasi_file');
            $table->string('nama_file_asli');
            $table->string('tipe_file', 100);
            $table->unsignedBigInteger('ukuran_file');
            $table->text('catatan')->nullable();
            $table->timestamp('diunggah_pada');
            $table->timestamps();

            $table->index(['perangkat_ajar_id', 'diunggah_pada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_file_perangkat_ajar');
    }
};
