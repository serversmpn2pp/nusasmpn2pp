<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('peran')->updateOrInsert(
            ['kode' => 'wakil_pimpinan_humas'],
            [
                'nama' => 'Wakil Pimpinan Humas',
                'deskripsi' => 'Mengelola arsip, hubungan masyarakat, kemitraan, dan publikasi sekolah.',
                'sistem' => true,
                'aktif' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        foreach ([
            [
                'kode' => 'dokumen_humas.lihat',
                'nama' => 'Lihat dokumen Humas',
                'deskripsi' => 'Melihat arsip dokumen Humas dan mengunduh berkas yang tersimpan.',
            ],
            [
                'kode' => 'dokumen_humas.kelola',
                'nama' => 'Kelola dokumen Humas',
                'deskripsi' => 'Menambah, memperbarui, mengarsipkan, dan mengelola masa berlaku dokumen Humas.',
            ],
        ] as $izin) {
            DB::table('izin')->updateOrInsert(
                ['kode' => $izin['kode']],
                $izin + [
                    'kelompok' => 'Humas',
                    'sistem' => true,
                    'aktif' => true,
                    'updated_at' => $now,
                    'created_at' => $now,
                ],
            );
        }

        $peranId = DB::table('peran')->where('kode', 'wakil_pimpinan_humas')->value('id');
        $izinIds = DB::table('izin')
            ->whereIn('kode', ['dokumen_humas.lihat', 'dokumen_humas.kelola'])
            ->pluck('id');

        foreach ($izinIds as $izinId) {
            DB::table('peran_izin')->insertOrIgnore([
                'peran_id' => $peranId,
                'izin_id' => $izinId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        Schema::create('dokumen_humas', function (Blueprint $table) {
            $table->id();
            $table->string('kategori', 60)->index();
            $table->string('judul', 180);
            $table->string('nomor_dokumen', 120)->nullable();
            $table->text('deskripsi')->nullable();
            $table->string('lokasi_file');
            $table->string('nama_file_asli');
            $table->string('tipe_file', 120);
            $table->unsignedBigInteger('ukuran_file');
            $table->date('berlaku_mulai')->nullable();
            $table->date('berlaku_sampai')->nullable()->index();
            $table->unsignedSmallInteger('ingatkan_hari_sebelum')->default(30);
            $table->string('status', 20)->default('aktif')->index();
            $table->foreignId('dibuat_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->foreignId('diubah_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'berlaku_sampai']);
        });

        Schema::create('riwayat_dokumen_humas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dokumen_humas_id')->constrained('dokumen_humas')->cascadeOnDelete();
            $table->unsignedInteger('versi');
            $table->string('lokasi_file');
            $table->string('nama_file_asli');
            $table->string('tipe_file', 120);
            $table->unsignedBigInteger('ukuran_file');
            $table->text('catatan')->nullable();
            $table->foreignId('diunggah_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamp('diunggah_pada');
            $table->timestamps();

            $table->unique(['dokumen_humas_id', 'versi']);
            $table->index(['dokumen_humas_id', 'diunggah_pada']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_dokumen_humas');
        Schema::dropIfExists('dokumen_humas');

        $roleId = DB::table('peran')->where('kode', 'wakil_pimpinan_humas')->value('id');
        $permissionIds = DB::table('izin')
            ->whereIn('kode', ['dokumen_humas.lihat', 'dokumen_humas.kelola'])
            ->pluck('id');

        if ($roleId) {
            DB::table('peran_izin')->where('peran_id', $roleId)->whereIn('izin_id', $permissionIds)->delete();
            DB::table('pengguna_peran')->where('peran_id', $roleId)->delete();
            DB::table('peran')->where('id', $roleId)->delete();
        }

        DB::table('peran_izin')->whereIn('izin_id', $permissionIds)->delete();
        DB::table('izin')->whereIn('id', $permissionIds)->delete();
    }
};
