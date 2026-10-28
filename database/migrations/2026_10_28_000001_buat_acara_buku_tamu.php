<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acara_buku_tamu', function (Blueprint $t) {
            $t->id();
            $t->string('nama', 180);
            $t->string('lokasi', 200);
            $t->text('pengantar')->nullable();
            $t->dateTime('mulai_pada');
            $t->dateTime('selesai_pada');
            $t->string('status', 16)->default('draf');
            $t->string('kode_publik', 32)->unique();
            $t->string('banner')->nullable();
            $t->uuid('kunci_pembuatan')->unique();
            $t->unsignedInteger('versi')->default(0);
            $t->foreignId('dibuat_oleh')->nullable()->constrained('pengguna')->nullOnDelete();
            $t->foreignId('diubah_oleh')->nullable()->constrained('pengguna')->nullOnDelete();
            $t->timestamps();
            $t->index(['status', 'mulai_pada']);
        });
        Schema::create('respon_acara_buku_tamu', function (Blueprint $t) {
            $t->id();
            $t->foreignId('acara_buku_tamu_id')->constrained('acara_buku_tamu')->restrictOnDelete();
            $t->uuid('kunci_kiriman');
            $t->string('sidik_kiriman', 64);
            $t->string('nama', 120);
            $t->string('instansi', 180)->nullable();
            $t->string('kategori', 24);
            $t->json('minat');
            $t->unsignedTinyInteger('kepuasan');
            $t->unsignedTinyInteger('nilai_nusa')->nullable();
            $t->text('saran')->nullable();
            $t->string('status', 16)->default('valid');
            $t->text('alasan_status')->nullable();
            $t->unsignedInteger('versi')->default(0);
            $t->timestamps();
            $t->unique(['acara_buku_tamu_id', 'kunci_kiriman']);
            $t->index(['acara_buku_tamu_id', 'status', 'created_at'], 'respon_acara_status_waktu');
        });
        Schema::create('riwayat_acara_buku_tamu', function (Blueprint $t) {
            $t->id();
            $t->foreignId('acara_buku_tamu_id')->constrained('acara_buku_tamu')->restrictOnDelete();
            $t->foreignId('respon_id')->nullable()->constrained('respon_acara_buku_tamu')->restrictOnDelete();
            $t->foreignId('pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $t->string('aksi', 60);
            $t->json('perubahan')->nullable();
            $t->text('alasan')->nullable();
            $t->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_acara_buku_tamu');
        Schema::dropIfExists('respon_acara_buku_tamu');
        Schema::dropIfExists('acara_buku_tamu');
    }
};
