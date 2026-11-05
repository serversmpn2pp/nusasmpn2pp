<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absensi_siswa', function (Blueprint $table) {
            $table->string('alasan_hadir_manual', 50)->nullable();
            $table->unsignedSmallInteger('sanksi_manual_menit')->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('absensi_siswa', function (Blueprint $table) {
            $table->dropColumn(['alasan_hadir_manual', 'sanksi_manual_menit']);
        });
    }
};
