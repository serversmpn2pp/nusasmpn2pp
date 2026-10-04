<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prestasi_sekolah', function (Blueprint $table) {
            $table->id();
            $table->uuid('token_pembuatan')->unique();
            $table->char('identitas_hash', 64)->unique();
            $table->foreignId('tahun_pelajaran_id')->nullable()->constrained('tahun_pelajaran')->restrictOnDelete();
            $table->string('nama_kegiatan', 180);
            $table->string('cabang', 120)->nullable();
            $table->string('kategori', 25);
            $table->string('tingkat', 25);
            $table->string('perolehan', 25);
            $table->string('capaian', 180);
            $table->date('tanggal_prestasi')->index();
            $table->string('penyelenggara', 180)->nullable();
            $table->string('tempat', 180)->nullable();
            $table->string('pembina', 180)->nullable();
            $table->string('penerima', 20);
            $table->string('bentuk', 20);
            $table->string('nama_tim', 180)->nullable();
            $table->text('catatan')->nullable();
            $table->string('tautan', 2000)->nullable();
            $table->foreignId('riwayat_dokumen_humas_id')->nullable()->constrained('riwayat_dokumen_humas')->restrictOnDelete();
            $table->string('status', 20)->default('draf');
            $table->timestamp('diverifikasi_pada')->nullable();
            $table->foreignId('diverifikasi_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->foreignId('dibuat_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->foreignId('diubah_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->unsignedInteger('versi')->default(0);
            $table->timestamps();
            $table->index(['status', 'tanggal_prestasi'], 'prestasi_sekolah_rekap');
        });
        Schema::create('peserta_prestasi_sekolah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prestasi_sekolah_id')->constrained('prestasi_sekolah')->restrictOnDelete();
            $table->foreignId('siswa_id')->nullable()->constrained('siswa')->restrictOnDelete();
            $table->foreignId('pegawai_id')->nullable()->constrained('pegawai')->restrictOnDelete();
            $table->string('nama', 180);
            $table->string('kelas', 80)->nullable();
            $table->unique(['prestasi_sekolah_id', 'siswa_id'], 'prestasi_siswa_unik');
            $table->unique(['prestasi_sekolah_id', 'pegawai_id'], 'prestasi_pegawai_unik');
        });
        Schema::create('riwayat_prestasi_sekolah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prestasi_sekolah_id')->constrained('prestasi_sekolah')->restrictOnDelete();
            $table->unsignedInteger('versi');
            $table->string('aksi', 80);
            $table->json('snapshot');
            $table->foreignId('riwayat_dokumen_humas_id')->nullable()->constrained('riwayat_dokumen_humas')->restrictOnDelete();
            $table->text('catatan_perubahan')->nullable();
            $table->foreignId('pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamp('created_at');
        });
        foreach (['lihat' => 'Lihat database prestasi sekolah', 'kelola' => 'Kelola dan verifikasi prestasi sekolah', 'ekspor' => 'Ekspor prestasi sekolah'] as $aksi => $nama) {
            $kode = 'prestasi_sekolah.'.$aksi;
            DB::table('izin')->updateOrInsert(['kode' => $kode], ['nama' => $nama, 'kelompok' => 'Humas', 'deskripsi' => 'Prestasi siswa, guru/pegawai, dan sekolah. Bukti mengikuti izin dokumen Humas.', 'sistem' => true, 'aktif' => true, 'created_at' => now(), 'updated_at' => now()]);
            $izin = DB::table('izin')->where('kode', $kode)->value('id');
            foreach ($aksi === 'lihat' ? ['administrator', 'wakil_pimpinan_humas', 'pimpinan'] : ['administrator', 'wakil_pimpinan_humas'] as $role) {
                $id = DB::table('peran')->where('kode', $role)->value('id');
                if ($id) {
                    DB::table('peran_izin')->insertOrIgnore(['peran_id' => $id, 'izin_id' => $izin, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_prestasi_sekolah');
        Schema::dropIfExists('peserta_prestasi_sekolah');
        Schema::dropIfExists('prestasi_sekolah');
        DB::table('izin')->whereIn('kode', ['prestasi_sekolah.lihat', 'prestasi_sekolah.kelola', 'prestasi_sekolah.ekspor'])->delete();
    }
};
