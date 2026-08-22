<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_ujian_cbt', function (Blueprint $table) {
            $table->foreignId('sesi_kegiatan_ujian_cbt_id')
                ->nullable()
                ->after('kegiatan_ujian_cbt_id')
                ->constrained('sesi_kegiatan_ujian_cbt')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->unique(
                ['kegiatan_ujian_cbt_id', 'tanggal', 'sesi_kegiatan_ujian_cbt_id', 'tingkat'],
                'jadwal_kegiatan_tanggal_sesi_tingkat_unik'
            );
        });

        Schema::create('kelompok_peserta_kegiatan_ujian_cbt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_ujian_cbt_id')
                ->constrained('kegiatan_ujian_cbt')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('sesi_kegiatan_ujian_cbt_id')
                ->constrained('sesi_kegiatan_ujian_cbt')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->unsignedTinyInteger('tingkat');
            $table->unsignedSmallInteger('jumlah_peserta')->default(0);
            $table->unsignedSmallInteger('total_kapasitas')->default(0);
            $table->timestamp('dibangkitkan_pada')->nullable();
            $table->foreignId('dibangkitkan_oleh_pengguna_id')
                ->nullable()
                ->constrained('pengguna')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();

            $table->unique(['kegiatan_ujian_cbt_id', 'tingkat'], 'kelompok_peserta_kegiatan_tingkat_unik');
            $table->index(['sesi_kegiatan_ujian_cbt_id', 'tingkat'], 'kelompok_peserta_sesi_tingkat_index');
        });

        Schema::create('kelompok_peserta_kegiatan_ujian_cbt_kelas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelompok_peserta_kegiatan_ujian_cbt_id')
                ->constrained('kelompok_peserta_kegiatan_ujian_cbt')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('kelas_id')
                ->constrained('kelas')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();

            $table->unique(
                ['kelompok_peserta_kegiatan_ujian_cbt_id', 'kelas_id'],
                'kelompok_peserta_kegiatan_kelas_unik'
            );
        });

        Schema::create('kelompok_peserta_kegiatan_ujian_cbt_ruang', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelompok_peserta_kegiatan_ujian_cbt_id')
                ->constrained('kelompok_peserta_kegiatan_ujian_cbt')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('ruang_kegiatan_ujian_cbt_id')
                ->constrained('ruang_kegiatan_ujian_cbt')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->timestamps();

            $table->unique(
                ['kelompok_peserta_kegiatan_ujian_cbt_id', 'ruang_kegiatan_ujian_cbt_id'],
                'kelompok_peserta_kegiatan_ruang_unik'
            );
        });

        Schema::create('penempatan_peserta_ujian_cbt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kelompok_peserta_kegiatan_ujian_cbt_id')
                ->constrained('kelompok_peserta_kegiatan_ujian_cbt')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('anggota_kelas_id')
                ->constrained('anggota_kelas')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('ruang_kegiatan_ujian_cbt_id')
                ->constrained('ruang_kegiatan_ujian_cbt')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->unsignedSmallInteger('nomor_meja');
            $table->string('nomor_peserta', 80)->unique();
            $table->timestamps();

            $table->unique(
                ['kelompok_peserta_kegiatan_ujian_cbt_id', 'anggota_kelas_id'],
                'penempatan_peserta_kelompok_anggota_unik'
            );
            $table->unique(
                ['kelompok_peserta_kegiatan_ujian_cbt_id', 'ruang_kegiatan_ujian_cbt_id', 'nomor_meja'],
                'penempatan_peserta_kelompok_ruang_meja_unik'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('penempatan_peserta_ujian_cbt');
        Schema::dropIfExists('kelompok_peserta_kegiatan_ujian_cbt_ruang');
        Schema::dropIfExists('kelompok_peserta_kegiatan_ujian_cbt_kelas');
        Schema::dropIfExists('kelompok_peserta_kegiatan_ujian_cbt');

        Schema::table('jadwal_ujian_cbt', function (Blueprint $table) {
            $table->dropUnique('jadwal_kegiatan_tanggal_sesi_tingkat_unik');
            $table->dropForeign(['sesi_kegiatan_ujian_cbt_id']);
            $table->dropColumn('sesi_kegiatan_ujian_cbt_id');
        });
    }
};
