<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_pelajaran', function (Blueprint $table) {
            $table->dropForeign(['guru_mata_pelajaran_id']);
        });

        Schema::table('jadwal_pelajaran', function (Blueprint $table) {
            $table->foreignId('guru_mata_pelajaran_id')->nullable()->change();
            $table->foreignId('mata_pelajaran_id')
                ->nullable()
                ->after('guru_mata_pelajaran_id')
                ->constrained('mata_pelajaran')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        DB::table('jadwal_pelajaran')
            ->whereNull('guru_mata_pelajaran_id')
            ->delete();

        Schema::table('jadwal_pelajaran', function (Blueprint $table) {
            $table->dropConstrainedForeignId('mata_pelajaran_id');
        });

        Schema::table('jadwal_pelajaran', function (Blueprint $table) {
            $table->foreignId('guru_mata_pelajaran_id')
                ->nullable(false)
                ->change();
            $table->foreign('guru_mata_pelajaran_id')
                ->references('id')
                ->on('guru_mata_pelajaran')
                ->cascadeOnDelete();
        });
    }
};
