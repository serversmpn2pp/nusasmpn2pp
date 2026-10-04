<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['media_resmi_humas.lihat' => ['Lihat media resmi sekolah', ['wakil_pimpinan_humas', 'pimpinan']],
            'media_resmi_humas.kelola' => ['Kelola media resmi sekolah', ['wakil_pimpinan_humas']]] as $kode => [$nama, $roles]) {
            DB::table('izin')->updateOrInsert(['kode' => $kode], ['nama' => $nama, 'kelompok' => 'Humas',
                'deskripsi' => 'Daftar alamat media resmi sekolah dan penanggung jawabnya, tanpa kredensial login.',
                'aktif' => true, 'sistem' => true, 'created_at' => now(), 'updated_at' => now()]);
            $izinId = DB::table('izin')->where('kode', $kode)->value('id');
            foreach (DB::table('peran')->whereIn('kode', $roles)->pluck('id') as $id) {
                DB::table('peran_izin')->insertOrIgnore(['peran_id' => $id, 'izin_id' => $izinId, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        Schema::create('media_resmi_humas', function (Blueprint $table) {
            $table->id();
            $table->uuid('token_pembuatan')->unique();
            $table->string('nama', 180);
            $table->string('jenis', 30)->index();
            $table->string('tautan', 2000);
            $table->char('tautan_hash', 64)->unique();
            $table->string('identitas_akun', 180)->nullable();
            $table->string('penanggung_jawab', 180)->index();
            $table->string('jabatan_penanggung_jawab', 100)->nullable();
            $table->string('status', 20)->default('aktif')->index();
            $table->date('tanggal_diperiksa')->nullable();
            $table->text('catatan')->nullable();
            $table->unsignedInteger('versi')->default(0);
            $table->foreignId('dibuat_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->foreignId('diubah_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('riwayat_media_resmi_humas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_resmi_humas_id')->constrained('media_resmi_humas')->cascadeOnDelete();
            $table->unsignedInteger('versi');
            $table->string('aksi', 80);
            $table->json('snapshot');
            $table->text('catatan_perubahan')->nullable();
            $table->foreignId('pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_media_resmi_humas');
        Schema::dropIfExists('media_resmi_humas');
        DB::table('izin')->whereIn('kode', ['media_resmi_humas.lihat', 'media_resmi_humas.kelola'])->delete();
    }
};
