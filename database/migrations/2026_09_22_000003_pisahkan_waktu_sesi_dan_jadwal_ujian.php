<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sesi_kegiatan_ujian_cbt', function (Blueprint $table) {
            $table->time('waktu_mulai')->nullable()->change();
            $table->time('waktu_selesai')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('sesi_kegiatan_ujian_cbt')
            ->whereNull('waktu_mulai')
            ->update(['waktu_mulai' => '00:00:00']);
        DB::table('sesi_kegiatan_ujian_cbt')
            ->whereNull('waktu_selesai')
            ->update(['waktu_selesai' => '23:59:00']);

        Schema::table('sesi_kegiatan_ujian_cbt', function (Blueprint $table) {
            $table->time('waktu_mulai')->nullable(false)->change();
            $table->time('waktu_selesai')->nullable(false)->change();
        });
    }
};
