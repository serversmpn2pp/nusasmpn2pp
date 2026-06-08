<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('peserta_ujian_cbt', function (Blueprint $table) {
            $table->foreignId('nilai_siswa_id')
                ->nullable()
                ->after('catatan')
                ->constrained('nilai_siswa')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamp('nilai_diterapkan_pada')->nullable()->after('nilai_siswa_id')->index();
            $table->foreignId('nilai_diterapkan_oleh_pengguna_id')
                ->nullable()
                ->after('nilai_diterapkan_pada')
                ->constrained('pengguna')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('peserta_ujian_cbt', function (Blueprint $table) {
            $table->dropForeign(['nilai_siswa_id']);
            $table->dropForeign(['nilai_diterapkan_oleh_pengguna_id']);
            $table->dropColumn([
                'nilai_siswa_id',
                'nilai_diterapkan_pada',
                'nilai_diterapkan_oleh_pengguna_id',
            ]);
        });
    }
};
