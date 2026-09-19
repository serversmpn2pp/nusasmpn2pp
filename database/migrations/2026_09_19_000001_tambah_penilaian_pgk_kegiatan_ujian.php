<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kegiatan_ujian_cbt', function (Blueprint $table) {
            $table->string('penilaian_pgk', 20)->default('dikotomi');
            $table->timestamp('penilaian_pgk_dikunci_pada')->nullable();
        });
        DB::table('kegiatan_ujian_cbt')->whereExists(function ($query) {
            $query->selectRaw('1')->from('jadwal_ujian_cbt')
                ->join('peserta_ujian_cbt', 'peserta_ujian_cbt.ujian_cbt_id', '=', 'jadwal_ujian_cbt.ujian_cbt_id')
                ->whereColumn('jadwal_ujian_cbt.kegiatan_ujian_cbt_id', 'kegiatan_ujian_cbt.id')
                ->where(function ($query) {
                    $query->whereNotNull('peserta_ujian_cbt.waktu_mulai')
                        ->orWhereIn('peserta_ujian_cbt.status', ['sedang_mengerjakan', 'selesai']);
                });
        })->update(['penilaian_pgk_dikunci_pada' => now()]);
    }

    public function down(): void
    {
        Schema::table('kegiatan_ujian_cbt', fn (Blueprint $table) => $table->dropColumn(['penilaian_pgk', 'penilaian_pgk_dikunci_pada']));
    }
};
