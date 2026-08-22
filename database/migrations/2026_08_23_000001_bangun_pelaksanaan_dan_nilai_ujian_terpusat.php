<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sesi_ujian_cbt', function (Blueprint $table) {
            $table->foreignId('sesi_kegiatan_ujian_cbt_id')
                ->nullable()
                ->after('ujian_cbt_id')
                ->constrained('sesi_kegiatan_ujian_cbt')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->unique(
                ['ujian_cbt_id', 'sesi_kegiatan_ujian_cbt_id'],
                'sesi_ujian_cbt_sumber_terpusat_unik'
            );
        });

        Schema::table('ruang_ujian_cbt', function (Blueprint $table) {
            $table->foreignId('ruang_kegiatan_ujian_cbt_id')
                ->nullable()
                ->after('jadwal_ujian_cbt_id')
                ->constrained('ruang_kegiatan_ujian_cbt')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->unique(
                ['ujian_cbt_id', 'ruang_kegiatan_ujian_cbt_id'],
                'ruang_ujian_cbt_sumber_terpusat_unik'
            );
        });

        Schema::create('pengawas_ruang_ujian_terpusat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jadwal_ujian_cbt_id')
                ->constrained('jadwal_ujian_cbt')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('ruang_kegiatan_ujian_cbt_id')
                ->constrained('ruang_kegiatan_ujian_cbt')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('pengawas_utama_pegawai_id')
                ->nullable()
                ->constrained('pegawai')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('pengawas_pendamping_pegawai_id')
                ->nullable()
                ->constrained('pegawai')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->text('catatan')->nullable();
            $table->foreignId('ditugaskan_oleh_pengguna_id')
                ->nullable()
                ->constrained('pengguna')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();

            $table->unique(
                ['jadwal_ujian_cbt_id', 'ruang_kegiatan_ujian_cbt_id'],
                'pengawas_ruang_ujian_terpusat_unik'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pengawas_ruang_ujian_terpusat');

        Schema::table('ruang_ujian_cbt', function (Blueprint $table) {
            $table->dropUnique('ruang_ujian_cbt_sumber_terpusat_unik');
            $table->dropForeign(['ruang_kegiatan_ujian_cbt_id']);
            $table->dropColumn('ruang_kegiatan_ujian_cbt_id');
        });

        Schema::table('sesi_ujian_cbt', function (Blueprint $table) {
            $table->dropUnique('sesi_ujian_cbt_sumber_terpusat_unik');
            $table->dropForeign(['sesi_kegiatan_ujian_cbt_id']);
            $table->dropColumn('sesi_kegiatan_ujian_cbt_id');
        });
    }
};
