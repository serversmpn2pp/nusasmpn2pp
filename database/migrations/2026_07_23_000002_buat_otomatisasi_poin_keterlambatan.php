<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pengaturan_poin_keterlambatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_pelajaran_id')->unique()->constrained('tahun_pelajaran')->cascadeOnUpdate()->cascadeOnDelete();
            $table->boolean('aktif')->default(false);
            $table->foreignId('diperbarui_oleh_pengguna_id')->nullable()->constrained('pengguna')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('rentang_poin_keterlambatan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengaturan_poin_keterlambatan_id')->constrained('pengaturan_poin_keterlambatan')->cascadeOnUpdate()->cascadeOnDelete();
            $table->unsignedSmallInteger('menit_mulai');
            $table->unsignedSmallInteger('menit_selesai')->nullable();
            $table->unsignedSmallInteger('poin')->default(0);
            $table->unsignedSmallInteger('urutan')->default(0);
            $table->timestamps();

            $table->unique(['pengaturan_poin_keterlambatan_id', 'menit_mulai'], 'rentang_poin_mulai_unik');
            $table->index(['pengaturan_poin_keterlambatan_id', 'urutan'], 'rentang_poin_pengaturan_urutan');
        });

        Schema::table('absensi_siswa', function (Blueprint $table) {
            $table->string('status_poin_keterlambatan', 40)->nullable()->after('menit_terlambat');
            $table->unsignedSmallInteger('poin_keterlambatan_terhitung')->default(0)->after('status_poin_keterlambatan');
            $table->timestamp('poin_keterlambatan_diproses_pada')->nullable()->after('poin_keterlambatan_terhitung');
        });

        Schema::table('laporan_pembinaan_siswa', function (Blueprint $table) {
            $table->string('sumber_laporan', 40)->default('manual')->after('jenis_laporan');
            $table->foreignId('absensi_siswa_id')->nullable()->after('anggota_kelas_id')->constrained('absensi_siswa')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('rentang_poin_keterlambatan_id')->nullable()->after('absensi_siswa_id')->constrained('rentang_poin_keterlambatan')->cascadeOnUpdate()->nullOnDelete();
            $table->unsignedSmallInteger('menit_terlambat_tercatat')->nullable()->after('rentang_poin_keterlambatan_id');
            $table->timestamp('diproses_otomatis_pada')->nullable()->after('menit_terlambat_tercatat');

            $table->index(['sumber_laporan', 'tanggal_kejadian'], 'laporan_pembinaan_sumber_tanggal');
        });

        DB::statement("CREATE UNIQUE INDEX laporan_pembinaan_absensi_aktif_unik ON laporan_pembinaan_siswa (absensi_siswa_id) WHERE absensi_siswa_id IS NOT NULL AND status_verifikasi <> 'dibatalkan'");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS laporan_pembinaan_absensi_aktif_unik');

        Schema::table('laporan_pembinaan_siswa', function (Blueprint $table) {
            $table->dropIndex('laporan_pembinaan_sumber_tanggal');
            $table->dropConstrainedForeignId('rentang_poin_keterlambatan_id');
            $table->dropConstrainedForeignId('absensi_siswa_id');
            $table->dropColumn(['sumber_laporan', 'menit_terlambat_tercatat', 'diproses_otomatis_pada']);
        });

        Schema::table('absensi_siswa', function (Blueprint $table) {
            $table->dropColumn(['status_poin_keterlambatan', 'poin_keterlambatan_terhitung', 'poin_keterlambatan_diproses_pada']);
        });

        Schema::dropIfExists('rentang_poin_keterlambatan');
        Schema::dropIfExists('pengaturan_poin_keterlambatan');
    }
};
