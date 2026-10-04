<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['kemitraan_humas.lihat' => ['Lihat kemitraan sekolah', ['wakil_pimpinan_humas', 'pimpinan']],
            'kemitraan_humas.kelola' => ['Kelola mitra dan MoU', ['wakil_pimpinan_humas']]] as $kode => [$nama, $roles]) {
            DB::table('izin')->updateOrInsert(['kode' => $kode], ['nama' => $nama, 'kelompok' => 'Humas',
                'deskripsi' => 'Data mitra, perjanjian kerja sama, masa berlaku, dan riwayat kegiatan Humas.',
                'aktif' => true, 'sistem' => true, 'created_at' => now(), 'updated_at' => now()]);
            $izinId = DB::table('izin')->where('kode', $kode)->value('id');
            foreach (DB::table('peran')->whereIn('kode', $roles)->pluck('id') as $id) {
                DB::table('peran_izin')->insertOrIgnore(['peran_id' => $id, 'izin_id' => $izinId, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        Schema::create('mitra_humas', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 180);
            $table->string('jenis', 40)->index();
            $table->string('alamat', 1000)->nullable();
            $table->string('nama_kontak', 180)->nullable();
            $table->string('jabatan_kontak', 120)->nullable();
            $table->string('nomor_kontak', 32)->nullable();
            $table->string('email', 180)->nullable();
            $table->string('website', 1000)->nullable();
            $table->text('catatan')->nullable();
            $table->string('status', 20)->default('aktif')->index();
            $table->unsignedInteger('versi')->default(0);
            $table->foreignId('diubah_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('kerja_sama_humas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mitra_humas_id')->constrained('mitra_humas')->restrictOnDelete();
            $table->string('judul', 180);
            $table->string('nomor', 120)->nullable();
            $table->string('bidang', 40)->index();
            $table->text('ruang_lingkup');
            $table->string('penanggung_jawab', 180)->nullable();
            $table->date('tanggal_mulai')->nullable()->index();
            $table->date('tanggal_selesai')->nullable()->index();
            $table->unsignedSmallInteger('ingatkan_hari_sebelum')->default(30);
            $table->string('status', 20)->default('draf')->index();
            $table->string('alasan_diakhiri', 1000)->nullable();
            $table->foreignId('dokumen_humas_id')->nullable()->constrained('dokumen_humas')->nullOnDelete();
            $table->uuid('token_unggahan_mou')->nullable()->unique();
            $table->unsignedInteger('versi')->default(0);
            $table->foreignId('diubah_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'tanggal_selesai']);
        });
        Schema::create('mitra_humas_agenda', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mitra_humas_id')->constrained('mitra_humas')->cascadeOnDelete();
            $table->foreignId('agenda_humas_id')->constrained('agenda_humas')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['mitra_humas_id', 'agenda_humas_id']);
        });
        Schema::create('riwayat_kemitraan_humas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mitra_humas_id')->constrained('mitra_humas')->cascadeOnDelete();
            $table->foreignId('kerja_sama_humas_id')->nullable()->constrained('kerja_sama_humas')->nullOnDelete();
            $table->string('aksi', 40);
            $table->json('data_sebelum')->nullable();
            $table->json('data_sesudah')->nullable();
            $table->foreignId('pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_kemitraan_humas');
        Schema::dropIfExists('mitra_humas_agenda');
        Schema::dropIfExists('kerja_sama_humas');
        Schema::dropIfExists('mitra_humas');
        $ids = DB::table('izin')->whereIn('kode', ['kemitraan_humas.lihat', 'kemitraan_humas.kelola'])->pluck('id');
        DB::table('peran_izin')->whereIn('izin_id', $ids)->delete();
        DB::table('izin')->whereIn('id', $ids)->delete();
    }
};
