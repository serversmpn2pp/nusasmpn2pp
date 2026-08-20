<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan_berhalangan_ibadah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_pelajaran_id')->unique()->constrained('tahun_pelajaran')->cascadeOnDelete();
            $table->unsignedTinyInteger('batas_hari_konfirmasi')->default(7);
            $table->boolean('aktif')->default(true);
            $table->foreignId('diperbarui_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('penugasan_pendamping_ibadah_siswi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_pelajaran_id')->constrained('tahun_pelajaran')->cascadeOnDelete();
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->boolean('semua_kelas')->default(false);
            $table->boolean('aktif')->default(true);
            $table->foreignId('ditugaskan_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamp('dinonaktifkan_pada')->nullable();
            $table->timestamps();

            $table->unique(['tahun_pelajaran_id', 'pegawai_id'], 'pendamping_ibadah_tahun_pegawai_unik');
            $table->index(['tahun_pelajaran_id', 'aktif'], 'pendamping_ibadah_tahun_aktif_idx');
        });

        Schema::create('kelas_pendamping_ibadah_siswi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('penugasan_pendamping_ibadah_siswi_id')
                ->constrained('penugasan_pendamping_ibadah_siswi')
                ->cascadeOnDelete();
            $table->foreignId('kelas_id')->constrained('kelas')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(
                ['penugasan_pendamping_ibadah_siswi_id', 'kelas_id'],
                'kelas_pendamping_ibadah_unik'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas_pendamping_ibadah_siswi');
        Schema::dropIfExists('penugasan_pendamping_ibadah_siswi');
        Schema::dropIfExists('pengaturan_berhalangan_ibadah');
    }
};
