<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('akun_peserta_cbt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jenis_ujian_cbt_id')
                ->constrained('jenis_ujian_cbt')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('tahun_pelajaran_id')
                ->constrained('tahun_pelajaran')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->string('semester', 20);
            $table->foreignId('anggota_kelas_id')
                ->constrained('anggota_kelas')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->string('nomor_peserta', 80)->unique();
            $table->string('username', 80);
            $table->string('kata_sandi', 40);
            $table->string('kode_qr', 80)->unique();
            $table->string('status', 30)->default('aktif')->index();
            $table->timestamps();

            $table->unique(
                ['jenis_ujian_cbt_id', 'tahun_pelajaran_id', 'semester', 'anggota_kelas_id'],
                'akun_peserta_cbt_rangkaian_anggota_unik'
            );
            $table->unique(
                ['jenis_ujian_cbt_id', 'tahun_pelajaran_id', 'semester', 'username'],
                'akun_peserta_cbt_rangkaian_username_unik'
            );
            $table->index(['jenis_ujian_cbt_id', 'tahun_pelajaran_id', 'semester'], 'akun_peserta_cbt_rangkaian_index');
        });

        Schema::table('peserta_ujian_cbt', function (Blueprint $table) {
            $table->foreignId('akun_peserta_cbt_id')
                ->nullable()
                ->after('anggota_kelas_id')
                ->constrained('akun_peserta_cbt')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('peserta_ujian_cbt', function (Blueprint $table) {
            $table->dropConstrainedForeignId('akun_peserta_cbt_id');
        });

        Schema::dropIfExists('akun_peserta_cbt');
    }
};
