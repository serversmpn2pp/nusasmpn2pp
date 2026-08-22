<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Data percobaan alur lama dibersihkan. Bank soal dan Asesmen Kelas tidak ikut dihapus.
        DB::table('kegiatan_ujian_cbt')->delete();
        DB::table('ujian_cbt')->where('alur', 'terpusat')->delete();

        Schema::create('panitia_ujian_cbt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_ujian_cbt_id')
                ->constrained('kegiatan_ujian_cbt')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->foreignId('pegawai_id')
                ->constrained('pegawai')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
            $table->string('jabatan', 40)->default('anggota');
            $table->boolean('aktif')->default(true)->index();
            $table->text('catatan')->nullable();
            $table->foreignId('ditugaskan_oleh_pengguna_id')
                ->nullable()
                ->constrained('pengguna')
                ->nullOnDelete()
                ->cascadeOnUpdate();
            $table->timestamps();

            $table->unique(['kegiatan_ujian_cbt_id', 'pegawai_id'], 'panitia_ujian_cbt_pegawai_unik');
            $table->index(['pegawai_id', 'aktif']);
        });

        Schema::create('sesi_kegiatan_ujian_cbt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_ujian_cbt_id')
                ->constrained('kegiatan_ujian_cbt')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('kode', 40);
            $table->string('nama', 100);
            $table->time('waktu_mulai');
            $table->time('waktu_selesai');
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->boolean('aktif')->default(true)->index();
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['kegiatan_ujian_cbt_id', 'kode'], 'sesi_kegiatan_ujian_cbt_kode_unik');
            $table->index(['kegiatan_ujian_cbt_id', 'urutan']);
        });

        Schema::create('ruang_kegiatan_ujian_cbt', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_ujian_cbt_id')
                ->constrained('kegiatan_ujian_cbt')
                ->cascadeOnDelete()
                ->cascadeOnUpdate();
            $table->string('kode', 40);
            $table->string('nama', 100);
            $table->string('lokasi')->nullable();
            $table->unsignedSmallInteger('kapasitas');
            $table->unsignedSmallInteger('urutan')->default(1);
            $table->boolean('aktif')->default(true)->index();
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique(['kegiatan_ujian_cbt_id', 'kode'], 'ruang_kegiatan_ujian_cbt_kode_unik');
            $table->index(['kegiatan_ujian_cbt_id', 'urutan']);
        });

        $waktu = now();
        DB::table('izin')->updateOrInsert(
            ['kode' => 'cbt.panitia'],
            [
                'nama' => 'Akses panitia ujian',
                'kelompok' => 'CBT',
                'deskripsi' => 'Mengakses kegiatan Ujian Terpusat tempat pegawai ditugaskan sebagai panitia.',
                'sistem' => true,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
        );
        DB::table('izin')->updateOrInsert(
            ['kode' => 'cbt.terpusat_lihat'],
            [
                'nama' => 'Lihat Ujian Terpusat',
                'kelompok' => 'CBT',
                'deskripsi' => 'Melihat persiapan dan pelaksanaan seluruh kegiatan Ujian Terpusat.',
                'sistem' => true,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
        );
        DB::table('peran')->updateOrInsert(
            ['kode' => 'panitia_ujian'],
            [
                'nama' => 'Panitia Ujian',
                'deskripsi' => 'Panitia sementara untuk mengelola rangkaian Ujian Terpusat yang ditugaskan sekolah.',
                'sistem' => true,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
        );

        $izinId = DB::table('izin')->where('kode', 'cbt.panitia')->value('id');
        $peranId = DB::table('peran')->where('kode', 'panitia_ujian')->value('id');

        if ($izinId && $peranId) {
            DB::table('peran_izin')->insertOrIgnore([
                'peran_id' => $peranId,
                'izin_id' => $izinId,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ]);
        }

        $izinLihatId = DB::table('izin')->where('kode', 'cbt.terpusat_lihat')->value('id');
        $peranLihatIds = DB::table('peran')
            ->whereIn('kode', ['administrator', 'pimpinan', 'wakil_pimpinan_kurikulum'])
            ->pluck('id');

        foreach ($peranLihatIds as $peranLihatId) {
            DB::table('peran_izin')->insertOrIgnore([
                'peran_id' => $peranLihatId,
                'izin_id' => $izinLihatId,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ruang_kegiatan_ujian_cbt');
        Schema::dropIfExists('sesi_kegiatan_ujian_cbt');
        Schema::dropIfExists('panitia_ujian_cbt');

        $izinIds = DB::table('izin')->whereIn('kode', ['cbt.panitia', 'cbt.terpusat_lihat'])->pluck('id');
        $peranId = DB::table('peran')->where('kode', 'panitia_ujian')->value('id');

        if ($peranId) {
            DB::table('pengguna_peran')->where('peran_id', $peranId)->delete();
            DB::table('peran_izin')->where('peran_id', $peranId)->delete();
            DB::table('peran')->where('id', $peranId)->delete();
        }

        if ($izinIds->isNotEmpty()) {
            DB::table('peran_izin')->whereIn('izin_id', $izinIds)->delete();
            DB::table('izin')->whereIn('id', $izinIds)->delete();
        }
    }
};
