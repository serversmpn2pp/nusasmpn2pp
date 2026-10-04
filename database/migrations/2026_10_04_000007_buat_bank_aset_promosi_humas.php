<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['aset_promosi_humas.lihat' => ['Lihat bank aset promosi', ['wakil_pimpinan_humas', 'pimpinan']],
            'aset_promosi_humas.kelola' => ['Kelola bank aset promosi', ['wakil_pimpinan_humas']]] as $kode => [$nama, $roles]) {
            DB::table('izin')->updateOrInsert(['kode' => $kode], ['nama' => $nama, 'kelompok' => 'Humas',
                'deskripsi' => 'Aset promosi, versi berkas, tautan video, dan pemakaian dalam publikasi sekolah.',
                'aktif' => true, 'sistem' => true, 'created_at' => now(), 'updated_at' => now()]);
            $izinId = DB::table('izin')->where('kode', $kode)->value('id');
            foreach (DB::table('peran')->whereIn('kode', $roles)->pluck('id') as $id) {
                DB::table('peran_izin')->insertOrIgnore(['peran_id' => $id, 'izin_id' => $izinId, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        $pimpinanId = DB::table('peran')->where('kode', 'pimpinan')->value('id');
        $dokumenIzinId = DB::table('izin')->where('kode', 'dokumen_humas.lihat')->value('id');
        if ($pimpinanId && $dokumenIzinId) {
            DB::table('peran_izin')->insertOrIgnore(['peran_id' => $pimpinanId, 'izin_id' => $dokumenIzinId, 'created_at' => now(), 'updated_at' => now()]);
        }
        Schema::create('aset_promosi_humas', function (Blueprint $table) {
            $table->id();
            $table->uuid('token_pembuatan')->unique();
            $table->string('nama', 180);
            $table->string('kategori', 30)->index();
            $table->text('deskripsi')->nullable();
            $table->date('tanggal_aset')->index();
            $table->string('kata_kunci', 200)->nullable();
            $table->string('kredit', 180)->nullable();
            $table->string('ketentuan_penggunaan', 1000)->nullable();
            $table->string('sumber', 20)->default('berkas');
            $table->string('tautan', 2000)->nullable();
            $table->foreignId('riwayat_dokumen_humas_id')->nullable()->constrained('riwayat_dokumen_humas')->restrictOnDelete();
            $table->string('status', 20)->default('aktif')->index();
            $table->unsignedInteger('versi')->default(0);
            $table->foreignId('dibuat_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->foreignId('diubah_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('riwayat_aset_promosi_humas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aset_promosi_humas_id')->constrained('aset_promosi_humas')->cascadeOnDelete();
            $table->unsignedInteger('versi');
            $table->string('aksi', 80);
            $table->json('snapshot');
            $table->foreignId('riwayat_dokumen_humas_id')->nullable()->constrained('riwayat_dokumen_humas')->restrictOnDelete();
            $table->text('catatan')->nullable();
            $table->foreignId('pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamp('created_at');
        });
        Schema::create('aset_publikasi_humas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publikasi_humas_id')->constrained('publikasi_humas')->cascadeOnDelete();
            $table->foreignId('aset_promosi_humas_id')->constrained('aset_promosi_humas')->restrictOnDelete();
            $table->foreignId('riwayat_dokumen_humas_id')->nullable()->constrained('riwayat_dokumen_humas')->restrictOnDelete();
            $table->json('snapshot');
            $table->unique(['publikasi_humas_id', 'aset_promosi_humas_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aset_publikasi_humas');
        Schema::dropIfExists('riwayat_aset_promosi_humas');
        Schema::dropIfExists('aset_promosi_humas');
        DB::table('izin')->whereIn('kode', ['aset_promosi_humas.lihat', 'aset_promosi_humas.kelola'])->delete();
    }
};
