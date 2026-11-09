<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengaturan_poin_keterlambatan', function (Blueprint $table) {
            $table->foreignId('pelanggaran_hadir_manual_id')->nullable()->constrained('jenis_pelanggaran_siswa')->restrictOnDelete();
        });
        Schema::table('absensi_siswa', fn (Blueprint $table) => $table->json('aturan_poin_hadir_manual')->nullable());
        if (! DB::table('pengaturan_poin_keterlambatan')->exists() && ! DB::table('absensi_siswa')->where('sanksi_manual_menit', '>', 0)->exists()) {
            return;
        }
        $kategori = DB::table('kategori_pembinaan_siswa')->where('kode', 'KEHADIRAN')->value('id')
            ?? DB::table('kategori_pembinaan_siswa')->insertGetId(['kode' => 'KEHADIRAN', 'nama' => 'Kehadiran', 'aktif' => true, 'created_at' => now(), 'updated_at' => now()]);
        $id = DB::table('jenis_pelanggaran_siswa')->where('kode', 'PRESENSI_MANUAL')->value('id')
            ?? DB::table('jenis_pelanggaran_siswa')->insertGetId([
                'kategori_pembinaan_siswa_id' => $kategori, 'kode' => 'PRESENSI_MANUAL',
                'nama' => 'Tidak melakukan scan kartu pelajar (presensi manual)', 'tingkat' => 'ringan',
                'poin' => 15, 'urutan' => 999, 'aktif' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        DB::table('pengaturan_poin_keterlambatan')->update(['pelanggaran_hadir_manual_id' => $id]);
        $default = (array) DB::table('jenis_pelanggaran_siswa')->find($id);
        $poinLama = DB::table('pengaturan_poin_keterlambatan')->pluck('poin_terlambat', 'tahun_pelajaran_id');
        DB::table('absensi_siswa')->where('sanksi_manual_menit', '>', 0)->orderBy('id')->chunkById(100, function ($rows) use ($default, $poinLama) {
            foreach ($rows as $row) {
                $butir = DB::table('butir_pelanggaran_laporan as b')->join('laporan_pembinaan_siswa as l', 'l.id', '=', 'b.laporan_pembinaan_siswa_id')
                    ->where('l.absensi_siswa_id', $row->id)->orderBy('b.id')->select('b.*', 'l.kategori_pembinaan_siswa_id as kategori_laporan')->first();
                $snapshot = [
                    'id' => $butir?->jenis_pelanggaran_siswa_id ?? $default['id'],
                    'kode' => $butir?->kode_pelanggaran ?? $default['kode'],
                    'nama' => $butir?->nama_pelanggaran ?? $default['nama'],
                    'tingkat' => $butir?->tingkat ?? $default['tingkat'],
                    'kategori_pembinaan_siswa_id' => $butir ? $butir->kategori_laporan : $default['kategori_pembinaan_siswa_id'],
                    'poin' => $row->poin_keterlambatan_terhitung > 0 ? (int) $row->poin_keterlambatan_terhitung
                        : ($butir ? (int) $butir->poin : ($row->poin_keterlambatan_diproses_pada ? 0 : (int) ($poinLama[$row->tahun_pelajaran_id] ?? 15))),
                    'sumber' => 'riwayat_sebelum_pemisahan', 'diambil_pada' => now()->toISOString(),
                ];
                DB::table('absensi_siswa')->where('id', $row->id)->update(['aturan_poin_hadir_manual' => json_encode($snapshot)]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('absensi_siswa', fn (Blueprint $table) => $table->dropColumn('aturan_poin_hadir_manual'));
        Schema::table('pengaturan_poin_keterlambatan', fn (Blueprint $table) => $table->dropConstrainedForeignId('pelanggaran_hadir_manual_id'));
        // Keep master rules and all point/history transactions intact.
    }
};
