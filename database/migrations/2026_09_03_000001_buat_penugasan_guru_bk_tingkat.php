<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('penugasan_guru_bk_tingkat', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_pelajaran_id')->constrained('tahun_pelajaran')->cascadeOnUpdate()->restrictOnDelete();
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnUpdate()->restrictOnDelete();
            $table->unsignedTinyInteger('tingkat');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai')->nullable();
            $table->boolean('aktif')->default(true);
            $table->foreignId('dibuat_oleh_pengguna_id')->nullable()->constrained('pengguna')->cascadeOnUpdate()->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['tahun_pelajaran_id', 'pegawai_id', 'tingkat'],
                'penugasan_bk_tahun_pegawai_tingkat_unik',
            );
            $table->index(['tahun_pelajaran_id', 'tingkat', 'aktif'], 'penugasan_bk_tahun_tingkat_aktif');
        });

        $sekarang = now();
        DB::table('izin')->updateOrInsert(
            ['kode' => 'bk.penugasan_tingkat_kelola'],
            [
                'kelompok' => 'BK',
                'nama' => 'Kelola penugasan tingkat Guru BK',
                'deskripsi' => 'Menentukan tingkat siswa yang menjadi tanggung jawab pemeriksaan setiap Guru BK.',
                'sistem' => true,
                'aktif' => true,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ],
        );

        $izinId = DB::table('izin')->where('kode', 'bk.penugasan_tingkat_kelola')->value('id');
        $peranIds = DB::table('peran')
            ->whereIn('kode', ['administrator', 'wakil_pimpinan_kesiswaan'])
            ->pluck('id');

        foreach ($peranIds as $peranId) {
            DB::table('peran_izin')->insertOrIgnore([
                'peran_id' => $peranId,
                'izin_id' => $izinId,
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('penugasan_guru_bk_tingkat');

        $izinId = DB::table('izin')->where('kode', 'bk.penugasan_tingkat_kelola')->value('id');
        if ($izinId) {
            DB::table('peran_izin')->where('izin_id', $izinId)->delete();
            DB::table('izin')->where('id', $izinId)->delete();
        }
    }
};
