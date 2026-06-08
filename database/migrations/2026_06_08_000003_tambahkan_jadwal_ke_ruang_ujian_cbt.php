<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ruang_ujian_cbt', function (Blueprint $table) {
            $table->foreignId('jadwal_ujian_cbt_id')
                ->nullable()
                ->after('sesi_ujian_cbt_id')
                ->constrained('jadwal_ujian_cbt')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->index(['ujian_cbt_id', 'jadwal_ujian_cbt_id'], 'ruang_ujian_cbt_jadwal_index');
        });
    }

    public function down(): void
    {
        Schema::table('ruang_ujian_cbt', function (Blueprint $table) {
            $table->dropIndex('ruang_ujian_cbt_jadwal_index');
            $table->dropForeign(['jadwal_ujian_cbt_id']);
            $table->dropColumn('jadwal_ujian_cbt_id');
        });
    }
};
