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
                'kelompok' => 'Penilaian OMR',
                'nama' => 'Lihat ujian OMR',
                'kode' => 'omr.lihat',
                'deskripsi' => 'Melihat ujian dan kunci jawaban untuk pemeriksaan lembar jawaban.',
            ],
            [
                'kelompok' => 'Penilaian OMR',
                'nama' => 'Kelola ujian OMR',
                'kode' => 'omr.kelola',
                'deskripsi' => 'Membuat ujian, mengatur kelas peserta, versi soal, dan kunci jawaban.',
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

        $izinIds = DB::table('izin')->whereIn('kode', ['omr.lihat', 'omr.kelola'])->pluck('id', 'kode');
        $peranIds = DB::table('peran')->pluck('id', 'kode');
        $peta = [
            'administrator' => ['omr.lihat', 'omr.kelola'],
            'pimpinan' => ['omr.lihat'],
            'wakil_pimpinan_kurikulum' => ['omr.lihat'],
            'guru_mapel' => ['omr.lihat', 'omr.kelola'],
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
        $izinIds = DB::table('izin')->whereIn('kode', ['omr.lihat', 'omr.kelola'])->pluck('id');

        DB::table('peran_izin')->whereIn('izin_id', $izinIds)->delete();
        DB::table('izin')->whereIn('id', $izinIds)->delete();
    }
};
