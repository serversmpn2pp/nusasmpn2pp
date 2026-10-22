<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['mitra_humas', 'kerja_sama_humas'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->uuid('token_pembuatan')->nullable()->unique();
                $t->foreignId('dibuat_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach (['mitra_humas', 'kerja_sama_humas'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropConstrainedForeignId('dibuat_oleh_pengguna_id');
                $t->dropColumn('token_pembuatan');
            });
        }
    }
};
