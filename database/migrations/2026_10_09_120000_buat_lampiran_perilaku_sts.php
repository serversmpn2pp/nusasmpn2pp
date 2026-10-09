<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lampiran_perilaku_sts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rapor_sts_kelas_id')->constrained('rapor_sts_kelas')->restrictOnDelete();
            $table->foreignId('anggota_kelas_id')->constrained('anggota_kelas')->restrictOnDelete();
            $table->foreignId('guru_bk_id')->constrained('pegawai')->restrictOnDelete();
            $table->foreignId('wakil_kesiswaan_id')->constrained('pegawai')->restrictOnDelete();
            $table->json('baris');
            $table->json('ringkasan');
            $table->text('catatan')->nullable();
            $table->string('sidik_sumber', 64);
            $table->unsignedInteger('versi')->default(1);
            $table->timestamp('diperiksa_pada');
            $table->foreignId('diperiksa_oleh_pengguna_id')->constrained('pengguna')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['rapor_sts_kelas_id', 'anggota_kelas_id'], 'lampiran_perilaku_sts_siswa_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lampiran_perilaku_sts');
    }
};
