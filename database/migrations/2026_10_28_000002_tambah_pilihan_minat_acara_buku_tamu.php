<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acara_buku_tamu', function (Blueprint $table) {
            $table->json('pilihan_minat')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('acara_buku_tamu', function (Blueprint $table) {
            $table->dropColumn('pilihan_minat');
        });
    }
};
