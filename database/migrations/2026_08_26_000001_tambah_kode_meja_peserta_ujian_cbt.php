<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('penempatan_peserta_ujian_cbt', function (Blueprint $table) {
            $table->string('kode_meja', 80)->nullable()->after('nomor_meja');
            $table->index('kode_meja', 'penempatan_peserta_kode_meja_index');
        });

        Schema::table('peserta_ujian_cbt', function (Blueprint $table) {
            $table->string('kode_meja', 80)->nullable()->after('nomor_meja');
            $table->index('kode_meja', 'peserta_ujian_kode_meja_index');
        });
    }

    public function down(): void
    {
        Schema::table('peserta_ujian_cbt', function (Blueprint $table) {
            $table->dropIndex('peserta_ujian_kode_meja_index');
            $table->dropColumn('kode_meja');
        });

        Schema::table('penempatan_peserta_ujian_cbt', function (Blueprint $table) {
            $table->dropIndex('penempatan_peserta_kode_meja_index');
            $table->dropColumn('kode_meja');
        });
    }
};
