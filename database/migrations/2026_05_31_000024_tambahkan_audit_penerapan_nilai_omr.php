<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hasil_scan_ljk_ujian_omr', function (Blueprint $table) {
            $table->foreignId('nilai_siswa_id')
                ->nullable()
                ->after('nilai')
                ->constrained('nilai_siswa')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamp('diterapkan_pada')->nullable()->after('nilai_siswa_id')->index();
            $table->foreignId('diterapkan_oleh_pengguna_id')
                ->nullable()
                ->after('diterapkan_pada')
                ->constrained('pengguna')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('hasil_scan_ljk_ujian_omr', function (Blueprint $table) {
            $table->dropForeign(['nilai_siswa_id']);
            $table->dropForeign(['diterapkan_oleh_pengguna_id']);
            $table->dropColumn([
                'nilai_siswa_id',
                'diterapkan_pada',
                'diterapkan_oleh_pengguna_id',
            ]);
        });
    }
};
