<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengaturan_poin_keterlambatan', function (Blueprint $table) {
            $table->boolean('otomatis_langsung')->default(false);
            $table->date('berlaku_mulai')->nullable();
            $table->unsignedSmallInteger('poin_terlambat')->default(15);
            $table->unsignedSmallInteger('poin_alfa')->default(25);
            $table->date('alfa_diproses_sampai')->nullable();
        });
        Schema::table('laporan_pembinaan_siswa', function (Blueprint $table) {
            $table->string('kunci_presensi_otomatis', 100)->nullable()->unique();
            $table->string('jenis_presensi_otomatis', 20)->nullable();
            $table->timestamp('poin_dikecualikan_pada')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('laporan_pembinaan_siswa', function (Blueprint $table) {
            $table->dropUnique(['kunci_presensi_otomatis']);
            $table->dropColumn(['kunci_presensi_otomatis', 'jenis_presensi_otomatis', 'poin_dikecualikan_pada']);
        });
        Schema::table('pengaturan_poin_keterlambatan', function (Blueprint $table) {
            $table->dropColumn(['otomatis_langsung', 'berlaku_mulai', 'poin_terlambat', 'poin_alfa', 'alfa_diproses_sampai']);
        });
    }
};
