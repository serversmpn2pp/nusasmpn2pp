<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agenda_humas', function (Blueprint $table) {
            $table->string('token_presensi', 64)->nullable()->unique();
            $table->boolean('presensi_dibuka')->default(false);
            $table->timestamp('presensi_diubah_pada')->nullable();
        });
        Schema::table('peserta_pertemuan_humas', function (Blueprint $table) {
            $table->foreignId('orang_tua_wali_id')->nullable()->constrained('orang_tua_wali')->nullOnDelete();
            $table->json('anak_undangan')->nullable();
            $table->string('sumber_kehadiran', 20)->nullable();
            $table->unsignedInteger('versi_presensi')->default(0);
            $table->unique(['agenda_humas_id', 'orang_tua_wali_id'], 'peserta_humas_akun_unik');
        });
    }

    public function down(): void
    {
        Schema::table('peserta_pertemuan_humas', function (Blueprint $table) {
            $table->dropUnique('peserta_humas_akun_unik');
            $table->dropConstrainedForeignId('orang_tua_wali_id');
            $table->dropColumn(['anak_undangan', 'sumber_kehadiran', 'versi_presensi']);
        });
        Schema::table('agenda_humas', function (Blueprint $table) {
            $table->dropUnique(['token_presensi']);
            $table->dropColumn(['token_presensi', 'presensi_dibuka', 'presensi_diubah_pada']);
        });
    }
};
