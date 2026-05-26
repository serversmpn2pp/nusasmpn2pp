<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menjalankan migration.
     */
    public function up(): void
    {
        Schema::table('laporan_pembinaan_siswa', function (Blueprint $table) {
            $table->string('tempat_kejadian', 150)->nullable();
        });
    }

    /**
     * Membatalkan migration.
     */
    public function down(): void
    {
        Schema::table('laporan_pembinaan_siswa', function (Blueprint $table) {
            $table->dropColumn('tempat_kejadian');
        });
    }
};
