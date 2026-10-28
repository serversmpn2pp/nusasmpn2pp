<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('perangkat_monitoring', function (Blueprint $table) {
            $table->foreignId('acara_buku_tamu_id')->nullable()->constrained('acara_buku_tamu')->nullOnDelete();
            $table->date('pameran_tanggal')->nullable();
            $table->boolean('pameran_identitas')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('perangkat_monitoring', function (Blueprint $table) {
            $table->dropConstrainedForeignId('acara_buku_tamu_id');
            $table->dropColumn(['pameran_tanggal', 'pameran_identitas']);
        });
    }
};
