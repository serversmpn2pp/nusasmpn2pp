<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jadwal_ujian_cbt', function (Blueprint $table) {
            $table->timestamp('dikunci_pada')->nullable()->after('keterangan');
            $table->foreignId('dikunci_oleh_pengguna_id')
                ->nullable()
                ->after('dikunci_pada')
                ->constrained('pengguna')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->index('dikunci_pada', 'jadwal_ujian_cbt_dikunci_index');
        });

        Schema::table('ruang_ujian_cbt', function (Blueprint $table) {
            $table->timestamp('dikunci_pada')->nullable()->after('catatan');
            $table->foreignId('dikunci_oleh_pengguna_id')
                ->nullable()
                ->after('dikunci_pada')
                ->constrained('pengguna')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->index('dikunci_pada', 'ruang_ujian_cbt_dikunci_index');
        });
    }

    public function down(): void
    {
        Schema::table('ruang_ujian_cbt', function (Blueprint $table) {
            $table->dropIndex('ruang_ujian_cbt_dikunci_index');
            $table->dropForeign(['dikunci_oleh_pengguna_id']);
            $table->dropColumn(['dikunci_pada', 'dikunci_oleh_pengguna_id']);
        });

        Schema::table('jadwal_ujian_cbt', function (Blueprint $table) {
            $table->dropIndex('jadwal_ujian_cbt_dikunci_index');
            $table->dropForeign(['dikunci_oleh_pengguna_id']);
            $table->dropColumn(['dikunci_pada', 'dikunci_oleh_pengguna_id']);
        });
    }
};
