<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kontak_humas', function (Blueprint $t) {
            $t->id();
            $t->uuid('token_pembuatan')->unique();
            $t->string('nama', 180);
            $t->string('instansi', 180)->nullable();
            $t->string('jabatan', 120)->nullable();
            $t->string('urusan', 40)->index();
            $t->string('nomor_kontak', 32)->nullable();
            $t->string('email', 180)->nullable();
            $t->string('dasar_penggunaan', 1000);
            $t->text('catatan')->nullable();
            $t->string('status', 20)->default('aktif')->index();
            $t->unsignedInteger('versi')->default(0);
            $t->foreignId('dibuat_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $t->foreignId('diubah_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('riwayat_kontak_humas', function (Blueprint $t) {
            $t->id();
            $t->foreignId('kontak_humas_id')->constrained('kontak_humas')->cascadeOnDelete();
            $t->string('aksi', 40);
            $t->json('kolom_berubah');
            $t->text('alasan');
            $t->foreignId('pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $t->timestamp('created_at');
        });
        Schema::create('dokumen_pendukung_kemitraan', function (Blueprint $t) {
            $t->id();
            $t->foreignId('kerja_sama_humas_id')->constrained('kerja_sama_humas')->cascadeOnDelete();
            $t->foreignId('riwayat_dokumen_humas_id')->constrained('riwayat_dokumen_humas')->restrictOnDelete();
            $t->string('jenis', 40);
            $t->string('catatan', 1000);
            $t->foreignId('pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $t->timestamp('dilepas_pada')->nullable();
            $t->foreignId('dilepas_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $t->timestamps();
            $t->index(['kerja_sama_humas_id', 'dilepas_pada'], 'pendukung_kemitraan_aktif');
        });
        DB::table('peran')->insertOrIgnore(['kode' => 'kontributor_publikasi', 'nama' => 'Kontributor Publikasi',
            'deskripsi' => 'Membuat dan mengajukan publikasi milik sendiri; tidak menyetujui atau menerbitkan.',
            'aktif' => true, 'sistem' => true, 'created_at' => now(), 'updated_at' => now()]);
        foreach ([
            'kontak_humas.lihat' => ['Lihat direktori Humas', ['wakil_pimpinan_humas', 'pimpinan']],
            'kontak_humas.kontak' => ['Lihat nomor dan email kontak Humas', ['wakil_pimpinan_humas']],
            'kontak_humas.kelola' => ['Kelola direktori Humas', ['wakil_pimpinan_humas']],
            'publikasi_humas.kontribusi' => ['Kontribusi publikasi milik sendiri', ['kontributor_publikasi']],
        ] as $kode => [$nama, $roles]) {
            DB::table('izin')->insertOrIgnore(['kode' => $kode, 'nama' => $nama, 'kelompok' => 'Humas',
                'deskripsi' => $nama, 'aktif' => true, 'sistem' => true, 'created_at' => now(), 'updated_at' => now()]);
            $izin = DB::table('izin')->where('kode', $kode)->value('id');
            foreach (DB::table('peran')->whereIn('kode', $roles)->pluck('id') as $peran) {
                DB::table('peran_izin')->insertOrIgnore(['peran_id' => $peran, 'izin_id' => $izin, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('dokumen_pendukung_kemitraan');
        Schema::dropIfExists('riwayat_kontak_humas');
        Schema::dropIfExists('kontak_humas');
        // Keep role assignments on rollback; revocation is an administrator decision.
    }
};
