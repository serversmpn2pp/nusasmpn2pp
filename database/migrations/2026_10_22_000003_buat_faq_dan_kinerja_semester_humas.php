<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faq_humas', function (Blueprint $t) {
            $t->id();
            $t->uuid('token_pembuatan')->unique();
            $t->string('pertanyaan', 240);
            $t->text('jawaban');
            $t->string('urusan', 40)->index();
            $t->string('sasaran', 30)->index();
            $t->unsignedSmallInteger('urutan')->default(0);
            $t->string('status', 20)->default('draf')->index();
            $t->unsignedInteger('versi')->default(0);
            $t->timestamp('diterbitkan_pada')->nullable();
            $t->foreignId('dibuat_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $t->foreignId('diubah_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $t->timestamps();
        });
        Schema::create('kinerja_semester_humas', function (Blueprint $t) {
            $t->id();
            $t->uuid('token_pembuatan')->unique();
            $t->foreignId('tahun_pelajaran_id')->constrained('tahun_pelajaran')->restrictOnDelete();
            $t->string('semester', 10);
            $t->string('judul', 180);
            foreach (['ringkasan', 'capaian', 'kendala', 'tindak_lanjut', 'rekomendasi'] as $kolom) {
                $t->text($kolom)->nullable();
            }
            $t->date('tanggal_laporan');
            $t->string('nama_penyusun', 180);
            $t->string('nip_penyusun', 40)->nullable();
            $t->string('nama_pimpinan', 180)->nullable();
            $t->string('nip_pimpinan', 40)->nullable();
            $t->string('status', 20)->default('draf')->index();
            $t->unsignedInteger('versi')->default(0);
            $t->json('snapshot');
            $t->timestamp('difinalisasi_pada')->nullable();
            $t->foreignId('dibuat_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $t->foreignId('diubah_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $t->timestamps();
            $t->unique(['tahun_pelajaran_id', 'semester'], 'kinerja_humas_periode_unique');
        });
        foreach (['faq_humas', 'kinerja_semester_humas'] as $tabel) {
            Schema::create('riwayat_'.$tabel, function (Blueprint $t) use ($tabel) {
                $t->id();
                $t->foreignId('induk_id')->constrained($tabel)->cascadeOnDelete();
                $t->string('aksi', 40);
                $t->unsignedInteger('versi');
                $t->text('alasan');
                $t->json('data');
                $t->foreignId('pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
                $t->timestamp('created_at');
                $t->index(['induk_id', 'id']);
            });
        }
        foreach ([
            'faq_humas.kelola' => ['Kelola dan terbitkan FAQ Humas', ['wakil_pimpinan_humas']],
            'kinerja_humas.lihat' => ['Lihat laporan kinerja semester Humas', ['wakil_pimpinan_humas', 'pimpinan']],
            'kinerja_humas.kelola' => ['Kelola dan finalisasi kinerja Humas', ['wakil_pimpinan_humas']],
        ] as $kode => [$nama, $roles]) {
            DB::table('izin')->insertOrIgnore(['kode' => $kode, 'nama' => $nama, 'kelompok' => 'Humas', 'deskripsi' => $nama,
                'aktif' => true, 'sistem' => true, 'created_at' => now(), 'updated_at' => now()]);
            $izin = DB::table('izin')->where('kode', $kode)->value('id');
            foreach (DB::table('peran')->whereIn('kode', $roles)->pluck('id') as $peran) {
                DB::table('peran_izin')->insertOrIgnore(['peran_id' => $peran, 'izin_id' => $izin, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_kinerja_semester_humas');
        Schema::dropIfExists('riwayat_faq_humas');
        Schema::dropIfExists('kinerja_semester_humas');
        Schema::dropIfExists('faq_humas');
    }
};
