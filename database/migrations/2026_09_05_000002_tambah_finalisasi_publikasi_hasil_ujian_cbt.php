<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ujian_cbt', function (Blueprint $table) {
            $table->timestamp('hasil_difinalisasi_pada')->nullable()->after('tampilkan_hasil');
            $table->foreignId('hasil_difinalisasi_oleh_pengguna_id')
                ->nullable()
                ->after('hasil_difinalisasi_pada')
                ->constrained('pengguna')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamp('hasil_dipublikasikan_pada')->nullable()
                ->after('hasil_difinalisasi_oleh_pengguna_id');
            $table->foreignId('hasil_dipublikasikan_oleh_pengguna_id')
                ->nullable()
                ->after('hasil_dipublikasikan_pada')
                ->constrained('pengguna')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        DB::table('ujian_cbt')
            ->where('alur', 'terpusat')
            ->where('tampilkan_hasil', true)
            ->update([
                'hasil_difinalisasi_pada' => DB::raw('updated_at'),
                'hasil_dipublikasikan_pada' => DB::raw('updated_at'),
            ]);
    }

    public function down(): void
    {
        Schema::table('ujian_cbt', function (Blueprint $table) {
            $table->dropForeign(['hasil_difinalisasi_oleh_pengguna_id']);
            $table->dropForeign(['hasil_dipublikasikan_oleh_pengguna_id']);
            $table->dropColumn([
                'hasil_difinalisasi_pada',
                'hasil_difinalisasi_oleh_pengguna_id',
                'hasil_dipublikasikan_pada',
                'hasil_dipublikasikan_oleh_pengguna_id',
            ]);
        });
    }
};
