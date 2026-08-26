<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bukti_ruang_ujian_cbt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ruang_ujian_cbt_id')
                ->constrained('ruang_ujian_cbt')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('jenis', 30);
            $table->string('lokasi_file');
            $table->string('nama_file_asli');
            $table->string('tipe_file', 120)->nullable();
            $table->unsignedBigInteger('ukuran_file')->nullable();
            $table->foreignId('diunggah_oleh_pengguna_id')
                ->nullable()
                ->constrained('pengguna')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamp('diunggah_pada');
            $table->timestamps();

            $table->index(['ruang_ujian_cbt_id', 'jenis'], 'bukti_ruang_ujian_jenis');
        });

        Schema::table('ruang_ujian_cbt', function (Blueprint $table) {
            $table->string('status_bukti', 30)->default('belum_diunggah')->after('bukti_berita_acara_diunggah_oleh_pengguna_id');
            $table->timestamp('bukti_diajukan_pada')->nullable()->after('status_bukti');
            $table->foreignId('bukti_diajukan_oleh_pengguna_id')
                ->nullable()
                ->after('bukti_diajukan_pada')
                ->constrained('pengguna')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->text('catatan_pemeriksaan_bukti')->nullable()->after('bukti_diajukan_oleh_pengguna_id');
            $table->timestamp('bukti_diperiksa_pada')->nullable()->after('catatan_pemeriksaan_bukti');
            $table->foreignId('bukti_diperiksa_oleh_pengguna_id')
                ->nullable()
                ->after('bukti_diperiksa_pada')
                ->constrained('pengguna')
                ->nullOnDelete()
                ->cascadeOnUpdate();
        });

        $sekarang = now();

        DB::table('ruang_ujian_cbt')
            ->orderBy('id')
            ->chunkById(100, function ($daftarRuang) use ($sekarang) {
                foreach ($daftarRuang as $ruang) {
                    $jumlahJenis = 0;

                    foreach (['daftar_hadir', 'berita_acara'] as $jenis) {
                        $prefix = "bukti_{$jenis}";
                        $lokasiFile = $ruang->{$prefix.'_lokasi_file'} ?? null;

                        if (! $lokasiFile) {
                            continue;
                        }

                        DB::table('bukti_ruang_ujian_cbt')->insert([
                            'ruang_ujian_cbt_id' => $ruang->id,
                            'jenis' => $jenis,
                            'lokasi_file' => $lokasiFile,
                            'nama_file_asli' => $ruang->{$prefix.'_nama_file_asli'} ?: basename($lokasiFile),
                            'tipe_file' => $ruang->{$prefix.'_tipe_file'} ?? null,
                            'ukuran_file' => $ruang->{$prefix.'_ukuran_file'} ?? null,
                            'diunggah_oleh_pengguna_id' => $ruang->{$prefix.'_diunggah_oleh_pengguna_id'} ?? null,
                            'diunggah_pada' => $ruang->{$prefix.'_diunggah_pada'} ?? $sekarang,
                            'created_at' => $sekarang,
                            'updated_at' => $sekarang,
                        ]);
                        $jumlahJenis++;
                    }

                    DB::table('ruang_ujian_cbt')
                        ->where('id', $ruang->id)
                        ->update([
                            'status_bukti' => match ($jumlahJenis) {
                                2 => 'siap_dikirim',
                                1 => 'sebagian',
                                default => 'belum_diunggah',
                            },
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('bukti_ruang_ujian_cbt');

        Schema::table('ruang_ujian_cbt', function (Blueprint $table) {
            $table->dropForeign(['bukti_diajukan_oleh_pengguna_id']);
            $table->dropForeign(['bukti_diperiksa_oleh_pengguna_id']);
            $table->dropColumn([
                'status_bukti',
                'bukti_diajukan_pada',
                'bukti_diajukan_oleh_pengguna_id',
                'catatan_pemeriksaan_bukti',
                'bukti_diperiksa_pada',
                'bukti_diperiksa_oleh_pengguna_id',
            ]);
        });
    }
};
