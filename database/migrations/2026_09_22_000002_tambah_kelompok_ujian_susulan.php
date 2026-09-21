<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('peserta_ujian_cbt', function (Blueprint $table) {
            $table->uuid('kelompok_susulan')->nullable()->after('status_susulan')->index();
        });
    }

    public function down(): void
    {
        Schema::table('peserta_ujian_cbt', function (Blueprint $table) {
            $table->dropColumn('kelompok_susulan');
        });
    }
};
