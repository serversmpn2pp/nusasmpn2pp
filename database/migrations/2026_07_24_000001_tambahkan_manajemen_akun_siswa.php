<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengguna', function (Blueprint $table) {
            $table->foreignId('siswa_id')
                ->nullable()
                ->after('pegawai_id')
                ->unique()
                ->constrained('siswa')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->text('kata_sandi_awal')->nullable()->after('kata_sandi');
            $table->boolean('wajib_ganti_kata_sandi')->default(false)->after('kata_sandi_awal');
        });

        $waktu = now();

        DB::table('peran')->updateOrInsert(
            ['kode' => 'siswa'],
            [
                'nama' => 'Siswa',
                'deskripsi' => 'Akses siswa untuk melihat data akademik dan kesiswaan miliknya sendiri.',
                'sistem' => true,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
        );

        $izin = [
            [
                'kode' => 'akun_siswa.lihat',
                'nama' => 'Lihat akun siswa',
                'deskripsi' => 'Melihat status akun siswa sesuai cakupan kelas.',
            ],
            [
                'kode' => 'akun_siswa.kelola',
                'nama' => 'Kelola akun siswa',
                'deskripsi' => 'Membuat, mereset, mengaktifkan, dan menonaktifkan akun siswa.',
            ],
            [
                'kode' => 'akun_siswa.cetak',
                'nama' => 'Cetak kredensial akun siswa',
                'deskripsi' => 'Mencetak daftar username dan password awal akun siswa sesuai cakupan kelas.',
            ],
        ];

        foreach ($izin as $item) {
            DB::table('izin')->updateOrInsert(
                ['kode' => $item['kode']],
                [
                    'kelompok' => 'Akun',
                    'nama' => $item['nama'],
                    'deskripsi' => $item['deskripsi'],
                    'sistem' => true,
                    'aktif' => true,
                    'created_at' => $waktu,
                    'updated_at' => $waktu,
                ],
            );
        }

        $this->pasangIzin('administrator', [
            'akun_siswa.lihat',
            'akun_siswa.kelola',
            'akun_siswa.cetak',
        ], $waktu);
        $this->pasangIzin('wali_kelas', [
            'akun_siswa.lihat',
            'akun_siswa.cetak',
        ], $waktu);
        $this->pasangIzin('siswa', ['beranda.akses'], $waktu);
    }

    public function down(): void
    {
        $izinIds = DB::table('izin')
            ->whereIn('kode', [
                'akun_siswa.lihat',
                'akun_siswa.kelola',
                'akun_siswa.cetak',
            ])
            ->pluck('id');
        $peranSiswaId = DB::table('peran')->where('kode', 'siswa')->value('id');

        DB::table('peran_izin')->whereIn('izin_id', $izinIds)->delete();

        if ($peranSiswaId) {
            DB::table('pengguna_peran')->where('peran_id', $peranSiswaId)->delete();
            DB::table('peran_izin')->where('peran_id', $peranSiswaId)->delete();
            DB::table('peran')->where('id', $peranSiswaId)->delete();
        }

        DB::table('izin')->whereIn('id', $izinIds)->delete();

        Schema::table('pengguna', function (Blueprint $table) {
            $table->dropConstrainedForeignId('siswa_id');
            $table->dropColumn(['kata_sandi_awal', 'wajib_ganti_kata_sandi']);
        });
    }

    private function pasangIzin(string $kodePeran, array $kodeIzin, $waktu): void
    {
        $peranId = DB::table('peran')->where('kode', $kodePeran)->value('id');

        if (! $peranId) {
            return;
        }

        DB::table('izin')
            ->whereIn('kode', $kodeIzin)
            ->pluck('id')
            ->each(function ($izinId) use ($peranId, $waktu) {
                DB::table('peran_izin')->insertOrIgnore([
                    'peran_id' => $peranId,
                    'izin_id' => $izinId,
                    'created_at' => $waktu,
                    'updated_at' => $waktu,
                ]);
            });
    }
};
