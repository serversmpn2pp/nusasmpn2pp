<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('folder_soal_cbt', function (Blueprint $table) {
            $table->foreignId('kegiatan_ujian_cbt_id')->nullable()->constrained('kegiatan_ujian_cbt')->nullOnDelete();
            $table->unique(['kegiatan_ujian_cbt_id', 'mata_pelajaran_id', 'tingkat'], 'folder_soal_cbt_kegiatan_mapel_tingkat_unik');
        });
    }

    public function down(): void
    {
        Schema::table('folder_soal_cbt', function (Blueprint $table) {
            $table->dropUnique('folder_soal_cbt_kegiatan_mapel_tingkat_unik');
            $table->dropConstrainedForeignId('kegiatan_ujian_cbt_id');
        });
    }
};
