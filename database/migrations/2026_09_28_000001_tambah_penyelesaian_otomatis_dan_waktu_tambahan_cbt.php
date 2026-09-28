<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('peserta_ujian_cbt', function (Blueprint $table) {
            $table->dateTime('waktu_tambahan_sampai')->nullable()->after('menit_tersisa')->index();
            $table->dateTime('selesai_otomatis_pada')->nullable()->after('waktu_tambahan_sampai');
            $table->string('cara_selesai', 30)->nullable()->after('selesai_otomatis_pada')->index();
            $table->text('alasan_waktu_tambahan')->nullable()->after('cara_selesai');
            $table->dateTime('waktu_tambahan_diberikan_pada')->nullable()->after('alasan_waktu_tambahan');
            $table->foreignId('waktu_tambahan_oleh_pengguna_id')
                ->nullable()
                ->after('waktu_tambahan_diberikan_pada')
                ->constrained('pengguna')
                ->nullOnDelete()
                ->cascadeOnUpdate();

            $table->index(['status', 'waktu_mulai'], 'peserta_cbt_status_waktu_mulai_index');
        });

        Schema::create('riwayat_waktu_tambahan_ujian_cbt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('peserta_ujian_cbt_id')
                ->constrained('peserta_ujian_cbt')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->unsignedSmallInteger('menit_tambahan');
            $table->dateTime('mulai_pada');
            $table->dateTime('selesai_pada');
            $table->text('alasan');
            $table->foreignId('diberikan_oleh_pengguna_id')
                ->nullable()
                ->constrained('pengguna')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();

            $table->index(['peserta_ujian_cbt_id', 'selesai_pada'], 'riwayat_waktu_tambahan_peserta_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_waktu_tambahan_ujian_cbt');

        Schema::table('peserta_ujian_cbt', function (Blueprint $table) {
            $table->dropIndex('peserta_cbt_status_waktu_mulai_index');
            $table->dropIndex(['waktu_tambahan_sampai']);
            $table->dropIndex(['cara_selesai']);
            $table->dropForeign(['waktu_tambahan_oleh_pengguna_id']);
            $table->dropColumn([
                'waktu_tambahan_sampai',
                'selesai_otomatis_pada',
                'cara_selesai',
                'alasan_waktu_tambahan',
                'waktu_tambahan_diberikan_pada',
                'waktu_tambahan_oleh_pengguna_id',
            ]);
        });
    }
};
