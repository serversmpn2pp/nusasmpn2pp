<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alumni_humas', function (Blueprint $table) {
            $table->id();
            $table->uuid('token_pembuatan')->unique();
            $table->foreignId('siswa_id')->nullable()->unique()->constrained('siswa')->restrictOnDelete();
            $table->foreignId('anggota_kelas_id')->nullable()->constrained('anggota_kelas')->restrictOnDelete();
            $table->string('nama_lengkap', 180);
            $table->string('nis', 30)->nullable();
            $table->string('nisn', 10)->nullable()->unique();
            $table->string('jenis_kelamin', 1)->nullable();
            $table->unsignedSmallInteger('tahun_masuk')->nullable();
            $table->unsignedSmallInteger('tahun_lulus')->index();
            $table->date('tanggal_lulus')->nullable();
            $table->string('kelas_terakhir', 80)->nullable();
            $table->string('status', 12)->default('aktif');
            $table->string('status_penelusuran', 25)->default('belum_terdata');
            $table->string('jenis_sekolah', 20)->nullable();
            $table->string('nama_sekolah', 180)->nullable();
            $table->string('kota_sekolah', 120)->nullable();
            $table->string('jurusan', 180)->nullable();
            $table->date('tanggal_penelusuran')->nullable();
            $table->text('nomor_wa')->nullable();
            $table->text('email')->nullable();
            $table->text('catatan_penelusuran')->nullable();
            $table->timestamp('kelulusan_dicatat_pada');
            $table->unsignedInteger('versi')->default(0);
            $table->foreignId('dibuat_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->foreignId('diubah_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamps();
            $table->unique(['tahun_lulus', 'nis'], 'alumni_humas_tahun_nis_unik');
            $table->index(['status', 'tahun_lulus', 'status_penelusuran'], 'alumni_humas_filter');
        });
        Schema::create('riwayat_alumni_humas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('alumni_humas_id')->constrained('alumni_humas')->restrictOnDelete();
            $table->string('aksi', 80);
            $table->unsignedInteger('versi');
            $table->json('snapshot');
            $table->text('snapshot_privat')->nullable();
            $table->text('catatan_perubahan')->nullable();
            $table->foreignId('pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamp('created_at');
        });
        foreach (['lihat' => 'Lihat database dan statistik alumni', 'kelola' => 'Kelola alumni dan kontak privat', 'ekspor' => 'Ekspor database alumni'] as $aksi => $nama) {
            $kode = 'alumni_humas.'.$aksi;
            DB::table('izin')->updateOrInsert(['kode' => $kode], ['kelompok' => 'Humas', 'nama' => $nama,
                'deskripsi' => 'Pendataan alumni dan penelusuran sekolah lanjutan. Kontak dan catatan privat hanya untuk pengelola.', 'sistem' => true, 'aktif' => true, 'created_at' => now(), 'updated_at' => now()]);
            $izin = DB::table('izin')->where('kode', $kode)->value('id');
            foreach ($aksi === 'lihat' ? ['administrator', 'wakil_pimpinan_humas', 'pimpinan'] : ['administrator', 'wakil_pimpinan_humas'] as $role) {
                $peran = DB::table('peran')->where('kode', $role)->value('id');
                if ($peran) {
                    DB::table('peran_izin')->insertOrIgnore(['peran_id' => $peran, 'izin_id' => $izin, 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_alumni_humas');
        Schema::dropIfExists('alumni_humas');
        DB::table('izin')->whereIn('kode', ['alumni_humas.lihat', 'alumni_humas.kelola', 'alumni_humas.ekspor'])->delete();
    }
};
