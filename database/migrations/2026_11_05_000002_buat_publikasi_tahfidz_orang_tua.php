<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pertemuan_tahfidz', function (Blueprint $table) {
            $table->timestamp('selesai_pada')->nullable();
            $table->foreignId('diselesaikan_oleh')->nullable()->constrained('pengguna')->nullOnDelete();
        });

        Schema::table('kelulusan_tahfidz', function (Blueprint $table) {
            $table->timestamp('dibagikan_pada')->nullable();
            $table->foreignId('dibagikan_oleh')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->unsignedInteger('versi_publikasi')->default(0);
        });

        Schema::create('publikasi_rapor_tahfidz', function (Blueprint $table) {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswa')->restrictOnDelete();
            $table->foreignId('tahun_pelajaran_id')->constrained('tahun_pelajaran')->restrictOnDelete();
            $table->json('salinan');
            $table->unsignedInteger('versi');
            $table->timestamp('dipublikasikan_pada')->nullable();
            $table->foreignId('dipublikasikan_oleh')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamps();
            $table->unique(['siswa_id', 'tahun_pelajaran_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publikasi_rapor_tahfidz');
        Schema::table('kelulusan_tahfidz', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dibagikan_oleh');
            $table->dropColumn(['dibagikan_pada', 'versi_publikasi']);
        });
        Schema::table('pertemuan_tahfidz', function (Blueprint $table) {
            $table->dropConstrainedForeignId('diselesaikan_oleh');
            $table->dropColumn('selesai_pada');
        });
    }
};
