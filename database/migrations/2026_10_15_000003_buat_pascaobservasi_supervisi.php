<?php

use App\Services\Supervisi\InstrumenSupervisiAwal;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('versi_instrumen_supervisi', function (Blueprint $t) {
            $t->json('pertanyaan_refleksi')->nullable();
        });
        DB::table('versi_instrumen_supervisi')->whereNull('pertanyaan_refleksi')->update([
            'pertanyaan_refleksi' => json_encode(InstrumenSupervisiAwal::pertanyaanRefleksi(), JSON_THROW_ON_ERROR),
        ]);
        Schema::create('pascaobservasi_supervisi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('sesi_supervisi_id')->unique()->constrained('sesi_supervisi')->restrictOnDelete();
            $t->unsignedInteger('penilaian_versi');
            $t->unsignedInteger('versi')->default(1);
            $t->string('status', 20)->default('draf');
            $t->json('pertanyaan');
            $t->json('jawaban');
            $t->text('catatan_supervisor')->nullable();
            $t->text('catatan_penutup')->nullable();
            $t->timestamp('dikirim_pada')->nullable();
            $t->timestamp('diterima_pada')->nullable();
            $t->timestamp('selesai_pada')->nullable();
            $t->foreignId('pengubah_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('tindak_lanjut_supervisi', function (Blueprint $t) {
            $t->id();
            $t->uuid('token_pembuatan')->unique();
            $t->string('sidik_pembuatan', 64);
            $t->foreignId('pembuat_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $t->foreignId('sesi_supervisi_id')->constrained('sesi_supervisi')->restrictOnDelete();
            $t->unsignedInteger('penilaian_versi');
            $t->unsignedInteger('refleksi_versi');
            $t->unsignedInteger('versi')->default(1);
            $t->string('status', 20)->default('draf');
            $t->text('temuan');
            $t->text('rencana_perbaikan')->nullable();
            $t->text('bentuk_pendampingan')->nullable();
            $t->text('indikator_keberhasilan')->nullable();
            $t->date('tenggat')->nullable();
            $t->date('tanggal_pelaksanaan')->nullable();
            $t->text('laporan_pelaksanaan')->nullable();
            $t->text('hasil_pelaksanaan')->nullable();
            $t->text('catatan_supervisor')->nullable();
            $t->timestamp('diterbitkan_pada')->nullable();
            $t->timestamp('diajukan_pada')->nullable();
            $t->timestamp('selesai_pada')->nullable();
            $t->foreignId('pengubah_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $t->timestamps();
            $t->index(['sesi_supervisi_id', 'penilaian_versi']);
        });
        Schema::create('lampiran_tindak_lanjut_supervisi', function (Blueprint $t) {
            $t->id();
            $t->foreignId('tindak_lanjut_supervisi_id')->constrained('tindak_lanjut_supervisi')->restrictOnDelete();
            $t->string('lokasi_file');
            $t->string('nama_file_asli', 200);
            $t->string('tipe_file', 150);
            $t->unsignedBigInteger('ukuran_file');
            $t->foreignId('pengunggah_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lampiran_tindak_lanjut_supervisi');
        Schema::dropIfExists('tindak_lanjut_supervisi');
        Schema::dropIfExists('pascaobservasi_supervisi');
        Schema::table('versi_instrumen_supervisi', fn (Blueprint $t) => $t->dropColumn('pertanyaan_refleksi'));
    }
};
