<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $waktu = now();

        DB::table('peran')->updateOrInsert(
            ['kode' => 'petugas_inventaris'],
            [
                'nama' => 'Petugas Inventaris',
                'deskripsi' => 'Mengelola inventaris, stok, peminjaman, pengembalian, dan laporan sarana prasarana.',
                'sistem' => true,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
        );

        $peranId = DB::table('peran')->where('kode', 'petugas_inventaris')->value('id');
        $izinIds = DB::table('izin')
            ->whereIn('kode', [
                'beranda.akses',
                'sarpras.lihat',
                'sarpras.kelola',
                'barang.lihat',
                'barang.kelola',
                'barang.peminjaman_kelola',
                'laporan.export',
            ])
            ->pluck('id');

        foreach ($izinIds as $izinId) {
            DB::table('peran_izin')->insertOrIgnore([
                'peran_id' => $peranId,
                'izin_id' => $izinId,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ]);
        }
    }

    public function down(): void
    {
        $peranId = DB::table('peran')->where('kode', 'petugas_inventaris')->value('id');

        if (! $peranId) {
            return;
        }

        DB::table('pengguna_peran')->where('peran_id', $peranId)->delete();
        DB::table('peran_izin')->where('peran_id', $peranId)->delete();
        DB::table('peran')->where('id', $peranId)->delete();
    }
};
