<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('ujian_cbt')->where('alur', 'kelas')
            ->whereIn('status', ['draft', 'terjadwal', 'berlangsung'])
            ->orderBy('id')->chunkById(100, function ($ujian): void {
                foreach ($ujian as $item) {
                    DB::transaction(function () use ($item): void {
                        $ujian = DB::table('ujian_cbt')->where('id', $item->id)->lockForUpdate()->first();
                        if (! $ujian || ! in_array($ujian->status, ['draft', 'terjadwal', 'berlangsung'], true)) {
                            return;
                        }
                        DB::table('ujian_cbt')->where('id', $ujian->id)->update([
                            'deteksi_pindah_tab' => true,
                            'tindakan_pindah_aplikasi' => 'tahan',
                            'batas_pindah_aplikasi' => 3,
                        ]);
                        DB::table('peserta_ujian_cbt')->where('ujian_cbt_id', $ujian->id)
                            ->where('status', 'sedang_mengerjakan')->where('jumlah_pindah_aplikasi', '>=', 3)
                            ->orderBy('id')->lockForUpdate()->get()->each(function ($peserta): void {
                                $aktivitas = DB::table('aktivitas_keamanan_ujian_cbt')->where('peserta_ujian_cbt_id', $peserta->id);
                                $dibuka = (int) (clone $aktivitas)->where('jenis', 'buka_mode_aman')->max('id');
                                $kejadian = (int) (clone $aktivitas)->where('jenis', 'keluar_aplikasi')->where('dihitung', true)->max('id');
                                if ($dibuka > 0 && $kejadian <= $dibuka) {
                                    return; // Preserve a supervisor's decision covering the existing incidents.
                                }
                                $waktu = now();
                                DB::table('peserta_ujian_cbt')->where('id', $peserta->id)->update([
                                    'status' => 'terblokir', 'ditahan_mode_aman_pada' => $waktu,
                                ]);
                                DB::table('aktivitas_keamanan_ujian_cbt')->insert([
                                    'peserta_ujian_cbt_id' => $peserta->id,
                                    'jenis' => 'kebijakan_diperketat', 'mulai_pada' => $waktu, 'selesai_pada' => $waktu,
                                    'dihitung' => false, 'catatan' => 'Kebijakan asesmen diperketat: batas tiga kejadian sudah tercapai. Jawaban dan waktu ujian dipertahankan.',
                                    'metadata' => json_encode(['menahan_sesi' => true, 'alasan_tahanan' => 'batas_kejadian',
                                        'jumlah_kejadian' => (int) $peserta->jumlah_pindah_aplikasi, 'pemicu' => 'migrasi-kebijakan-asesmen']),
                                    'created_at' => $waktu, 'updated_at' => $waktu,
                                ]);
                            });
                    });
                }
            });
    }

    public function down(): void
    {
        // Do not reopen held sessions or erase security history during rollback.
    }
};
