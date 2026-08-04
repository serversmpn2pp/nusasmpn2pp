<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $waktu = now();
        $izin = [
            [
                'kelompok' => 'Akademik',
                'nama' => 'Lihat jadwal pelajaran',
                'kode' => 'jadwal.lihat',
                'deskripsi' => 'Melihat jadwal pelajaran sesuai cakupan pengguna.',
                'sistem' => true,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
            [
                'kelompok' => 'Akademik',
                'nama' => 'Kelola jadwal pelajaran',
                'kode' => 'jadwal.kelola',
                'deskripsi' => 'Mengelola jadwal pelajaran.',
                'sistem' => true,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
        ];

        foreach ($izin as $item) {
            DB::table('izin')->updateOrInsert(
                ['kode' => $item['kode']],
                $item,
            );
        }

        $izinIds = DB::table('izin')->whereIn('kode', ['jadwal.lihat', 'jadwal.kelola'])->pluck('id', 'kode');
        $peranIds = DB::table('peran')->pluck('id', 'kode');

        $peta = [
            'administrator' => ['jadwal.lihat', 'jadwal.kelola'],
            'pimpinan' => ['jadwal.lihat'],
            'wakil_pimpinan_kurikulum' => ['jadwal.lihat', 'jadwal.kelola'],
            'guru_mapel' => ['jadwal.lihat'],
            'wali_kelas' => ['jadwal.lihat'],
        ];

        foreach ($peta as $kodePeran => $daftarKodeIzin) {
            $peranId = $peranIds[$kodePeran] ?? null;

            if (! $peranId) {
                continue;
            }

            foreach ($daftarKodeIzin as $kodeIzin) {
                $izinId = $izinIds[$kodeIzin] ?? null;

                if (! $izinId) {
                    continue;
                }

                DB::table('peran_izin')->insertOrIgnore([
                    'peran_id' => $peranId,
                    'izin_id' => $izinId,
                    'created_at' => $waktu,
                    'updated_at' => $waktu,
                ]);
            }
        }
    }

    public function down(): void
    {
        $izinIds = DB::table('izin')->whereIn('kode', ['jadwal.lihat', 'jadwal.kelola'])->pluck('id');
        DB::table('peran_izin')->whereIn('izin_id', $izinIds)->delete();
        DB::table('izin')->whereIn('kode', ['jadwal.lihat', 'jadwal.kelola'])->delete();
    }
};
