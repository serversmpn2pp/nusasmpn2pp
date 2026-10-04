<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['komite_humas.lihat' => ['Lihat kepengurusan komite sekolah', ['wakil_pimpinan_humas', 'pimpinan']],
            'komite_humas.kelola' => ['Kelola kepengurusan komite sekolah', ['wakil_pimpinan_humas']]] as $kode => [$nama, $roles]) {
            DB::table('izin')->updateOrInsert(['kode' => $kode], ['nama' => $nama, 'kelompok' => 'Humas',
                'deskripsi' => 'Susunan pengurus, masa bakti, SK, dan riwayat kepengurusan komite sekolah.', 'aktif' => true, 'sistem' => true, 'created_at' => now(), 'updated_at' => now()]);
            $izin = DB::table('izin')->where('kode', $kode)->value('id');
            foreach (DB::table('peran')->whereIn('kode', $roles)->pluck('id') as $peran) {
                DB::table('peran_izin')->insertOrIgnore(['peran_id' => $peran, 'izin_id' => $izin, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        Schema::create('periode_komite_humas', function (Blueprint $table) {
            $table->id();
            $table->uuid('token_pembuatan')->unique();
            $table->string('nama', 180);
            $table->date('tanggal_mulai')->index();
            $table->date('tanggal_selesai')->index();
            $table->string('nomor_sk', 150)->nullable();
            $table->date('tanggal_sk')->nullable();
            $table->foreignId('riwayat_dokumen_humas_id')->nullable()->constrained('riwayat_dokumen_humas')->restrictOnDelete();
            $table->string('status', 20)->default('draf')->index();
            $table->text('catatan')->nullable();
            $table->unsignedInteger('versi')->default(0);
            $table->foreignId('dibuat_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->foreignId('diubah_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('pengurus_komite_humas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_komite_humas_id')->constrained('periode_komite_humas')->cascadeOnDelete();
            $table->string('nama', 180);
            $table->string('jabatan', 30);
            $table->text('nomor_telepon')->nullable();
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });
        Schema::create('riwayat_komite_humas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periode_komite_humas_id')->constrained('periode_komite_humas')->cascadeOnDelete();
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
        Schema::dropIfExists('riwayat_komite_humas');
        Schema::dropIfExists('pengurus_komite_humas');
        Schema::dropIfExists('periode_komite_humas');
        DB::table('izin')->whereIn('kode', ['komite_humas.lihat', 'komite_humas.kelola'])->delete();
    }
};
