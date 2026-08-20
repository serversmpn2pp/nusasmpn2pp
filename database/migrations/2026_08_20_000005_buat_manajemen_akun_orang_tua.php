<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orang_tua_wali', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pengguna_id')
                ->unique()
                ->constrained('pengguna')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('siswa_acuan_username_id')
                ->nullable()
                ->unique()
                ->constrained('siswa')
                ->cascadeOnUpdate()
                ->nullOnDelete();
            $table->string('nama_lengkap');
            $table->string('nomor_wa', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('orang_tua_wali_siswa', function (Blueprint $table) {
            $table->foreignId('orang_tua_wali_id')
                ->constrained('orang_tua_wali')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('siswa_id')
                ->constrained('siswa')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->string('hubungan', 50)->default('wali');
            $table->boolean('utama')->default(true);
            $table->timestamps();

            $table->primary(['orang_tua_wali_id', 'siswa_id']);
            $table->index(['siswa_id', 'utama']);
        });

        $waktu = now();

        DB::table('peran')->updateOrInsert(
            ['kode' => 'orang_tua'],
            [
                'nama' => 'Orang Tua/Wali',
                'deskripsi' => 'Akses orang tua atau wali untuk memantau informasi anak yang terhubung.',
                'sistem' => true,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
        );

        $izin = [
            [
                'kode' => 'akun_orang_tua.lihat',
                'nama' => 'Lihat akun orang tua',
                'deskripsi' => 'Melihat status akun orang tua sesuai cakupan kelas.',
            ],
            [
                'kode' => 'akun_orang_tua.kelola',
                'nama' => 'Kelola akun orang tua',
                'deskripsi' => 'Membuat, mereset, mengaktifkan, dan menonaktifkan akun orang tua.',
            ],
            [
                'kode' => 'akun_orang_tua.cetak',
                'nama' => 'Cetak kredensial akun orang tua',
                'deskripsi' => 'Mencetak daftar username dan password awal akun orang tua sesuai cakupan kelas.',
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
            'akun_orang_tua.lihat',
            'akun_orang_tua.kelola',
            'akun_orang_tua.cetak',
        ], $waktu);
        $this->pasangIzin('wali_kelas', [
            'akun_orang_tua.lihat',
            'akun_orang_tua.cetak',
        ], $waktu);
        $this->pasangIzin('orang_tua', ['beranda.akses'], $waktu);
    }

    public function down(): void
    {
        $penggunaOrangTuaIds = Schema::hasTable('orang_tua_wali')
            ? DB::table('orang_tua_wali')->pluck('pengguna_id')
            : collect();
        $izinIds = DB::table('izin')
            ->whereIn('kode', [
                'akun_orang_tua.lihat',
                'akun_orang_tua.kelola',
                'akun_orang_tua.cetak',
            ])
            ->pluck('id');
        $peranOrangTuaId = DB::table('peran')->where('kode', 'orang_tua')->value('id');

        if ($penggunaOrangTuaIds->isNotEmpty()) {
            DB::table('pengguna_peran')->whereIn('pengguna_id', $penggunaOrangTuaIds)->delete();
            DB::table('pengguna')->whereIn('id', $penggunaOrangTuaIds)->delete();
        }

        Schema::dropIfExists('orang_tua_wali_siswa');
        Schema::dropIfExists('orang_tua_wali');

        DB::table('peran_izin')->whereIn('izin_id', $izinIds)->delete();

        if ($peranOrangTuaId) {
            DB::table('pengguna_peran')->where('peran_id', $peranOrangTuaId)->delete();
            DB::table('peran_izin')->where('peran_id', $peranOrangTuaId)->delete();
            DB::table('peran')->where('id', $peranOrangTuaId)->delete();
        }

        DB::table('izin')->whereIn('id', $izinIds)->delete();
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
