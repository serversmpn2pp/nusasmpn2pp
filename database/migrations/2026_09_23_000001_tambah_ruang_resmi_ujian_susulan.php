<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('peserta_ujian_cbt', function (Blueprint $table) {
            $table->foreignId('ruang_susulan_kegiatan_ujian_cbt_id')
                ->nullable()
                ->after('ruang_susulan')
                ->constrained('ruang_kegiatan_ujian_cbt')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        DB::table('peserta_ujian_cbt')
            ->whereNotNull('ruang_susulan')
            ->whereNull('ruang_susulan_kegiatan_ujian_cbt_id')
            ->orderBy('id')
            ->eachById(function ($peserta) {
                $kegiatanId = DB::table('jadwal_ujian_cbt')
                    ->where('ujian_cbt_id', $peserta->ujian_cbt_id)
                    ->value('kegiatan_ujian_cbt_id');

                if (! $kegiatanId) {
                    return;
                }

                $ruangId = DB::table('ruang_kegiatan_ujian_cbt')
                    ->where('kegiatan_ujian_cbt_id', $kegiatanId)
                    ->where(function ($query) use ($peserta) {
                        $query->where('nama', $peserta->ruang_susulan)
                            ->orWhere('kode', $peserta->ruang_susulan);
                    })
                    ->value('id');

                if ($ruangId) {
                    DB::table('peserta_ujian_cbt')
                        ->where('id', $peserta->id)
                        ->update(['ruang_susulan_kegiatan_ujian_cbt_id' => $ruangId]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('peserta_ujian_cbt', function (Blueprint $table) {
            $table->dropForeign(['ruang_susulan_kegiatan_ujian_cbt_id']);
            $table->dropColumn('ruang_susulan_kegiatan_ujian_cbt_id');
        });
    }
};
