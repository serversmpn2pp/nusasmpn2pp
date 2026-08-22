<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const KODE_IZIN = ['omr.lihat', 'omr.kelola'];

    public function up(): void
    {
        $izinIds = DB::table('izin')->whereIn('kode', self::KODE_IZIN)->pluck('id');

        DB::table('peran_izin')->whereIn('izin_id', $izinIds)->delete();
        DB::table('izin')->whereIn('id', $izinIds)->delete();
    }

    public function down(): void
    {
        $waktu = now();
        $daftarIzin = [
            'omr.lihat' => [
                'kelompok' => 'Penilaian OMR',
                'nama' => 'Lihat ujian OMR',
                'deskripsi' => 'Melihat ujian dan kunci jawaban untuk pemeriksaan lembar jawaban.',
            ],
            'omr.kelola' => [
                'kelompok' => 'Penilaian OMR',
                'nama' => 'Kelola ujian OMR',
                'deskripsi' => 'Membuat ujian, mengatur kelas peserta, versi soal, dan kunci jawaban.',
            ],
        ];

        foreach ($daftarIzin as $kode => $izin) {
            DB::table('izin')->updateOrInsert(
                ['kode' => $kode],
                $izin + [
                    'sistem' => true,
                    'aktif' => true,
                    'created_at' => $waktu,
                    'updated_at' => $waktu,
                ],
            );
        }

        $izinIds = DB::table('izin')->whereIn('kode', self::KODE_IZIN)->pluck('id', 'kode');
        $peranIds = DB::table('peran')->whereIn('kode', [
            'administrator',
            'pimpinan',
            'wakil_pimpinan_kurikulum',
            'guru_mapel',
        ])->pluck('id', 'kode');
        $peta = [
            'administrator' => self::KODE_IZIN,
            'pimpinan' => ['omr.lihat'],
            'wakil_pimpinan_kurikulum' => ['omr.lihat'],
            'guru_mapel' => self::KODE_IZIN,
        ];

        foreach ($peta as $kodePeran => $kodeIzin) {
            foreach ($kodeIzin as $kode) {
                if (! isset($peranIds[$kodePeran], $izinIds[$kode])) {
                    continue;
                }

                DB::table('peran_izin')->insertOrIgnore([
                    'peran_id' => $peranIds[$kodePeran],
                    'izin_id' => $izinIds[$kode],
                    'created_at' => $waktu,
                    'updated_at' => $waktu,
                ]);
            }
        }
    }
};
