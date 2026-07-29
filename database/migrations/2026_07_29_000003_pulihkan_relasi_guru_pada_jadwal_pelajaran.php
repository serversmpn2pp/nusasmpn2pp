<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_pelajaran', function (Blueprint $table) {
            $table->foreign('guru_mata_pelajaran_id')
                ->references('id')
                ->on('guru_mata_pelajaran')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('jadwal_pelajaran', function (Blueprint $table) {
            $table->dropForeign(['guru_mata_pelajaran_id']);
        });
    }
};
