<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_ujian_cbt', function (Blueprint $table) {
            $table->dropUnique('jadwal_kegiatan_tanggal_sesi_tingkat_unik');
            $table->unique(
                ['kegiatan_ujian_cbt_id', 'tanggal', 'tingkat', 'waktu_mulai'],
                'jadwal_kegiatan_tanggal_tingkat_mulai_unik'
            );
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_ujian_cbt', function (Blueprint $table) {
            $table->dropUnique('jadwal_kegiatan_tanggal_tingkat_mulai_unik');
            $table->unique(
                ['kegiatan_ujian_cbt_id', 'tanggal', 'sesi_kegiatan_ujian_cbt_id', 'tingkat'],
                'jadwal_kegiatan_tanggal_sesi_tingkat_unik'
            );
        });
    }
};
