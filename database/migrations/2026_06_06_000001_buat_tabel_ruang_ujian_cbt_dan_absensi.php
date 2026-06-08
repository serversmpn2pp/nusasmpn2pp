<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ruang_ujian_cbt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ujian_cbt_id')
                ->constrained('ujian_cbt')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('sesi_ujian_cbt_id')
                ->nullable()
                ->constrained('sesi_ujian_cbt')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->string('kode', 40);
            $table->string('nama', 120);
            $table->string('lokasi')->nullable();
            $table->unsignedSmallInteger('kapasitas')->nullable();
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
            $table->dateTime('waktu_mulai_aktual')->nullable();
            $table->dateTime('waktu_selesai_aktual')->nullable();
            $table->text('berita_acara')->nullable();
            $table->text('hambatan')->nullable();
            $table->text('tindak_lanjut')->nullable();
            $table->text('catatan')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->timestamps();

            $table->unique(['ujian_cbt_id', 'sesi_ujian_cbt_id', 'kode'], 'ruang_ujian_cbt_kode_unik');
            $table->index(['ujian_cbt_id', 'sesi_ujian_cbt_id']);
        });

        Schema::table('peserta_ujian_cbt', function (Blueprint $table) {
            $table->foreignId('ruang_ujian_cbt_id')
                ->nullable()
                ->after('kelas_ujian_cbt_id')
                ->constrained('ruang_ujian_cbt')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->unsignedSmallInteger('nomor_meja')->nullable()->after('ruang_ujian_cbt_id');
            $table->string('status_kehadiran_ujian', 30)->default('belum_absen')->after('status')->index();
            $table->timestamp('absen_ujian_pada')->nullable()->after('status_kehadiran_ujian');
            $table->foreignId('absen_ujian_oleh_pengguna_id')
                ->nullable()
                ->after('absen_ujian_pada')
                ->constrained('pengguna')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->text('catatan_kehadiran_ujian')->nullable()->after('catatan');

            $table->unique(['ruang_ujian_cbt_id', 'nomor_meja'], 'peserta_cbt_ruang_meja_unik');
            $table->index(['ruang_ujian_cbt_id', 'status_kehadiran_ujian'], 'peserta_cbt_ruang_absen_index');
        });
    }

    public function down(): void
    {
        Schema::table('peserta_ujian_cbt', function (Blueprint $table) {
            $table->dropUnique('peserta_cbt_ruang_meja_unik');
            $table->dropIndex('peserta_cbt_ruang_absen_index');
            $table->dropForeign(['ruang_ujian_cbt_id']);
            $table->dropForeign(['absen_ujian_oleh_pengguna_id']);
            $table->dropColumn([
                'ruang_ujian_cbt_id',
                'nomor_meja',
                'status_kehadiran_ujian',
                'absen_ujian_pada',
                'absen_ujian_oleh_pengguna_id',
                'catatan_kehadiran_ujian',
            ]);
        });

        Schema::dropIfExists('ruang_ujian_cbt');
    }
};
