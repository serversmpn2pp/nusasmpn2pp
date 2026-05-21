<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menjalankan migration.
     */
    public function up(): void
    {
        Schema::create('peran', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('kode')->unique();
            $table->text('deskripsi')->nullable();
            $table->boolean('sistem')->default(false);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('pengguna_peran', function (Blueprint $table) {
            $table->foreignId('pengguna_id')
                ->constrained('pengguna')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('peran_id')
                ->constrained('peran')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['pengguna_id', 'peran_id']);
        });

        $this->isiPeranBawaan();
        $this->hubungkanPeranPenggunaYangAda();
    }

    /**
     * Membatalkan migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('pengguna_peran');
        Schema::dropIfExists('peran');
    }

    private function isiPeranBawaan(): void
    {
        $waktu = now();

        DB::table('peran')->insert([
            [
                'nama' => 'Administrator',
                'kode' => 'administrator',
                'deskripsi' => 'Akses penuh untuk mengelola seluruh data dan pengaturan NUSA.',
                'sistem' => true,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
            [
                'nama' => 'Pimpinan',
                'kode' => 'pimpinan',
                'deskripsi' => 'Monitoring seluruh data sekolah secara read-only.',
                'sistem' => true,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
            [
                'nama' => 'Wakil Pimpinan Kesiswaan',
                'kode' => 'wakil_pimpinan_kesiswaan',
                'deskripsi' => 'Monitoring data kesiswaan, perilaku siswa, dan data BK.',
                'sistem' => true,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
            [
                'nama' => 'Wakil Pimpinan Sarana Prasarana',
                'kode' => 'wakil_pimpinan_sarana_prasarana',
                'deskripsi' => 'Mengelola dan memonitor inventaris, peminjaman, serta keluar masuk barang.',
                'sistem' => true,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
            [
                'nama' => 'Wakil Pimpinan Kurikulum',
                'kode' => 'wakil_pimpinan_kurikulum',
                'deskripsi' => 'Monitoring perangkat ajar, nilai, guru mapel, dan kelengkapan kurikulum.',
                'sistem' => true,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
            [
                'nama' => 'Guru Mapel',
                'kode' => 'guru_mapel',
                'deskripsi' => 'Input dan melihat nilai sesuai mata pelajaran dan kelas yang diajar.',
                'sistem' => true,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
            [
                'nama' => 'Wali Kelas',
                'kode' => 'wali_kelas',
                'deskripsi' => 'Melihat data kelas binaan, absensi, nilai, dan perilaku siswa.',
                'sistem' => true,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
            [
                'nama' => 'BK',
                'kode' => 'bk',
                'deskripsi' => 'Mengelola dan memonitor catatan pembinaan, perilaku, dan konseling siswa.',
                'sistem' => true,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
            [
                'nama' => 'Pegawai',
                'kode' => 'pegawai',
                'deskripsi' => 'Akses dasar untuk pegawai: beranda, profil, dan ganti kata sandi.',
                'sistem' => true,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
            [
                'nama' => 'Satpam',
                'kode' => 'satpam',
                'deskripsi' => 'Akses petugas keamanan untuk fitur keamanan sekolah yang akan dikembangkan.',
                'sistem' => true,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
            [
                'nama' => 'Petugas Kebersihan',
                'kode' => 'petugas_kebersihan',
                'deskripsi' => 'Akses petugas kebersihan untuk jadwal dan laporan area kerja yang akan dikembangkan.',
                'sistem' => true,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ],
        ]);
    }

    private function hubungkanPeranPenggunaYangAda(): void
    {
        $waktu = now();
        $peranAdministratorId = DB::table('peran')->where('kode', 'administrator')->value('id');
        $peranPegawaiId = DB::table('peran')->where('kode', 'pegawai')->value('id');

        DB::table('pengguna')
            ->where(function ($query) {
                $query->where('peran', 'administrator')
                    ->orWhere('akun_sistem', true);
            })
            ->orderBy('id')
            ->pluck('id')
            ->each(function ($penggunaId) use ($peranAdministratorId, $waktu) {
                DB::table('pengguna_peran')->insertOrIgnore([
                    'pengguna_id' => $penggunaId,
                    'peran_id' => $peranAdministratorId,
                    'created_at' => $waktu,
                    'updated_at' => $waktu,
                ]);
            });

        DB::table('pengguna')
            ->where('peran', 'pegawai')
            ->orderBy('id')
            ->pluck('id')
            ->each(function ($penggunaId) use ($peranPegawaiId, $waktu) {
                DB::table('pengguna_peran')->insertOrIgnore([
                    'pengguna_id' => $penggunaId,
                    'peran_id' => $peranPegawaiId,
                    'created_at' => $waktu,
                    'updated_at' => $waktu,
                ]);
            });
    }
};
