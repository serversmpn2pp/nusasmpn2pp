<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('perangkat_monitoring', function (Blueprint $table) {
            $table->string('cakupan', 12)->default('siswa');
            $table->foreignId('kegiatan_ujian_cbt_id')->nullable()->constrained('kegiatan_ujian_cbt')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('perangkat_monitoring', function (Blueprint $table) {
            $table->dropConstrainedForeignId('kegiatan_ujian_cbt_id');
            $table->dropColumn('cakupan');
        });
    }
};
