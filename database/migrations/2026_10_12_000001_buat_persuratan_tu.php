<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('surat_tu', function (Blueprint $table) {
            $table->id();
            $table->string('jenis', 20);
            $table->string('nomor_surat', 120);
            $table->char('nomor_unik', 64)->unique();
            $table->string('nomor_agenda', 80)->nullable();
            $table->char('agenda_unik', 64)->nullable()->unique();
            $table->date('tanggal_surat');
            $table->date('tanggal_diterima')->nullable();
            $table->string('perihal', 200);
            $table->string('pihak', 200);
            $table->string('sifat', 20)->default('biasa');
            $table->text('keterangan')->nullable();
            $table->date('mulai_tugas')->nullable();
            $table->date('selesai_tugas')->nullable();
            $table->string('tempat_tugas', 200)->nullable();
            $table->text('uraian_tugas')->nullable();
            $table->string('status', 25)->default('draf')->index();
            $table->unsignedInteger('versi')->default(1);
            $table->foreignId('revisi_dari_id')->nullable()->constrained('surat_tu')->restrictOnDelete();
            $table->foreignId('pengesah_pengguna_id')->nullable()->constrained('pengguna')->restrictOnDelete();
            $table->foreignId('disahkan_oleh_pengguna_id')->nullable()->constrained('pengguna')->restrictOnDelete();
            $table->timestamp('diajukan_pada')->nullable();
            $table->timestamp('disahkan_pada')->nullable();
            $table->timestamp('dikirim_pada')->nullable();
            $table->timestamp('dibatalkan_pada')->nullable();
            $table->text('catatan_status')->nullable();
            $table->foreignId('dibuat_oleh_pengguna_id')->constrained('pengguna')->restrictOnDelete();
            $table->foreignId('diubah_oleh_pengguna_id')->constrained('pengguna')->restrictOnDelete();
            $table->timestamps();
            $table->index(['jenis', 'tanggal_surat']);
        });
        Schema::create('berkas_surat_tu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surat_tu_id')->constrained('surat_tu')->restrictOnDelete();
            $table->string('jenis', 20);
            $table->string('lokasi_file');
            $table->string('nama_file_asli');
            $table->string('tipe_file', 100);
            $table->unsignedBigInteger('ukuran_file');
            $table->char('sha256', 64);
            $table->boolean('aktif')->default(true);
            $table->foreignId('diunggah_oleh_pengguna_id')->constrained('pengguna')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('penugasan_surat_tu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surat_tu_id')->constrained('surat_tu')->restrictOnDelete();
            $table->foreignId('pegawai_id')->constrained('pegawai')->restrictOnDelete();
            $table->string('nama_pegawai', 200);
            $table->timestamp('dibaca_pada')->nullable();
            $table->foreignId('dibaca_oleh_pengguna_id')->nullable()->constrained('pengguna')->restrictOnDelete();
            $table->timestamps();
            $table->unique(['surat_tu_id', 'pegawai_id']);
        });
        Schema::create('riwayat_surat_tu', function (Blueprint $table) {
            $table->id();
            $table->foreignId('surat_tu_id')->constrained('surat_tu')->restrictOnDelete();
            $table->foreignId('pengguna_id')->constrained('pengguna')->restrictOnDelete();
            $table->string('aksi', 40);
            $table->unsignedInteger('versi');
            $table->text('catatan')->nullable();
            $table->json('data');
            $table->timestamps();
        });

        DB::table('peran')->updateOrInsert(['kode' => 'tata_usaha'], [
            'nama' => 'Tata Usaha', 'deskripsi' => 'Mengelola persuratan dan arsip sekolah; tidak mengesahkan surat.',
            'aktif' => true, 'sistem' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach (['lihat' => 'Lihat arsip surat', 'kelola' => 'Kelola surat dan penugasan',
            'sahkan' => 'Periksa dan sahkan surat', 'rahasia' => 'Akses arsip surat rahasia'] as $kode => $nama) {
            DB::table('izin')->updateOrInsert(['kode' => 'surat.'.$kode], [
                'nama' => $nama, 'kelompok' => 'Persuratan', 'deskripsi' => $nama.' sesuai akses persuratan sekolah.',
                'sistem' => true, 'aktif' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        foreach (['administrator' => ['lihat', 'kelola', 'sahkan', 'rahasia'], 'pimpinan' => ['lihat', 'sahkan', 'rahasia'],
            'tata_usaha' => ['lihat', 'kelola', 'rahasia']] as $peran => $izin) {
            $id = DB::table('peran')->where('kode', $peran)->value('id');
            if ($id) {
                foreach ($izin as $kode) {
                    DB::table('peran_izin')->insertOrIgnore(['peran_id' => $id,
                        'izin_id' => DB::table('izin')->where('kode', 'surat.'.$kode)->value('id'),
                        'created_at' => now(), 'updated_at' => now()]);
                }
            }
        }
        $tu = DB::table('peran')->where('kode', 'tata_usaha')->value('id');
        foreach (DB::table('izin')->whereIn('kode', ['beranda.akses', 'pegawai.profil', 'absensi_pegawai.pribadi'])->pluck('id') as $izin) {
            DB::table('peran_izin')->insertOrIgnore(['peran_id' => $tu, 'izin_id' => $izin, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_surat_tu');
        Schema::dropIfExists('penugasan_surat_tu');
        Schema::dropIfExists('berkas_surat_tu');
        Schema::dropIfExists('surat_tu');
        $ids = DB::table('izin')->whereIn('kode', ['surat.lihat', 'surat.kelola', 'surat.sahkan', 'surat.rahasia'])->pluck('id');
        DB::table('peran_izin')->whereIn('izin_id', $ids)->delete();
        DB::table('izin')->whereIn('id', $ids)->delete();
    }
};
