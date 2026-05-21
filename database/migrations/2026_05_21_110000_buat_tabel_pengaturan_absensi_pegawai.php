<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menjalankan migration.
     */
    public function up(): void
    {
        Schema::create('pengaturan_absensi_pegawai', function (Blueprint $table) {
            $table->id();
            $table->string('nama_jadwal', 120);
            $table->string('cakupan', 30)->default('semua');
            $table->string('jenis_pegawai', 100)->nullable();
            $table->foreignId('pegawai_id')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->string('hari', 20);
            $table->unsignedTinyInteger('urutan_hari')->default(1);
            $table->time('jam_scan_masuk_mulai');
            $table->time('jam_masuk');
            $table->time('jam_scan_masuk_selesai');
            $table->time('jam_scan_pulang_mulai');
            $table->time('jam_pulang');
            $table->time('jam_scan_pulang_selesai');
            $table->boolean('aktif')->default(true);
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index(['aktif', 'urutan_hari']);
            $table->index(['cakupan', 'hari']);
            $table->index(['jenis_pegawai', 'hari']);
            $table->index(['pegawai_id', 'hari']);
        });
    }

    /**
     * Membatalkan migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengaturan_absensi_pegawai');
    }
};
