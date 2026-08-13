<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presensi_kegiatan_ibadah', function (Blueprint $table) {
            $table->foreignId('dikoreksi_oleh_pengguna_id')
                ->nullable()
                ->after('dipindai_oleh_pengguna_id')
                ->constrained('pengguna')
                ->nullOnDelete();
            $table->timestamp('dikoreksi_pada')->nullable()->after('user_agent');
            $table->text('catatan_koreksi')->nullable()->after('dikoreksi_pada');
        });

        Schema::create('riwayat_koreksi_kegiatan_ibadah', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presensi_kegiatan_ibadah_id')->nullable()->constrained('presensi_kegiatan_ibadah')->nullOnDelete();
            $table->foreignId('kegiatan_ibadah_id')->constrained('kegiatan_ibadah')->cascadeOnDelete();
            $table->foreignId('tahun_pelajaran_id')->constrained('tahun_pelajaran')->cascadeOnDelete();
            $table->foreignId('kelas_id')->nullable()->constrained('kelas')->nullOnDelete();
            $table->foreignId('anggota_kelas_id')->nullable()->constrained('anggota_kelas')->nullOnDelete();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->foreignId('diubah_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->date('tanggal');
            $table->string('tindakan', 20);
            $table->boolean('hadir_sebelum')->default(false);
            $table->boolean('hadir_sesudah')->default(false);
            $table->time('waktu_sebelum')->nullable();
            $table->time('waktu_sesudah')->nullable();
            $table->string('sumber_sebelum', 20)->nullable();
            $table->string('sumber_sesudah', 20)->nullable();
            $table->text('alasan');
            $table->timestamps();

            $table->index(['tanggal', 'kegiatan_ibadah_id'], 'riwayat_koreksi_ibadah_tanggal_idx');
            $table->index(['siswa_id', 'tanggal'], 'riwayat_koreksi_ibadah_siswa_idx');
        });

        DB::table('izin')->updateOrInsert(
            ['kode' => 'ibadah.koreksi'],
            [
                'kelompok' => 'Kegiatan Ibadah',
                'nama' => 'Koreksi presensi kegiatan ibadah',
                'deskripsi' => 'Menambah atau mengoreksi presensi kegiatan ibadah siswa dengan riwayat perubahan.',
                'aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        foreach (['administrator', 'wakil_pimpinan_kesiswaan', 'guru_mapel'] as $kodePeran) {
            $this->berikanIzin($kodePeran, 'ibadah.koreksi');
        }
    }

    public function down(): void
    {
        $izinId = DB::table('izin')->where('kode', 'ibadah.koreksi')->value('id');

        if ($izinId) {
            DB::table('peran_izin')->where('izin_id', $izinId)->delete();
            DB::table('izin')->where('id', $izinId)->delete();
        }

        Schema::dropIfExists('riwayat_koreksi_kegiatan_ibadah');

        Schema::table('presensi_kegiatan_ibadah', function (Blueprint $table) {
            $table->dropConstrainedForeignId('dikoreksi_oleh_pengguna_id');
            $table->dropColumn(['dikoreksi_pada', 'catatan_koreksi']);
        });
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
