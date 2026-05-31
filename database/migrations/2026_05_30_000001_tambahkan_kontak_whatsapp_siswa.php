<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            $table->string('nomor_wa_ayah', 30)->nullable()->after('nama_ayah');
            $table->string('nomor_wa_ibu', 30)->nullable()->after('nama_ibu');
            $table->string('nama_wali')->nullable()->after('pekerjaan_ibu');
            $table->string('hubungan_wali', 100)->nullable()->after('nama_wali');
            $table->string('nomor_wa_wali', 30)->nullable()->after('hubungan_wali');
            $table->string('kontak_absensi_utama', 20)->nullable()->after('nomor_wa_wali');
        });
    }

    public function down(): void
    {
        Schema::table('siswa', function (Blueprint $table) {
            $table->dropColumn([
                'nomor_wa_ayah',
                'nomor_wa_ibu',
                'nama_wali',
                'hubungan_wali',
                'nomor_wa_wali',
                'kontak_absensi_utama',
            ]);
        });
    }
};
