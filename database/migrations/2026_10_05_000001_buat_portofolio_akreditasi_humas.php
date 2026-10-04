<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portofolio_akreditasi_humas', function (Blueprint $t) {
            $t->id();
            $t->uuid('token_pembuatan')->unique();
            $t->foreignId('tahun_pelajaran_id')->constrained('tahun_pelajaran')->restrictOnDelete();
            $t->string('nama', 180);
            $t->string('instrumen', 180);
            $t->string('penanggung_jawab', 180);
            $t->text('catatan')->nullable();
            $t->string('status', 20)->default('draf')->index();
            $t->unsignedInteger('versi')->default(0);
            $t->foreignId('dibuat_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('butir_akreditasi_humas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('portofolio_akreditasi_humas_id')->constrained('portofolio_akreditasi_humas')->restrictOnDelete();
            $t->string('kode', 40);
            $t->string('judul', 180);
            $t->text('deskripsi')->nullable();
            $t->unsignedSmallInteger('urutan')->default(1);
            $t->unsignedSmallInteger('target_bukti')->default(1);
            $t->string('status', 25)->default('belum_diperiksa');
            $t->text('catatan_pemeriksaan')->nullable();
            $t->foreignId('diperiksa_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $t->timestamp('diperiksa_pada')->nullable();
            $t->timestamp('dihapus_pada')->nullable();
            $t->timestamps();
            $t->index(['portofolio_akreditasi_humas_id', 'urutan'], 'butir_akreditasi_urutan');
        });
        Schema::create('bukti_akreditasi_humas', function (Blueprint $t) {
            $t->id();
            $t->uuid('token_pembuatan')->unique();
            $t->foreignId('butir_akreditasi_humas_id')->constrained('butir_akreditasi_humas')->restrictOnDelete();
            $t->foreignId('riwayat_dokumen_humas_id')->constrained('riwayat_dokumen_humas')->restrictOnDelete();
            $t->string('judul', 180);
            $t->text('catatan')->nullable();
            $t->foreignId('dibuat_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $t->timestamp('dilepas_pada')->nullable();
            $t->timestamps();
            $t->index(['butir_akreditasi_humas_id', 'dilepas_pada'], 'bukti_akreditasi_aktif');
        });
        Schema::create('riwayat_akreditasi_humas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('portofolio_akreditasi_humas_id')->constrained('portofolio_akreditasi_humas')->restrictOnDelete();
            $t->string('aksi', 180);
            $t->unsignedInteger('versi');
            $t->text('catatan')->nullable();
            $t->json('snapshot');
            $t->foreignId('pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $t->timestamp('created_at');
            $t->index(['portofolio_akreditasi_humas_id', 'id'], 'riwayat_akreditasi_portofolio');
        });
        foreach (['lihat' => 'Lihat portofolio akreditasi Humas', 'kelola' => 'Kelola butir dan pemeriksaan akreditasi Humas', 'ekspor' => 'Unduh bundel bukti akreditasi Humas'] as $kode => $nama) {
            DB::table('izin')->updateOrInsert(['kode' => 'akreditasi_humas.'.$kode], ['nama' => $nama, 'kelompok' => 'Humas', 'deskripsi' => 'Akses bukti tetap memerlukan izin Pusat Dokumen Humas.', 'sistem' => true, 'aktif' => true, 'created_at' => now(), 'updated_at' => now()]);
            $id = DB::table('izin')->where('kode', 'akreditasi_humas.'.$kode)->value('id');
            $peran = ['administrator', 'wakil_pimpinan_humas', ...($kode === 'lihat' ? ['pimpinan'] : [])];
            foreach (DB::table('peran')->whereIn('kode', $peran)->pluck('id') as $role) {
                DB::table('peran_izin')->insertOrIgnore(['peran_id' => $role, 'izin_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_akreditasi_humas');
        Schema::dropIfExists('bukti_akreditasi_humas');
        Schema::dropIfExists('butir_akreditasi_humas');
        Schema::dropIfExists('portofolio_akreditasi_humas');
        $ids = DB::table('izin')->whereIn('kode', ['akreditasi_humas.lihat', 'akreditasi_humas.kelola', 'akreditasi_humas.ekspor'])->pluck('id');
        DB::table('peran_izin')->whereIn('izin_id', $ids)->delete();
        DB::table('izin')->whereIn('id', $ids)->delete();
    }
};
