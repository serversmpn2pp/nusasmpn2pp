<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('presensi_kegiatan_ibadah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jadwal_kegiatan_ibadah_id')->constrained('jadwal_kegiatan_ibadah')->cascadeOnDelete();
            $table->foreignId('kegiatan_ibadah_id')->constrained('kegiatan_ibadah')->cascadeOnDelete();
            $table->foreignId('tahun_pelajaran_id')->constrained('tahun_pelajaran')->cascadeOnDelete();
            $table->foreignId('kelas_id')->nullable()->constrained('kelas')->nullOnDelete();
            $table->foreignId('anggota_kelas_id')->nullable()->constrained('anggota_kelas')->nullOnDelete();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignId('dipindai_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->date('tanggal');
            $table->time('waktu_scan');
            $table->string('sumber', 20)->default('kamera');
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->unique(
                ['kegiatan_ibadah_id', 'siswa_id', 'tanggal'],
                'presensi_ibadah_siswa_harian_unik'
            );
            $table->index(['tanggal', 'kegiatan_ibadah_id'], 'presensi_ibadah_tanggal_idx');
        });

        Schema::create('log_scan_kegiatan_ibadah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presensi_kegiatan_ibadah_id')->nullable()->constrained('presensi_kegiatan_ibadah')->nullOnDelete();
            $table->foreignId('jadwal_kegiatan_ibadah_id')->nullable()->constrained('jadwal_kegiatan_ibadah')->nullOnDelete();
            $table->foreignId('kegiatan_ibadah_id')->nullable()->constrained('kegiatan_ibadah')->nullOnDelete();
            $table->foreignId('siswa_id')->nullable()->constrained('siswa')->nullOnDelete();
            $table->foreignId('dipindai_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->string('isi_scan', 100);
            $table->string('nisn', 40)->nullable();
            $table->timestamp('waktu_scan');
            $table->date('tanggal');
            $table->boolean('berhasil')->default(false);
            $table->string('status_scan', 50);
            $table->text('pesan')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['tanggal', 'kegiatan_ibadah_id', 'berhasil'], 'log_ibadah_tanggal_idx');
            $table->index(['nisn', 'waktu_scan'], 'log_ibadah_nisn_idx');
        });

        DB::table('izin')->updateOrInsert(
            ['kode' => 'ibadah.scan'],
            [
                'kelompok' => 'Kegiatan Ibadah',
                'nama' => 'Scan presensi kegiatan ibadah',
                'deskripsi' => 'Memindai QR kartu pelajar untuk presensi kegiatan ibadah siswa.',
                'aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        foreach (['administrator', 'wakil_pimpinan_kesiswaan', 'guru_mapel'] as $kodePeran) {
            $this->berikanIzin($kodePeran, 'ibadah.scan');
        }
    }

    public function down(): void
    {
        $izinId = DB::table('izin')->where('kode', 'ibadah.scan')->value('id');

        if ($izinId) {
            DB::table('peran_izin')->where('izin_id', $izinId)->delete();
            DB::table('izin')->where('id', $izinId)->delete();
        }

        Schema::dropIfExists('log_scan_kegiatan_ibadah');
        Schema::dropIfExists('presensi_kegiatan_ibadah');
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
