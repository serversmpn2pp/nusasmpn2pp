<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'buku_tamu.lihat' => ['Lihat rekap buku tamu', 'Melihat riwayat, lampiran privat, dan mengekspor laporan kunjungan.', ['wakil_pimpinan_humas', 'pimpinan']],
            'buku_tamu.kelola' => ['Kelola buku tamu', 'Mencatat, mengoreksi, dan membatalkan kunjungan dengan jejak perubahan.', ['wakil_pimpinan_humas']],
            'buku_tamu.catat' => ['Catat tamu operasional', 'Mencatat kedatangan, kepulangan, dan lampiran kunjungan operasional.', ['satpam']],
            'buku_tamu.catat_piket' => ['Catat tamu saat piket', 'Mencatat tamu operasional hanya saat jadwal guru piket aktif hari ini.', ['guru_mapel']],
        ] as $kode => [$nama, $deskripsi, $roles]) {
            DB::table('izin')->updateOrInsert(['kode' => $kode], ['nama' => $nama, 'deskripsi' => $deskripsi,
                'kelompok' => 'Humas', 'sistem' => true, 'aktif' => true, 'created_at' => now(), 'updated_at' => now()]);
            $izinId = DB::table('izin')->where('kode', $kode)->value('id');
            foreach (DB::table('peran')->whereIn('kode', $roles)->pluck('id') as $roleId) {
                DB::table('peran_izin')->insertOrIgnore(['peran_id' => $roleId, 'izin_id' => $izinId, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        Schema::create('kunjungan_tamu', function (Blueprint $table) {
            $table->id();
            $table->uuid('token_pencatatan')->unique();
            $table->string('nama_tamu', 180);
            $table->string('instansi', 180)->nullable();
            $table->string('alamat_instansi', 500)->nullable();
            $table->string('jabatan', 120)->nullable();
            $table->string('nomor_wa', 32)->nullable();
            $table->string('kategori', 40)->index();
            $table->text('keperluan');
            $table->foreignId('pegawai_tujuan_id')->nullable()->constrained('pegawai')->nullOnDelete();
            $table->string('nama_tujuan', 180);
            $table->dateTime('waktu_datang')->index();
            $table->dateTime('waktu_pulang')->nullable();
            $table->string('status', 20)->default('berkunjung')->index();
            $table->string('catatan', 1000)->nullable();
            $table->string('alasan_pembatalan', 1000)->nullable();
            $table->unsignedInteger('versi')->default(0);
            $table->foreignId('dicatat_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->foreignId('diubah_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'waktu_datang']);
        });
        Schema::create('lampiran_kunjungan_tamu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kunjungan_tamu_id')->constrained('kunjungan_tamu')->cascadeOnDelete();
            $table->string('jenis', 30);
            $table->string('lokasi_file');
            $table->string('nama_file_asli');
            $table->string('tipe_file', 120);
            $table->unsignedBigInteger('ukuran_file');
            $table->foreignId('diunggah_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('riwayat_kunjungan_tamu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kunjungan_tamu_id')->constrained('kunjungan_tamu')->cascadeOnDelete();
            $table->string('aksi', 30);
            $table->uuid('token_operasi')->nullable()->unique();
            $table->json('data_sebelum')->nullable();
            $table->json('data_sesudah')->nullable();
            $table->foreignId('pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_kunjungan_tamu');
        Schema::dropIfExists('lampiran_kunjungan_tamu');
        Schema::dropIfExists('kunjungan_tamu');
        $ids = DB::table('izin')->whereIn('kode', ['buku_tamu.lihat', 'buku_tamu.kelola', 'buku_tamu.catat', 'buku_tamu.catat_piket'])->pluck('id');
        DB::table('peran_izin')->whereIn('izin_id', $ids)->delete();
        DB::table('izin')->whereIn('id', $ids)->delete();
    }
};
