<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publikasi_rapor_sts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rapor_sts_kelas_id')->constrained('rapor_sts_kelas')->restrictOnDelete();
            $table->foreignId('anggota_kelas_id')->constrained('anggota_kelas')->restrictOnDelete();
            $table->foreignId('siswa_id')->constrained('siswa')->restrictOnDelete();
            $table->unsignedInteger('versi');
            $table->string('penerima', 30);
            $table->boolean('sertakan_perilaku')->default(false);
            $table->boolean('aktif')->default(true);
            $table->json('salinan');
            $table->string('sidik_sumber', 64);
            $table->timestamp('dipublikasikan_pada');
            $table->foreignId('dipublikasikan_oleh_pengguna_id')->constrained('pengguna')->restrictOnDelete();
            $table->timestamp('dibatalkan_pada')->nullable();
            $table->foreignId('dibatalkan_oleh_pengguna_id')->nullable()->constrained('pengguna')->restrictOnDelete();
            $table->string('alasan_pembatalan', 500)->nullable();
            $table->timestamps();
            $table->unique(['rapor_sts_kelas_id', 'anggota_kelas_id', 'versi'], 'publikasi_sts_versi_unik');
            $table->index(['siswa_id', 'aktif']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publikasi_rapor_sts');
    }
};
