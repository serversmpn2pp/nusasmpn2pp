<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bukti_laporan_pembinaan_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_pembinaan_siswa_id')->constrained('laporan_pembinaan_siswa')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('jenis', 20);
            $table->string('nama_file_asli');
            $table->string('lokasi_file');
            $table->string('tipe_file', 120)->nullable();
            $table->unsignedBigInteger('ukuran_file')->default(0);
            $table->text('keterangan')->nullable();
            $table->foreignId('diunggah_oleh_pengguna_id')->nullable()->constrained('pengguna')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('diunggah_pada');
            $table->timestamps();

            $table->index(['laporan_pembinaan_siswa_id', 'diunggah_pada'], 'bukti_pembinaan_laporan_tanggal');
        });

        Schema::create('saksi_laporan_pembinaan_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_pembinaan_siswa_id')->constrained('laporan_pembinaan_siswa')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('jenis_saksi', 20);
            $table->foreignId('siswa_id')->nullable()->constrained('siswa')->cascadeOnUpdate()->nullOnDelete();
            $table->foreignId('pegawai_id')->nullable()->constrained('pegawai')->cascadeOnUpdate()->nullOnDelete();
            $table->string('nama_saksi', 160);
            $table->text('pernyataan');
            $table->foreignId('dibuat_oleh_pengguna_id')->nullable()->constrained('pengguna')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();

            $table->index(['laporan_pembinaan_siswa_id', 'jenis_saksi'], 'saksi_pembinaan_laporan_jenis');
        });

        Schema::create('klarifikasi_siswa_pembinaan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_pembinaan_siswa_id')->constrained('laporan_pembinaan_siswa')->cascadeOnUpdate()->cascadeOnDelete();
            $table->text('isi_klarifikasi');
            $table->string('metode', 20);
            $table->string('pendamping', 160)->nullable();
            $table->timestamp('disampaikan_pada');
            $table->foreignId('dicatat_oleh_pengguna_id')->nullable()->constrained('pengguna')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();

            $table->index(['laporan_pembinaan_siswa_id', 'disampaikan_pada'], 'klarifikasi_pembinaan_laporan_tanggal');
        });

        Schema::create('riwayat_proses_pembinaan_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_pembinaan_siswa_id')->constrained('laporan_pembinaan_siswa')->cascadeOnUpdate()->cascadeOnDelete();
            $table->string('kode_kegiatan', 60);
            $table->string('judul', 160);
            $table->text('keterangan')->nullable();
            $table->string('status_sebelum', 40)->nullable();
            $table->string('status_sesudah', 40)->nullable();
            $table->foreignId('pengguna_id')->nullable()->constrained('pengguna')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamp('terjadi_pada');
            $table->json('data')->nullable();
            $table->timestamps();

            $table->index(['laporan_pembinaan_siswa_id', 'terjadi_pada'], 'riwayat_pembinaan_laporan_tanggal');
        });

        DB::table('laporan_pembinaan_siswa')
            ->orderBy('id')
            ->chunkById(200, function ($laporan) {
                $baris = $laporan->map(fn ($item) => [
                    'laporan_pembinaan_siswa_id' => $item->id,
                    'kode_kegiatan' => 'laporan_dibuat',
                    'judul' => 'Laporan dibuat',
                    'keterangan' => 'Riwayat awal dibuat dari laporan yang sudah ada.',
                    'status_sebelum' => null,
                    'status_sesudah' => $item->status_verifikasi,
                    'pengguna_id' => $item->dibuat_oleh_pengguna_id,
                    'terjadi_pada' => $item->created_at ?? now(),
                    'data' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])->all();

                if ($baris !== []) {
                    DB::table('riwayat_proses_pembinaan_siswa')->insert($baris);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_proses_pembinaan_siswa');
        Schema::dropIfExists('klarifikasi_siswa_pembinaan');
        Schema::dropIfExists('saksi_laporan_pembinaan_siswa');
        Schema::dropIfExists('bukti_laporan_pembinaan_siswa');
    }
};
