<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dokumen_humas', function (Blueprint $table) {
            $table->string('urusan', 40)->nullable()->index();
            $table->string('jenis_dokumen', 40)->nullable()->index();
            $table->string('sumber_klasifikasi', 40)->nullable();
            $table->unsignedInteger('versi_metadata')->default(1);
            $table->timestamp('klasifikasi_diperiksa_pada')->nullable()->index();
            $table->foreignId('klasifikasi_pemeriksa_id')->nullable()->constrained('pengguna')->nullOnDelete();
        });
        foreach (['program' => 'program_kerja_humas', 'program_komite' => 'program_komite_humas', 'surat' => 'surat_tu'] as $kode => $target) {
            Schema::create('dokumen_humas_'.$kode, function (Blueprint $table) use ($target) {
                $table->id();
                $table->foreignId('dokumen_humas_id')->constrained('dokumen_humas')->cascadeOnDelete();
                $table->foreignId('target_id')->constrained($target)->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['dokumen_humas_id', 'target_id']);
            });
        }
        Schema::create('riwayat_klasifikasi_dokumen_humas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dokumen_humas_id')->constrained('dokumen_humas')->cascadeOnDelete();
            $table->string('aksi', 60);
            $table->json('sebelum')->nullable();
            $table->json('sesudah');
            $table->text('alasan');
            $table->foreignId('pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamp('created_at');
            $table->index(['dokumen_humas_id', 'created_at'], 'riwayat_klasifikasi_humas_waktu');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_klasifikasi_dokumen_humas');
        foreach (['surat', 'program_komite', 'program'] as $kode) {
            Schema::dropIfExists('dokumen_humas_'.$kode);
        }
        Schema::table('dokumen_humas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('klasifikasi_pemeriksa_id');
            $table->dropColumn(['urusan', 'jenis_dokumen', 'sumber_klasifikasi', 'versi_metadata', 'klasifikasi_diperiksa_pada']);
        });
    }
};
