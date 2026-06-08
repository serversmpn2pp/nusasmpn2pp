<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ruang_ujian_cbt', function (Blueprint $table) {
            $table->string('bukti_daftar_hadir_lokasi_file')->nullable()->after('catatan');
            $table->string('bukti_daftar_hadir_nama_file_asli')->nullable()->after('bukti_daftar_hadir_lokasi_file');
            $table->string('bukti_daftar_hadir_tipe_file')->nullable()->after('bukti_daftar_hadir_nama_file_asli');
            $table->unsignedBigInteger('bukti_daftar_hadir_ukuran_file')->nullable()->after('bukti_daftar_hadir_tipe_file');
            $table->timestamp('bukti_daftar_hadir_diunggah_pada')->nullable()->after('bukti_daftar_hadir_ukuran_file');
            $table->foreignId('bukti_daftar_hadir_diunggah_oleh_pengguna_id')
                ->nullable()
                ->after('bukti_daftar_hadir_diunggah_pada')
                ->constrained('pengguna')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->string('bukti_berita_acara_lokasi_file')->nullable()->after('bukti_daftar_hadir_diunggah_oleh_pengguna_id');
            $table->string('bukti_berita_acara_nama_file_asli')->nullable()->after('bukti_berita_acara_lokasi_file');
            $table->string('bukti_berita_acara_tipe_file')->nullable()->after('bukti_berita_acara_nama_file_asli');
            $table->unsignedBigInteger('bukti_berita_acara_ukuran_file')->nullable()->after('bukti_berita_acara_tipe_file');
            $table->timestamp('bukti_berita_acara_diunggah_pada')->nullable()->after('bukti_berita_acara_ukuran_file');
            $table->foreignId('bukti_berita_acara_diunggah_oleh_pengguna_id')
                ->nullable()
                ->after('bukti_berita_acara_diunggah_pada')
                ->constrained('pengguna')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('ruang_ujian_cbt', function (Blueprint $table) {
            $table->dropForeign(['bukti_daftar_hadir_diunggah_oleh_pengguna_id']);
            $table->dropForeign(['bukti_berita_acara_diunggah_oleh_pengguna_id']);
            $table->dropColumn([
                'bukti_daftar_hadir_lokasi_file',
                'bukti_daftar_hadir_nama_file_asli',
                'bukti_daftar_hadir_tipe_file',
                'bukti_daftar_hadir_ukuran_file',
                'bukti_daftar_hadir_diunggah_pada',
                'bukti_daftar_hadir_diunggah_oleh_pengguna_id',
                'bukti_berita_acara_lokasi_file',
                'bukti_berita_acara_nama_file_asli',
                'bukti_berita_acara_tipe_file',
                'bukti_berita_acara_ukuran_file',
                'bukti_berita_acara_diunggah_pada',
                'bukti_berita_acara_diunggah_oleh_pengguna_id',
            ]);
        });
    }
};
