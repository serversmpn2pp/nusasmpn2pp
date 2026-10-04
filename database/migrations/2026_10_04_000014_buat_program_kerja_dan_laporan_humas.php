<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('program_kerja_humas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_pelajaran_id')->constrained('tahun_pelajaran')->restrictOnDelete();
            $table->uuid('token_pembuatan')->unique();
            $table->string('semester', 12);
            $table->string('bidang', 30);
            $table->string('nama', 180);
            $table->text('tujuan');
            $table->text('sasaran');
            $table->text('target_hasil');
            $table->unsignedInteger('target_kegiatan')->default(1);
            $table->string('penanggung_jawab', 180);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->index();
            $table->string('status', 20)->default('rencana');
            $table->text('evaluasi')->nullable();
            $table->unsignedInteger('versi')->default(0);
            $table->foreignId('dibuat_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->foreignId('diubah_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamps();
            $table->index(['tahun_pelajaran_id', 'semester', 'status'], 'program_humas_periode_status');
        });
        Schema::create('laporan_pelaksanaan_humas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_kerja_humas_id')->constrained('program_kerja_humas')->restrictOnDelete();
            $table->uuid('token_pembuatan')->unique();
            $table->foreignId('agenda_humas_id')->nullable()->constrained('agenda_humas')->restrictOnDelete();
            $table->string('judul', 180);
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->string('tempat', 180);
            $table->string('pelaksana', 180);
            $table->unsignedInteger('jumlah_peserta');
            $table->text('uraian');
            $table->text('hasil');
            $table->text('kendala')->nullable();
            $table->text('tindak_lanjut')->nullable();
            $table->string('status', 20)->default('draf');
            $table->json('snapshot_final')->nullable();
            $table->timestamp('difinalisasi_pada')->nullable();
            $table->foreignId('difinalisasi_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->unsignedInteger('versi')->default(0);
            $table->foreignId('dibuat_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->foreignId('diubah_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamps();
            $table->index(['program_kerja_humas_id', 'status'], 'laporan_humas_program_status');
        });
        Schema::create('bukti_laporan_humas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('laporan_pelaksanaan_humas_id')->constrained('laporan_pelaksanaan_humas')->restrictOnDelete();
            $table->foreignId('riwayat_dokumen_humas_id')->constrained('riwayat_dokumen_humas')->restrictOnDelete();
            $table->uuid('token_pembuatan')->unique();
            $table->string('judul', 180);
            $table->timestamp('dilepas_pada')->nullable();
            $table->foreignId('dibuat_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('riwayat_program_kerja_humas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_kerja_humas_id')->constrained('program_kerja_humas')->restrictOnDelete();
            $table->foreignId('laporan_pelaksanaan_humas_id')->nullable()->constrained('laporan_pelaksanaan_humas')->restrictOnDelete();
            $table->string('aksi', 80);
            $table->unsignedInteger('versi');
            $table->json('snapshot');
            $table->text('catatan_perubahan')->nullable();
            $table->foreignId('pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamp('created_at');
        });
        foreach (['lihat' => 'Lihat program kerja dan laporan Humas', 'kelola' => 'Kelola program kerja dan laporan Humas'] as $aksi => $nama) {
            $kode = 'program_kerja_humas.'.$aksi;
            DB::table('izin')->updateOrInsert(['kode' => $kode], ['kelompok' => 'Humas', 'nama' => $nama,
                'deskripsi' => 'Program Waka Humas, target kegiatan, laporan pelaksanaan, evaluasi, dan cetak. Bukti mengikuti izin dokumen.', 'aktif' => true, 'sistem' => true, 'created_at' => now(), 'updated_at' => now()]);
            $izin = DB::table('izin')->where('kode', $kode)->value('id');
            foreach ($aksi === 'lihat' ? ['administrator', 'pimpinan', 'wakil_pimpinan_humas'] : ['administrator', 'wakil_pimpinan_humas'] as $role) {
                $peran = DB::table('peran')->where('kode', $role)->value('id');
                if ($peran) {
                    DB::table('peran_izin')->updateOrInsert(['peran_id' => $peran, 'izin_id' => $izin], ['created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_program_kerja_humas');
        Schema::dropIfExists('bukti_laporan_humas');
        Schema::dropIfExists('laporan_pelaksanaan_humas');
        Schema::dropIfExists('program_kerja_humas');
        $izin = DB::table('izin')->whereIn('kode', ['program_kerja_humas.lihat', 'program_kerja_humas.kelola'])->pluck('id');
        DB::table('peran_izin')->whereIn('izin_id', $izin)->delete();
        DB::table('izin')->whereIn('id', $izin)->delete();
    }
};
