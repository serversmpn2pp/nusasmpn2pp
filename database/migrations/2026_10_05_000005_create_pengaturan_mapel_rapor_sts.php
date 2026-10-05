<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan_mapel_rapor_sts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_ujian_cbt_id')->constrained('kegiatan_ujian_cbt')->cascadeOnDelete();
            $table->unsignedTinyInteger('tingkat');
            $table->json('mapel_dikecualikan');
            $table->unsignedInteger('versi')->default(1);
            $table->string('alasan', 500);
            $table->foreignId('diubah_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamps();
            $table->unique(['kegiatan_ujian_cbt_id', 'tingkat'], 'mapel_sts_kegiatan_tingkat_unik');
        });
        Schema::create('riwayat_mapel_rapor_sts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengaturan_mapel_rapor_sts_id')->constrained('pengaturan_mapel_rapor_sts')->cascadeOnDelete();
            $table->unsignedInteger('versi');
            $table->json('mapel_dikecualikan_sebelum');
            $table->json('mapel_dikecualikan_sesudah');
            $table->string('alasan', 500);
            $table->foreignId('pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_mapel_rapor_sts');
        Schema::dropIfExists('pengaturan_mapel_rapor_sts');
    }
};
