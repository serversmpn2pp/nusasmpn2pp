<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hasil_scan_ljk_ujian_omr', function (Blueprint $table) {
            $table->text('catatan_koreksi')->nullable()->after('catatan');
            $table->timestamp('dikoreksi_pada')->nullable()->after('catatan_koreksi')->index();
            $table->foreignId('dikoreksi_oleh_pengguna_id')
                ->nullable()
                ->after('dikoreksi_pada')
                ->constrained('pengguna')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::table('hasil_scan_ljk_ujian_omr', function (Blueprint $table) {
            $table->dropForeign(['dikoreksi_oleh_pengguna_id']);
            $table->dropColumn([
                'catatan_koreksi',
                'dikoreksi_pada',
                'dikoreksi_oleh_pengguna_id',
            ]);
        });
    }
};
