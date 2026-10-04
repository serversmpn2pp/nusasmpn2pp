<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['kliping_berita_humas.lihat' => ['Lihat kliping berita sekolah', ['wakil_pimpinan_humas', 'pimpinan']],
            'kliping_berita_humas.kelola' => ['Kelola kliping berita sekolah', ['wakil_pimpinan_humas']]] as $kode => [$nama, $roles]) {
            DB::table('izin')->updateOrInsert(['kode' => $kode], ['nama' => $nama, 'kelompok' => 'Humas',
                'deskripsi' => 'Arsip pemberitaan sekolah oleh media luar, tautan sumber, dan bukti pemberitaan privat.',
                'aktif' => true, 'sistem' => true, 'created_at' => now(), 'updated_at' => now()]);
            $izinId = DB::table('izin')->where('kode', $kode)->value('id');
            foreach (DB::table('peran')->whereIn('kode', $roles)->pluck('id') as $id) {
                DB::table('peran_izin')->insertOrIgnore(['peran_id' => $id, 'izin_id' => $izinId, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        Schema::create('kliping_berita_humas', function (Blueprint $table) {
            $table->id();
            $table->uuid('token_pembuatan')->unique();
            $table->string('judul', 180);
            $table->string('nama_media', 180)->index();
            $table->string('jenis', 30)->index();
            $table->string('topik', 30)->index();
            $table->date('tanggal_terbit')->index();
            $table->string('penulis', 180)->nullable();
            $table->string('rujukan', 250)->nullable();
            $table->string('tautan', 2000)->nullable();
            $table->char('tautan_hash', 64)->nullable()->unique();
            $table->text('ringkasan')->nullable();
            $table->text('catatan')->nullable();
            $table->foreignId('riwayat_dokumen_humas_id')->nullable()->constrained('riwayat_dokumen_humas')->restrictOnDelete();
            $table->string('status', 20)->default('aktif')->index();
            $table->unsignedInteger('versi')->default(0);
            $table->foreignId('dibuat_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->foreignId('diubah_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('riwayat_kliping_berita_humas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kliping_berita_humas_id')->constrained('kliping_berita_humas')->cascadeOnDelete();
            $table->unsignedInteger('versi');
            $table->string('aksi', 80);
            $table->json('snapshot');
            $table->foreignId('riwayat_dokumen_humas_id')->nullable()->constrained('riwayat_dokumen_humas')->restrictOnDelete();
            $table->text('catatan_perubahan')->nullable();
            $table->foreignId('pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_kliping_berita_humas');
        Schema::dropIfExists('kliping_berita_humas');
        DB::table('izin')->whereIn('kode', ['kliping_berita_humas.lihat', 'kliping_berita_humas.kelola'])->delete();
    }
};
