<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jawaban_peserta_ujian_cbt', function (Blueprint $table) {
            $table->string('lokasi_file')->nullable()->after('jawaban');
            $table->string('nama_file_asli')->nullable()->after('lokasi_file');
            $table->string('tipe_file', 120)->nullable()->after('nama_file_asli');
            $table->unsignedBigInteger('ukuran_file')->nullable()->after('tipe_file');
        });
    }

    public function down(): void
    {
        Schema::table('jawaban_peserta_ujian_cbt', function (Blueprint $table) {
            $table->dropColumn([
                'lokasi_file',
                'nama_file_asli',
                'tipe_file',
                'ukuran_file',
            ]);
        });
    }
};
