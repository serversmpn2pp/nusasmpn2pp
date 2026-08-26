<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_pergantian_pengawas_ujian', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengawas_ruang_ujian_terpusat_id')
                ->nullable()
                ->constrained('pengawas_ruang_ujian_terpusat')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->foreignId('jadwal_ujian_cbt_id')
                ->constrained('jadwal_ujian_cbt')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('ruang_kegiatan_ujian_cbt_id')
                ->constrained('ruang_kegiatan_ujian_cbt')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('peran_pengawas', 20);
            $table->foreignId('pegawai_lama_id')
                ->constrained('pegawai')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('pegawai_baru_id')
                ->constrained('pegawai')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->text('alasan');
            $table->foreignId('diganti_oleh_pengguna_id')
                ->nullable()
                ->constrained('pengguna')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->timestamp('diganti_pada');
            $table->timestamps();

            $table->index(
                ['jadwal_ujian_cbt_id', 'ruang_kegiatan_ujian_cbt_id', 'diganti_pada'],
                'riwayat_ganti_pengawas_ruang_waktu_idx',
            );
            $table->index(
                ['pegawai_lama_id', 'pegawai_baru_id'],
                'riwayat_ganti_pengawas_pegawai_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_pergantian_pengawas_ujian');
    }
};
