<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengecualian_presensi_siswa', fn (Blueprint $table) => $table->string('cakupan', 20)->nullable());
        Schema::create('pengecualian_presensi_anggota', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengecualian_id')->constrained('pengecualian_presensi_siswa')->restrictOnDelete();
            $table->foreignId('anggota_kelas_id')->constrained('anggota_kelas')->restrictOnDelete();
            $table->json('riwayat_pembatalan')->nullable();
            $table->unique(['pengecualian_id', 'anggota_kelas_id'], 'pengecualian_anggota_unik');
        });
        Schema::table('absensi_siswa', function (Blueprint $table) {
            $table->foreignId('penugasan_presensi_id')->nullable()->constrained('pengecualian_presensi_siswa')->restrictOnDelete();
            $table->json('sebelum_penugasan')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('absensi_siswa', function (Blueprint $table) {
            $table->dropConstrainedForeignId('penugasan_presensi_id');
            $table->dropColumn('sebelum_penugasan');
        });
        Schema::dropIfExists('pengecualian_presensi_anggota');
        Schema::table('pengecualian_presensi_siswa', fn (Blueprint $table) => $table->dropColumn('cakupan'));
    }
};
