<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('komponen_nilai', function (Blueprint $table) {
            $table->timestamp('sts_manual_difinalisasi_pada')->nullable();
            $table->foreignId('sts_manual_difinalisasi_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->char('sts_manual_sidik_final', 64)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('komponen_nilai', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sts_manual_difinalisasi_oleh_pengguna_id');
            $table->dropColumn(['sts_manual_difinalisasi_pada', 'sts_manual_sidik_final']);
        });
    }
};
