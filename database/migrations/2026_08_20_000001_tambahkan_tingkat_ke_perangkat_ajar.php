<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('perangkat_ajar', function (Blueprint $table) {
            $table->unsignedSmallInteger('tingkat')->nullable()->after('mata_pelajaran_id');
        });

        DB::table('perangkat_ajar')
            ->orderBy('id')
            ->each(function ($perangkatAjar) {
                $daftarTingkat = DB::table('guru_mata_pelajaran')
                    ->join('kelas', 'kelas.id', '=', 'guru_mata_pelajaran.kelas_id')
                    ->where('guru_mata_pelajaran.pegawai_id', $perangkatAjar->pegawai_id)
                    ->where('guru_mata_pelajaran.tahun_pelajaran_id', $perangkatAjar->tahun_pelajaran_id)
                    ->where('guru_mata_pelajaran.mata_pelajaran_id', $perangkatAjar->mata_pelajaran_id)
                    ->where('guru_mata_pelajaran.aktif', true)
                    ->whereIn('kelas.tingkat', [7, 8, 9])
                    ->distinct()
                    ->pluck('kelas.tingkat');

                if ($daftarTingkat->count() === 1) {
                    DB::table('perangkat_ajar')
                        ->where('id', $perangkatAjar->id)
                        ->update(['tingkat' => (int) $daftarTingkat->first()]);
                }
            });

        Schema::table('perangkat_ajar', function (Blueprint $table) {
            $table->dropUnique('perangkat_ajar_unik_guru_periode_mapel_jenis');
            $table->unique(
                ['pegawai_id', 'tahun_pelajaran_id', 'semester', 'mata_pelajaran_id', 'tingkat', 'jenis_perangkat_ajar_id'],
                'perangkat_ajar_unik_guru_periode_mapel_tingkat_jenis',
            );
            $table->index(
                ['tahun_pelajaran_id', 'semester', 'tingkat', 'status'],
                'perangkat_ajar_periode_tingkat_status',
            );
        });
    }

    public function down(): void
    {
        Schema::table('perangkat_ajar', function (Blueprint $table) {
            $table->dropIndex('perangkat_ajar_periode_tingkat_status');
            $table->dropUnique('perangkat_ajar_unik_guru_periode_mapel_tingkat_jenis');
            $table->dropColumn('tingkat');
            $table->unique(
                ['pegawai_id', 'tahun_pelajaran_id', 'semester', 'mata_pelajaran_id', 'jenis_perangkat_ajar_id'],
                'perangkat_ajar_unik_guru_periode_mapel_jenis',
            );
        });
    }
};
