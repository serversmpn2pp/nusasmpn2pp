<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kegiatan_ibadah', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->string('nama', 150);
            $table->boolean('aktif')->default(true);
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index(['aktif', 'nama']);
        });

        Schema::create('jadwal_kegiatan_ibadah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kegiatan_ibadah_id')->constrained('kegiatan_ibadah')->cascadeOnDelete();
            $table->foreignId('tahun_pelajaran_id')->constrained('tahun_pelajaran')->cascadeOnDelete();
            $table->string('hari', 10);
            $table->unsignedTinyInteger('urutan_hari');
            $table->time('jam_scan_mulai');
            $table->time('jam_pelaksanaan');
            $table->time('jam_scan_selesai');
            $table->boolean('aktif')->default(true);
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique(
                ['kegiatan_ibadah_id', 'tahun_pelajaran_id', 'hari'],
                'jadwal_kegiatan_ibadah_unik'
            );
            $table->index(
                ['tahun_pelajaran_id', 'hari', 'aktif'],
                'jadwal_ibadah_hari_aktif_idx'
            );
        });

        DB::table('kegiatan_ibadah')->insert([
            'kode' => 'sholat_duhur',
            'nama' => 'Sholat Duhur Berjamaah',
            'aktif' => true,
            'keterangan' => 'Presensi satu kali setelah siswa melaksanakan sholat Duhur berjamaah.',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('izin')->updateOrInsert(
            ['kode' => 'ibadah.pengaturan_kelola'],
            [
                'kelompok' => 'Kegiatan Ibadah',
                'nama' => 'Kelola kegiatan dan jadwal ibadah',
                'deskripsi' => 'Mengelola kegiatan ibadah siswa dan jadwal presensinya.',
                'aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        $this->berikanIzin('administrator', 'ibadah.pengaturan_kelola');
        $this->berikanIzin('wakil_pimpinan_kesiswaan', 'ibadah.pengaturan_kelola');
    }

    public function down(): void
    {
        $izinId = DB::table('izin')->where('kode', 'ibadah.pengaturan_kelola')->value('id');

        if ($izinId) {
            DB::table('peran_izin')->where('izin_id', $izinId)->delete();
            DB::table('izin')->where('id', $izinId)->delete();
        }

        Schema::dropIfExists('jadwal_kegiatan_ibadah');
        Schema::dropIfExists('kegiatan_ibadah');
    }

    private function berikanIzin(string $kodePeran, string $kodeIzin): void
    {
        $peranId = DB::table('peran')->where('kode', $kodePeran)->value('id');
        $izinId = DB::table('izin')->where('kode', $kodeIzin)->value('id');

        if (! $peranId || ! $izinId) {
            return;
        }

        DB::table('peran_izin')->insertOrIgnore([
            'peran_id' => $peranId,
            'izin_id' => $izinId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
