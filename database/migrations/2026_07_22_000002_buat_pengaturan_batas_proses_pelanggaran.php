<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan_batas_proses_pelanggaran', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_pelajaran_id')->unique()->constrained('tahun_pelajaran')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedSmallInteger('batas_hari_pemeriksaan_bk')->default(2);
            $table->unsignedSmallInteger('batas_hari_persetujuan')->default(2);
            $table->unsignedSmallInteger('batas_hari_musyawarah')->default(3);
            $table->unsignedSmallInteger('pengingat_hari_sebelum_batas')->default(1);
            $table->boolean('notifikasi_pengingat_aktif')->default(true);
            $table->boolean('notifikasi_terlambat_aktif')->default(true);
            $table->foreignId('diperbarui_oleh_pengguna_id')->nullable()->constrained('pengguna')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('laporan_pembinaan_siswa', function (Blueprint $table) {
            $table->string('tahap_batas_proses', 30)->nullable()->after('status_verifikasi');
            $table->timestamp('batas_proses_pada')->nullable()->after('tahap_batas_proses');
            $table->index(['status_verifikasi', 'batas_proses_pada'], 'laporan_pembinaan_status_batas');
        });

        $waktu = now();
        $tahunIds = DB::table('tahun_pelajaran')->pluck('id');
        foreach ($tahunIds as $tahunId) {
            DB::table('pengaturan_batas_proses_pelanggaran')->insert([
                'tahun_pelajaran_id' => $tahunId,
                'batas_hari_pemeriksaan_bk' => 2,
                'batas_hari_persetujuan' => 2,
                'batas_hari_musyawarah' => 3,
                'pengingat_hari_sebelum_batas' => 1,
                'notifikasi_pengingat_aktif' => true,
                'notifikasi_terlambat_aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ]);
        }

        DB::table('laporan_pembinaan_siswa')
            ->where('jenis_laporan', 'pelanggaran')
            ->whereNotIn('status_verifikasi', ['disahkan', 'tidak_terbukti', 'dibatalkan'])
            ->orderBy('id')
            ->chunkById(200, function ($laporan) {
                foreach ($laporan as $item) {
                    [$tahap, $hari] = match (true) {
                        in_array($item->status_verifikasi, ['diajukan', 'pemeriksaan_bk', 'perlu_klarifikasi'], true) => ['pemeriksaan_bk', 2],
                        in_array($item->status_verifikasi, ['menunggu_persetujuan', 'disetujui_sebagian'], true) => ['persetujuan', 2],
                        $item->status_verifikasi === 'perlu_musyawarah' => ['musyawarah', 3],
                        default => [null, 0],
                    };

                    if (! $tahap) {
                        continue;
                    }

                    $acuan = CarbonImmutable::parse($item->updated_at ?? $item->created_at ?? now());
                    DB::table('laporan_pembinaan_siswa')->where('id', $item->id)->update([
                        'tahap_batas_proses' => $tahap,
                        'batas_proses_pada' => $acuan->addDays($hari),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('laporan_pembinaan_siswa', function (Blueprint $table) {
            $table->dropIndex('laporan_pembinaan_status_batas');
            $table->dropColumn(['tahap_batas_proses', 'batas_proses_pada']);
        });

        Schema::dropIfExists('pengaturan_batas_proses_pelanggaran');
    }
};
