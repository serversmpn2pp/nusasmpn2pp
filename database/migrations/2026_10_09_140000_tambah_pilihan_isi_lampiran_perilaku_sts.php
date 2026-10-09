<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rapor_sts_kelas', function (Blueprint $table) {
            $table->string('isi_lampiran_perilaku', 30)->default('semua_terverifikasi');
            $table->unsignedInteger('versi_isi_perilaku')->default(0);
            $table->timestamp('isi_perilaku_diubah_pada')->nullable();
            $table->foreignId('isi_perilaku_diubah_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('rapor_sts_kelas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('isi_perilaku_diubah_oleh_pengguna_id');
            $table->dropColumn(['isi_lampiran_perilaku', 'versi_isi_perilaku', 'isi_perilaku_diubah_pada']);
        });
    }
};
