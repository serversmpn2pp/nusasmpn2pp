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
                'kelompok' => 'CBT',
                'nama' => 'Lihat CBT',
                'kode' => 'cbt.lihat',
                'deskripsi' => 'Melihat data dasar, bank soal, paket ujian, dan hasil CBT sesuai cakupan peran.',
            ],
            [
                'kelompok' => 'CBT',
                'nama' => 'Kelola CBT',
                'kode' => 'cbt.kelola',
                'deskripsi' => 'Mengelola pengaturan dasar, bank soal, paket ujian, peserta, dan pelaksanaan CBT.',
            ],
        ];

        foreach ($izin as $item) {
            DB::table('izin')->updateOrInsert(
                ['kode' => $item['kode']],
                $item + [
                    'sistem' => true,
                    'aktif' => true,
                    'created_at' => $waktu,
                    'updated_at' => $waktu,
                ],
            );
        }

        $izinIds = DB::table('izin')->whereIn('kode', ['cbt.lihat', 'cbt.kelola'])->pluck('id', 'kode');
        $peranIds = DB::table('peran')->pluck('id', 'kode');
        $peta = [
            'administrator' => ['cbt.lihat', 'cbt.kelola'],
            'pimpinan' => ['cbt.lihat'],
            'wakil_pimpinan_kurikulum' => ['cbt.lihat', 'cbt.kelola'],
            'guru_mapel' => ['cbt.lihat'],
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
        $izinIds = DB::table('izin')->whereIn('kode', ['cbt.lihat', 'cbt.kelola'])->pluck('id');

        DB::table('peran_izin')->whereIn('izin_id', $izinIds)->delete();
        DB::table('izin')->whereIn('id', $izinIds)->delete();
    }
};
