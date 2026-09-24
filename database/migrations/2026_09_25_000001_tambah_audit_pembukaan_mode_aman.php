<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aktivitas_keamanan_ujian_cbt', function (Blueprint $table) {
            $table->foreignId('oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->text('catatan')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('aktivitas_keamanan_ujian_cbt', function (Blueprint $table) {
            $table->dropForeign(['oleh_pengguna_id']);
            $table->dropColumn(['oleh_pengguna_id', 'catatan']);
        });
    }
};
