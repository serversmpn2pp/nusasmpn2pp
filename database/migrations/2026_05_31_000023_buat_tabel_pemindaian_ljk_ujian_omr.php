<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batch_scan_ujian_omr', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ujian_omr_id')
                ->constrained('ujian_omr')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('nama_file_asli');
            $table->string('lokasi_file');
            $table->unsignedSmallInteger('jumlah_halaman_pdf')->default(0);
            $table->unsignedSmallInteger('jumlah_ljk_terdeteksi')->default(0);
            $table->unsignedSmallInteger('jumlah_berhasil')->default(0);
            $table->unsignedSmallInteger('jumlah_perlu_diperiksa')->default(0);
            $table->string('status', 30)->default('diproses')->index();
            $table->text('pesan_error')->nullable();
            $table->foreignId('dibuat_oleh_pengguna_id')
                ->nullable()
                ->constrained('pengguna')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();

            $table->index(['ujian_omr_id', 'created_at']);
        });

        Schema::create('hasil_scan_ljk_ujian_omr', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_scan_ujian_omr_id')
                ->constrained('batch_scan_ujian_omr')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('lembar_jawab_ujian_omr_id')
                ->nullable()
                ->constrained('lembar_jawab_ujian_omr')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->unsignedSmallInteger('halaman_pdf');
            $table->unsignedTinyInteger('urutan_ljk');
            $table->string('token_terbaca', 50)->nullable()->index();
            $table->string('lokasi_pratinjau')->nullable();
            $table->string('status', 40)->default('perlu_diperiksa')->index();
            $table->unsignedSmallInteger('jumlah_benar')->default(0);
            $table->unsignedSmallInteger('jumlah_salah')->default(0);
            $table->unsignedSmallInteger('jumlah_kosong')->default(0);
            $table->unsignedSmallInteger('jumlah_ganda')->default(0);
            $table->decimal('nilai', 5, 2)->nullable();
            $table->text('catatan')->nullable();
            $table->timestamps();

            $table->unique(['batch_scan_ujian_omr_id', 'halaman_pdf', 'urutan_ljk'], 'hasil_scan_batch_halaman_urutan_unik');
            $table->index(['lembar_jawab_ujian_omr_id', 'status'], 'hasil_scan_ljk_status_index');
        });

        Schema::create('jawaban_hasil_scan_ujian_omr', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hasil_scan_ljk_ujian_omr_id')
                ->constrained('hasil_scan_ljk_ujian_omr')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->unsignedSmallInteger('nomor_soal');
            $table->string('jawaban', 2)->nullable();
            $table->string('status', 20)->default('terbaca')->index();
            $table->json('tingkat_kehitaman')->nullable();
            $table->boolean('benar')->nullable();
            $table->timestamps();

            $table->unique(['hasil_scan_ljk_ujian_omr_id', 'nomor_soal'], 'jawaban_scan_hasil_nomor_unik');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jawaban_hasil_scan_ujian_omr');
        Schema::dropIfExists('hasil_scan_ljk_ujian_omr');
        Schema::dropIfExists('batch_scan_ujian_omr');
    }
};
