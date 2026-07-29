<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_pergantian_guru_mapel', function (Blueprint $table) {
            $table->id();
            $table->foreignId('guru_mata_pelajaran_id')
                ->constrained('guru_mata_pelajaran')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('tahun_pelajaran_id')
                ->constrained('tahun_pelajaran')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('kelas_id')
                ->constrained('kelas')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('mata_pelajaran_id')
                ->constrained('mata_pelajaran')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('pegawai_lama_id')
                ->constrained('pegawai')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->foreignId('pegawai_baru_id')
                ->constrained('pegawai')
                ->cascadeOnUpdate()
                ->restrictOnDelete();
            $table->date('tanggal_efektif');
            $table->text('alasan');
            $table->foreignId('diganti_oleh_pengguna_id')
                ->nullable()
                ->constrained('pengguna')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['guru_mata_pelajaran_id', 'tanggal_efektif'],
                'riwayat_guru_mapel_penugasan_tanggal_idx',
            );
            $table->index(
                ['pegawai_lama_id', 'pegawai_baru_id'],
                'riwayat_guru_mapel_pegawai_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_pergantian_guru_mapel');
    }
};
