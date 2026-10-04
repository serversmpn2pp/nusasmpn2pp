<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['pengaduan_humas.lihat' => ['Pantau tiket aspirasi dan pengaduan', ['wakil_pimpinan_humas', 'pimpinan']],
            'pengaduan_humas.kelola' => ['Kelola tiket dan identitas pelapor', ['wakil_pimpinan_humas']],
            'pengaduan_humas.tangani' => ['Tangani tiket yang ditugaskan', ['pegawai', 'guru_mapel', 'bk', 'pimpinan', 'wakil_pimpinan_kesiswaan', 'wakil_pimpinan_kurikulum', 'wakil_pimpinan_sarana_prasarana', 'wakil_pimpinan_humas']]] as $kode => [$nama, $roles]) {
            DB::table('izin')->updateOrInsert(['kode' => $kode], ['nama' => $nama, 'kelompok' => 'Humas', 'deskripsi' => $nama.' melalui layanan internal; akses petugas dibatasi per tiket.',
                'aktif' => true, 'sistem' => true, 'created_at' => now(), 'updated_at' => now()]);
            $izinId = DB::table('izin')->where('kode', $kode)->value('id');
            foreach (DB::table('peran')->whereIn('kode', $roles)->pluck('id') as $id) {
                DB::table('peran_izin')->insertOrIgnore(['peran_id' => $id, 'izin_id' => $izinId, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        Schema::create('pengaduan_humas', function (Blueprint $table) {
            $table->id();
            $table->uuid('token_pembuatan')->unique();
            $table->string('judul', 180);
            $table->string('jenis', 20)->index();
            $table->string('kategori', 30)->index();
            $table->string('kanal', 30);
            $table->date('tanggal_diterima')->index();
            $table->text('isi');
            $table->boolean('anonim')->default(false);
            $table->text('nama_pelapor')->nullable();
            $table->text('kontak_pelapor')->nullable();
            $table->string('prioritas', 20)->default('normal')->index();
            $table->string('status', 20)->default('baru')->index();
            $table->foreignId('petugas_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->date('batas_tanggal')->nullable()->index();
            $table->text('hasil_penanganan')->nullable();
            $table->timestamp('diselesaikan_pada')->nullable();
            $table->unsignedInteger('versi')->default(0);
            $table->foreignId('dibuat_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('riwayat_pengaduan_humas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengaduan_humas_id')->constrained('pengaduan_humas')->cascadeOnDelete();
            $table->unsignedInteger('versi');
            $table->string('aksi', 80);
            $table->string('status', 20);
            $table->text('catatan');
            $table->json('snapshot');
            $table->foreignId('pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamp('created_at');
        });
        Schema::create('lampiran_pengaduan_humas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengaduan_humas_id')->constrained('pengaduan_humas')->cascadeOnDelete();
            $table->string('lokasi_file');
            $table->string('nama_file_asli');
            $table->string('tipe_file', 100);
            $table->unsignedBigInteger('ukuran_file');
            $table->foreignId('diunggah_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lampiran_pengaduan_humas');
        Schema::dropIfExists('riwayat_pengaduan_humas');
        Schema::dropIfExists('pengaduan_humas');
        DB::table('izin')->whereIn('kode', ['pengaduan_humas.lihat', 'pengaduan_humas.kelola', 'pengaduan_humas.tangani'])->delete();
    }
};
