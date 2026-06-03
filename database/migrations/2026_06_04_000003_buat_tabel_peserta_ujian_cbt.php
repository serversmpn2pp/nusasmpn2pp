<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('peserta_ujian_cbt', function (Blueprint $table) {
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
            $table->foreignId('kelas_ujian_cbt_id')
                ->constrained('kelas_ujian_cbt')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('anggota_kelas_id')
                ->constrained('anggota_kelas')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->string('nomor_peserta', 80)->unique();
            $table->string('username', 80)->unique();
            $table->string('kata_sandi', 40);
            $table->string('token_akses', 40)->unique();
            $table->string('status', 30)->default('aktif')->index();
            $table->dateTime('waktu_mulai')->nullable();
            $table->dateTime('waktu_selesai')->nullable();
            $table->unsignedSmallInteger('menit_tersisa')->nullable();
            $table->string('ip_terakhir', 45)->nullable();
            $table->string('perangkat_terakhir', 120)->nullable();
            $table->text('user_agent_terakhir')->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('dibuat_oleh_pengguna_id')
                ->nullable()
                ->constrained('pengguna')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();

            $table->unique(['ujian_cbt_id', 'anggota_kelas_id'], 'peserta_ujian_cbt_anggota_unik');
            $table->index(['sesi_ujian_cbt_id', 'status']);
            $table->index(['kelas_ujian_cbt_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peserta_ujian_cbt');
    }
};
