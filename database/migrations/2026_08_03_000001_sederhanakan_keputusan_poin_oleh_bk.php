<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('laporan_pembinaan_siswa')
            ->whereIn('status_verifikasi', [
                'menunggu_persetujuan',
                'disetujui_sebagian',
                'perlu_musyawarah',
            ])
            ->update([
                'status_verifikasi' => 'diajukan',
                'tahap_batas_proses' => 'pemeriksaan_bk',
                'batas_proses_pada' => null,
            ]);

        DB::table('izin')->where('kode', 'poin_siswa.menyetujui')->delete();
    }

    public function down(): void
    {
        // Status lama tidak dapat dipulihkan secara akurat setelah keputusan BK diterapkan.
    }
};
