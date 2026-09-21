<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('peserta_ujian_cbt', function (Blueprint $table) {
            $table->string('status_susulan', 30)->nullable()->after('status_kehadiran_ujian')->index();
            $table->dateTime('susulan_mulai')->nullable()->after('status_susulan');
            $table->dateTime('susulan_selesai')->nullable()->after('susulan_mulai');
            $table->string('token_susulan', 20)->nullable()->after('susulan_selesai');
            $table->string('ruang_susulan', 120)->nullable()->after('token_susulan');
            $table->foreignId('pengawas_susulan_pegawai_id')
                ->nullable()
                ->after('ruang_susulan')
                ->constrained('pegawai')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->text('catatan_susulan')->nullable()->after('pengawas_susulan_pegawai_id');
            $table->timestamp('susulan_ditetapkan_pada')->nullable()->after('catatan_susulan');
            $table->foreignId('susulan_ditetapkan_oleh_pengguna_id')
                ->nullable()
                ->after('susulan_ditetapkan_pada')
                ->constrained('pengguna')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->index(['ujian_cbt_id', 'status_susulan'], 'peserta_cbt_status_susulan_index');
            $table->index(['susulan_mulai', 'susulan_selesai'], 'peserta_cbt_waktu_susulan_index');
        });
    }

    public function down(): void
    {
        Schema::table('peserta_ujian_cbt', function (Blueprint $table) {
            $table->dropIndex('peserta_cbt_status_susulan_index');
            $table->dropIndex('peserta_cbt_waktu_susulan_index');
            $table->dropForeign(['pengawas_susulan_pegawai_id']);
            $table->dropForeign(['susulan_ditetapkan_oleh_pengguna_id']);
            $table->dropColumn([
                'status_susulan',
                'susulan_mulai',
                'susulan_selesai',
                'token_susulan',
                'ruang_susulan',
                'pengawas_susulan_pegawai_id',
                'catatan_susulan',
                'susulan_ditetapkan_pada',
                'susulan_ditetapkan_oleh_pengguna_id',
            ]);
        });
    }
};
