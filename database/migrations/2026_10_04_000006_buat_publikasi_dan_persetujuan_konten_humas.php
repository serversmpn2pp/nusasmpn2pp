<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $permissions = [
            'publikasi_humas.lihat' => ['Lihat publikasi Humas', ['wakil_pimpinan_humas', 'pimpinan']],
            'publikasi_humas.kelola' => ['Kelola draf dan bukti tayang', ['wakil_pimpinan_humas']],
            'publikasi_humas.periksa' => ['Periksa dan setujui konten', ['pimpinan']],
        ];
        foreach ($permissions as $kode => [$nama, $roles]) {
            DB::table('izin')->updateOrInsert(['kode' => $kode], ['nama' => $nama, 'kelompok' => 'Humas',
                'deskripsi' => 'Draf publikasi, persetujuan pimpinan, dan arsip bukti tayang sekolah.',
                'aktif' => true, 'sistem' => true, 'created_at' => now(), 'updated_at' => now()]);
            $izinId = DB::table('izin')->where('kode', $kode)->value('id');
            foreach (DB::table('peran')->whereIn('kode', $roles)->pluck('id') as $id) {
                DB::table('peran_izin')->insertOrIgnore(['peran_id' => $id, 'izin_id' => $izinId, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        Schema::create('publikasi_humas', function (Blueprint $table) {
            $table->id();
            $table->uuid('token_pembuatan')->unique();
            $table->string('judul', 180);
            $table->string('jenis', 30)->index();
            $table->string('ringkasan', 500)->nullable();
            $table->text('isi');
            $table->string('kanal', 30);
            $table->date('rencana_tayang')->nullable()->index();
            $table->foreignId('agenda_humas_id')->nullable()->constrained('agenda_humas')->nullOnDelete();
            $table->string('status', 20)->default('draf')->index();
            $table->unsignedInteger('versi')->default(0);
            $table->text('catatan_pemeriksaan')->nullable();
            $table->timestamp('diajukan_pada')->nullable();
            $table->timestamp('diperiksa_pada')->nullable();
            $table->timestamp('waktu_tayang')->nullable()->index();
            $table->string('url_tayang', 2000)->nullable();
            foreach (['dibuat_oleh', 'diubah_oleh', 'diajukan_oleh', 'diperiksa_oleh'] as $aktor) {
                $table->foreignId($aktor.'_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            }
            $table->timestamps();
        });
        Schema::create('lampiran_publikasi_humas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publikasi_humas_id')->constrained('publikasi_humas')->cascadeOnDelete();
            $table->string('jenis', 20);
            $table->string('lokasi_file');
            $table->string('nama_file_asli');
            $table->string('tipe_file', 100);
            $table->unsignedBigInteger('ukuran_file');
            $table->timestamp('dihapus_pada')->nullable();
            $table->timestamps();
        });
        Schema::create('riwayat_publikasi_humas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('publikasi_humas_id')->constrained('publikasi_humas')->cascadeOnDelete();
            $table->string('aksi', 80);
            $table->unsignedInteger('versi');
            $table->json('snapshot');
            $table->text('catatan')->nullable();
            $table->foreignId('pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_publikasi_humas');
        Schema::dropIfExists('lampiran_publikasi_humas');
        Schema::dropIfExists('publikasi_humas');
        DB::table('izin')->whereIn('kode', ['publikasi_humas.lihat', 'publikasi_humas.kelola', 'publikasi_humas.periksa'])->delete();
    }
};
