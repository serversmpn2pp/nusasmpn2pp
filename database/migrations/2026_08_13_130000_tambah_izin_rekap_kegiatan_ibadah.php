<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('izin')->updateOrInsert(
            ['kode' => 'ibadah.rekap'],
            [
                'kelompok' => 'Kegiatan Ibadah',
                'nama' => 'Lihat rekap kegiatan ibadah',
                'deskripsi' => 'Melihat rekap harian presensi kegiatan ibadah siswa per kelas.',
                'aktif' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );

        foreach (['administrator', 'wakil_pimpinan_kesiswaan', 'guru_mapel'] as $kodePeran) {
            $this->berikanIzin($kodePeran, 'ibadah.rekap');
        }
    }

    public function down(): void
    {
        $izinId = DB::table('izin')->where('kode', 'ibadah.rekap')->value('id');

        if (! $izinId) {
            return;
        }

        DB::table('peran_izin')->where('izin_id', $izinId)->delete();
        DB::table('izin')->where('id', $izinId)->delete();
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
