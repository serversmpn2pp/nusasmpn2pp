<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acara_buku_tamu', function (Blueprint $table) {
            $table->string('banner_tampilan', 16)->default('potong');
            $table->unsignedTinyInteger('banner_fokus_desktop')->default(66);
            $table->unsignedTinyInteger('banner_fokus_mobile')->default(74);
        });
    }

    public function down(): void
    {
        Schema::table('acara_buku_tamu', function (Blueprint $table) {
            $table->dropColumn(['banner_tampilan', 'banner_fokus_desktop', 'banner_fokus_mobile']);
        });
    }
};
