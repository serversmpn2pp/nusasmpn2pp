<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periode_berhalangan_ibadah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_pelajaran_id')->constrained('tahun_pelajaran')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignId('kelas_id')->nullable()->constrained('kelas')->nullOnDelete();
            $table->foreignId('anggota_kelas_id')->nullable()->constrained('anggota_kelas')->nullOnDelete();
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->nullable();
            $table->string('status', 30)->default('aktif');
            $table->unsignedTinyInteger('batas_hari_konfirmasi')->default(7);
            $table->date('perlu_konfirmasi_sejak')->nullable();
            $table->foreignId('dimulai_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->foreignId('diselesaikan_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamp('diselesaikan_pada')->nullable();
            $table->string('cara_selesai', 40)->nullable();
            $table->text('catatan_privat')->nullable();
            $table->timestamps();

            $table->index(['tahun_pelajaran_id', 'siswa_id', 'status'], 'periode_berhalangan_siswa_status_idx');
            $table->index(['status', 'perlu_konfirmasi_sejak'], 'periode_berhalangan_konfirmasi_idx');
        });

        Schema::create('presensi_berhalangan_ibadah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_berhalangan_ibadah_id')->constrained('periode_berhalangan_ibadah')->cascadeOnDelete();
            $table->foreignId('jadwal_kegiatan_ibadah_id')->constrained('jadwal_kegiatan_ibadah')->cascadeOnDelete();
            $table->foreignId('kegiatan_ibadah_id')->constrained('kegiatan_ibadah')->cascadeOnDelete();
            $table->foreignId('tahun_pelajaran_id')->constrained('tahun_pelajaran')->cascadeOnDelete();
            $table->foreignId('kelas_id')->nullable()->constrained('kelas')->nullOnDelete();
            $table->foreignId('anggota_kelas_id')->nullable()->constrained('anggota_kelas')->nullOnDelete();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignId('dipindai_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->date('tanggal');
            $table->time('waktu_scan');
            $table->string('sumber', 20)->default('kamera');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->unique(
                ['kegiatan_ibadah_id', 'siswa_id', 'tanggal'],
                'presensi_berhalangan_siswa_harian_unik'
            );
            $table->index(['tanggal', 'kegiatan_ibadah_id'], 'presensi_berhalangan_tanggal_idx');
        });

        Schema::create('log_scan_berhalangan_ibadah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presensi_berhalangan_ibadah_id')->nullable()->constrained('presensi_berhalangan_ibadah')->nullOnDelete();
            $table->foreignId('periode_berhalangan_ibadah_id')->nullable()->constrained('periode_berhalangan_ibadah')->nullOnDelete();
            $table->foreignId('jadwal_kegiatan_ibadah_id')->nullable()->constrained('jadwal_kegiatan_ibadah')->nullOnDelete();
            $table->foreignId('kegiatan_ibadah_id')->nullable()->constrained('kegiatan_ibadah')->nullOnDelete();
            $table->foreignId('siswa_id')->nullable()->constrained('siswa')->nullOnDelete();
            $table->foreignId('dipindai_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->string('isi_scan', 100);
            $table->string('nisn', 40)->nullable();
            $table->timestamp('waktu_scan');
            $table->date('tanggal');
            $table->boolean('berhasil')->default(false);
            $table->string('status_scan', 50);
            $table->text('pesan')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['tanggal', 'kegiatan_ibadah_id', 'berhasil'], 'log_berhalangan_tanggal_idx');
            $table->index(['nisn', 'waktu_scan'], 'log_berhalangan_nisn_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('log_scan_berhalangan_ibadah');
        Schema::dropIfExists('presensi_berhalangan_ibadah');
        Schema::dropIfExists('periode_berhalangan_ibadah');
    }
};
