<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jadwal_piket_guru', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_pelajaran_id')->constrained('tahun_pelajaran')->cascadeOnDelete();
            $table->foreignId('pegawai_id')->constrained('pegawai')->cascadeOnDelete();
            $table->string('hari', 10);
            $table->boolean('aktif')->default(true);
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->unique(
                ['tahun_pelajaran_id', 'hari', 'pegawai_id'],
                'jadwal_piket_guru_unik'
            );
            $table->index(
                ['tahun_pelajaran_id', 'hari', 'aktif'],
                'jadwal_piket_guru_hari_idx'
            );
        });

        Schema::create('riwayat_perubahan_absensi_siswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('absensi_siswa_id')->constrained('absensi_siswa')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswa')->cascadeOnDelete();
            $table->date('tanggal');
            $table->string('status_sebelum', 30)->nullable();
            $table->string('status_sesudah', 30);
            $table->string('sumber', 30);
            $table->text('catatan')->nullable();
            $table->foreignId('dibuat_oleh_pengguna_id')->nullable()->constrained('pengguna')->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['siswa_id', 'tanggal'],
                'riwayat_absensi_siswa_tanggal_idx'
            );
        });

        $izin = [
            [
                'kelompok' => 'Guru Piket',
                'nama' => 'Kelola jadwal guru piket',
                'kode' => 'piket_guru.kelola',
                'deskripsi' => 'Mengatur jadwal guru piket mingguan.',
            ],
            [
                'kelompok' => 'Guru Piket',
                'nama' => 'Lihat jadwal piket pribadi',
                'kode' => 'piket_guru.lihat_pribadi',
                'deskripsi' => 'Melihat jadwal piket milik akun guru sendiri.',
            ],
            [
                'kelompok' => 'Guru Piket',
                'nama' => 'Catat kehadiran saat piket',
                'kode' => 'piket_guru.catat_kehadiran',
                'deskripsi' => 'Mencatat sakit atau izin siswa pada hari piket.',
            ],
        ];

        foreach ($izin as $item) {
            DB::table('izin')->updateOrInsert(
                ['kode' => $item['kode']],
                $item + [
                    'aktif' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );
        }

        $this->berikanIzin('administrator', [
            'piket_guru.kelola',
            'piket_guru.lihat_pribadi',
            'piket_guru.catat_kehadiran',
        ]);
        $this->berikanIzin('wakil_pimpinan_kesiswaan', [
            'piket_guru.kelola',
        ]);
        $this->berikanIzin('guru_mapel', [
            'piket_guru.lihat_pribadi',
            'piket_guru.catat_kehadiran',
        ]);
    }

    public function down(): void
    {
        $kodeIzin = [
            'piket_guru.kelola',
            'piket_guru.lihat_pribadi',
            'piket_guru.catat_kehadiran',
        ];
        $izinIds = DB::table('izin')->whereIn('kode', $kodeIzin)->pluck('id');

        DB::table('peran_izin')->whereIn('izin_id', $izinIds)->delete();
        DB::table('izin')->whereIn('id', $izinIds)->delete();

        Schema::dropIfExists('riwayat_perubahan_absensi_siswa');
        Schema::dropIfExists('jadwal_piket_guru');
    }

    private function berikanIzin(string $kodePeran, array $kodeIzin): void
    {
        $peranId = DB::table('peran')->where('kode', $kodePeran)->value('id');

        if (! $peranId) {
            return;
        }

        $izinIds = DB::table('izin')->whereIn('kode', $kodeIzin)->pluck('id');

        foreach ($izinIds as $izinId) {
            DB::table('peran_izin')->insertOrIgnore([
                'peran_id' => $peranId,
                'izin_id' => $izinId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
};
