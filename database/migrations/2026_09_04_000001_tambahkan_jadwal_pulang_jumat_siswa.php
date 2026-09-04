<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengaturan_absensi', function (Blueprint $table) {
            $table->boolean('pulang_jumat_dibedakan')->default(false);
            $table->time('jam_scan_pulang_perempuan_mulai')->nullable();
            $table->time('jam_pulang_perempuan')->nullable();
            $table->time('jam_scan_pulang_perempuan_selesai')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pengaturan_absensi', function (Blueprint $table) {
            $table->dropColumn([
                'pulang_jumat_dibedakan',
                'jam_scan_pulang_perempuan_mulai',
                'jam_pulang_perempuan',
                'jam_scan_pulang_perempuan_selesai',
            ]);
        });
    }
};
