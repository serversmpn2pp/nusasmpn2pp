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
        Schema::create('izin', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('kode')->unique();
            $table->string('kelompok', 80)->index();
            $table->text('deskripsi')->nullable();
            $table->boolean('sistem')->default(true);
            $table->boolean('aktif')->default(true);
            $table->timestamps();
        });

        Schema::create('peran_izin', function (Blueprint $table) {
            $table->foreignId('peran_id')
                ->constrained('peran')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->foreignId('izin_id')
                ->constrained('izin')
                ->cascadeOnUpdate()
                ->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['peran_id', 'izin_id']);
        });

        $this->isiIzinBawaan();
        $this->hubungkanIzinPeranBawaan();
    }

    /**
     * Membatalkan migration.
     */
    public function down(): void
    {
        Schema::dropIfExists('peran_izin');
        Schema::dropIfExists('izin');
    }

    private function isiIzinBawaan(): void
    {
        $waktu = now();
        $izin = array_map(function (array $item) use ($waktu) {
            return array_merge($item, [
                'sistem' => true,
                'aktif' => true,
                'created_at' => $waktu,
                'updated_at' => $waktu,
            ]);
        }, $this->daftarIzinBawaan());

        DB::table('izin')->insert($izin);
    }

    private function hubungkanIzinPeranBawaan(): void
    {
        $waktu = now();
        $peranIds = DB::table('peran')->pluck('id', 'kode');
        $izinIds = DB::table('izin')->pluck('id', 'kode');

        foreach ($this->petaIzinPeran(array_keys($izinIds->all())) as $kodePeran => $daftarKodeIzin) {
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

    private function daftarIzinBawaan(): array
    {
        return [
            ['kelompok' => 'Umum', 'nama' => 'Akses beranda', 'kode' => 'beranda.akses', 'deskripsi' => 'Masuk ke halaman beranda NUSA.'],
            ['kelompok' => 'Akun', 'nama' => 'Lihat akun pegawai', 'kode' => 'akun.lihat', 'deskripsi' => 'Melihat daftar akun pegawai.'],
            ['kelompok' => 'Akun', 'nama' => 'Kelola akun pegawai', 'kode' => 'akun.kelola', 'deskripsi' => 'Membuat, mengaktifkan, menonaktifkan, dan reset akun pegawai.'],
            ['kelompok' => 'Akun', 'nama' => 'Lihat role', 'kode' => 'peran.lihat', 'deskripsi' => 'Melihat daftar role/peran.'],
            ['kelompok' => 'Akun', 'nama' => 'Kelola role dan izin', 'kode' => 'peran.kelola', 'deskripsi' => 'Menambah, mengubah, dan mengatur izin role.'],
            ['kelompok' => 'Pegawai', 'nama' => 'Lihat pegawai', 'kode' => 'pegawai.lihat', 'deskripsi' => 'Melihat data pegawai.'],
            ['kelompok' => 'Pegawai', 'nama' => 'Kelola pegawai', 'kode' => 'pegawai.kelola', 'deskripsi' => 'Menambah, mengubah, import, dan menonaktifkan pegawai.'],
            ['kelompok' => 'Siswa', 'nama' => 'Lihat siswa', 'kode' => 'siswa.lihat', 'deskripsi' => 'Melihat data siswa.'],
            ['kelompok' => 'Siswa', 'nama' => 'Kelola siswa', 'kode' => 'siswa.kelola', 'deskripsi' => 'Menambah, mengubah, import, dan menonaktifkan siswa.'],
            ['kelompok' => 'Siswa', 'nama' => 'Lihat kartu pelajar', 'kode' => 'kartu_pelajar.lihat', 'deskripsi' => 'Melihat halaman kartu pelajar.'],
            ['kelompok' => 'Siswa', 'nama' => 'Cetak kartu pelajar', 'kode' => 'kartu_pelajar.cetak', 'deskripsi' => 'Mencetak kartu pelajar siswa.'],
            ['kelompok' => 'Akademik', 'nama' => 'Lihat tahun pelajaran', 'kode' => 'tahun_pelajaran.lihat', 'deskripsi' => 'Melihat tahun pelajaran.'],
            ['kelompok' => 'Akademik', 'nama' => 'Kelola tahun pelajaran', 'kode' => 'tahun_pelajaran.kelola', 'deskripsi' => 'Mengelola tahun pelajaran.'],
            ['kelompok' => 'Akademik', 'nama' => 'Lihat kelas', 'kode' => 'kelas.lihat', 'deskripsi' => 'Melihat data kelas.'],
            ['kelompok' => 'Akademik', 'nama' => 'Kelola kelas', 'kode' => 'kelas.kelola', 'deskripsi' => 'Mengelola kelas dan anggota kelas.'],
            ['kelompok' => 'Akademik', 'nama' => 'Kelola kenaikan kelas', 'kode' => 'kenaikan_kelas.kelola', 'deskripsi' => 'Mengelola kenaikan atau penempatan kelas.'],
            ['kelompok' => 'Akademik', 'nama' => 'Lihat mata pelajaran', 'kode' => 'mata_pelajaran.lihat', 'deskripsi' => 'Melihat mata pelajaran.'],
            ['kelompok' => 'Akademik', 'nama' => 'Kelola mata pelajaran', 'kode' => 'mata_pelajaran.kelola', 'deskripsi' => 'Mengelola mata pelajaran.'],
            ['kelompok' => 'Akademik', 'nama' => 'Lihat guru mapel', 'kode' => 'guru_mapel.lihat', 'deskripsi' => 'Melihat pembagian guru mata pelajaran.'],
            ['kelompok' => 'Akademik', 'nama' => 'Kelola guru mapel', 'kode' => 'guru_mapel.kelola', 'deskripsi' => 'Mengelola guru mata pelajaran.'],
            ['kelompok' => 'Nilai', 'nama' => 'Lihat nilai', 'kode' => 'nilai.lihat', 'deskripsi' => 'Melihat data nilai.'],
            ['kelompok' => 'Nilai', 'nama' => 'Input nilai', 'kode' => 'nilai.input', 'deskripsi' => 'Menginput nilai siswa.'],
            ['kelompok' => 'Nilai', 'nama' => 'Rekap nilai', 'kode' => 'nilai.rekap', 'deskripsi' => 'Melihat rekap nilai rapor.'],
            ['kelompok' => 'Nilai', 'nama' => 'Kelola skema bobot nilai', 'kode' => 'nilai.skema_kelola', 'deskripsi' => 'Mengelola skema bobot nilai.'],
            ['kelompok' => 'Nilai', 'nama' => 'Kelola komponen nilai', 'kode' => 'nilai.komponen_kelola', 'deskripsi' => 'Mengelola komponen nilai.'],
            ['kelompok' => 'Absensi', 'nama' => 'Lihat absensi', 'kode' => 'absensi.lihat', 'deskripsi' => 'Melihat data absensi.'],
            ['kelompok' => 'Absensi', 'nama' => 'Scan absensi', 'kode' => 'absensi.scan', 'deskripsi' => 'Membuka dan menggunakan halaman scan absensi.'],
            ['kelompok' => 'Absensi', 'nama' => 'Koreksi absensi', 'kode' => 'absensi.koreksi', 'deskripsi' => 'Mengoreksi absensi siswa.'],
            ['kelompok' => 'Absensi', 'nama' => 'Kelola pengaturan absensi', 'kode' => 'absensi.pengaturan_kelola', 'deskripsi' => 'Mengatur jam dan hari absensi.'],
            ['kelompok' => 'Absensi', 'nama' => 'Laporan absensi', 'kode' => 'absensi.laporan', 'deskripsi' => 'Melihat laporan absensi.'],
            ['kelompok' => 'Laporan', 'nama' => 'Export laporan', 'kode' => 'laporan.export', 'deskripsi' => 'Export laporan ke Excel atau format lain.'],
            ['kelompok' => 'BK', 'nama' => 'Lihat data BK', 'kode' => 'bk.lihat', 'deskripsi' => 'Melihat catatan dan pembinaan siswa.'],
            ['kelompok' => 'BK', 'nama' => 'Kelola data BK', 'kode' => 'bk.kelola', 'deskripsi' => 'Mengelola catatan pembinaan dan konseling siswa.'],
            ['kelompok' => 'Sarpras', 'nama' => 'Lihat sarpras', 'kode' => 'sarpras.lihat', 'deskripsi' => 'Melihat data sarana dan prasarana.'],
            ['kelompok' => 'Sarpras', 'nama' => 'Kelola sarpras', 'kode' => 'sarpras.kelola', 'deskripsi' => 'Mengelola sarana dan prasarana.'],
            ['kelompok' => 'Sarpras', 'nama' => 'Lihat barang', 'kode' => 'barang.lihat', 'deskripsi' => 'Melihat data barang/inventaris.'],
            ['kelompok' => 'Sarpras', 'nama' => 'Kelola barang', 'kode' => 'barang.kelola', 'deskripsi' => 'Mengelola barang/inventaris.'],
            ['kelompok' => 'Sarpras', 'nama' => 'Kelola peminjaman barang', 'kode' => 'barang.peminjaman_kelola', 'deskripsi' => 'Mengelola pinjam-kembali barang.'],
            ['kelompok' => 'Kurikulum', 'nama' => 'Lihat perangkat ajar', 'kode' => 'perangkat_ajar.lihat', 'deskripsi' => 'Melihat perangkat ajar guru.'],
            ['kelompok' => 'Kurikulum', 'nama' => 'Upload perangkat ajar', 'kode' => 'perangkat_ajar.upload', 'deskripsi' => 'Mengunggah perangkat ajar.'],
            ['kelompok' => 'Kurikulum', 'nama' => 'Periksa perangkat ajar', 'kode' => 'perangkat_ajar.periksa', 'deskripsi' => 'Memeriksa perangkat ajar guru.'],
            ['kelompok' => 'Keamanan', 'nama' => 'Lihat keamanan', 'kode' => 'keamanan.lihat', 'deskripsi' => 'Melihat data keamanan sekolah.'],
            ['kelompok' => 'Keamanan', 'nama' => 'Kelola keamanan', 'kode' => 'keamanan.kelola', 'deskripsi' => 'Mengelola catatan keamanan sekolah.'],
            ['kelompok' => 'Kebersihan', 'nama' => 'Lihat kebersihan', 'kode' => 'kebersihan.lihat', 'deskripsi' => 'Melihat jadwal atau laporan kebersihan.'],
            ['kelompok' => 'Kebersihan', 'nama' => 'Kelola kebersihan', 'kode' => 'kebersihan.kelola', 'deskripsi' => 'Mengelola jadwal atau laporan kebersihan.'],
        ];
    }

    private function petaIzinPeran(array $semuaKodeIzin): array
    {
        $izinLihat = array_values(array_filter($semuaKodeIzin, function (string $kode) {
            return str_ends_with($kode, '.lihat')
                || in_array($kode, ['beranda.akses', 'nilai.rekap', 'absensi.laporan', 'laporan.export'], true);
        }));

        return [
            'administrator' => $semuaKodeIzin,
            'pimpinan' => $izinLihat,
            'wakil_pimpinan_kesiswaan' => [
                'beranda.akses',
                'siswa.lihat',
                'kartu_pelajar.lihat',
                'kartu_pelajar.cetak',
                'absensi.lihat',
                'absensi.koreksi',
                'absensi.laporan',
                'bk.lihat',
                'bk.kelola',
                'laporan.export',
            ],
            'wakil_pimpinan_sarana_prasarana' => [
                'beranda.akses',
                'sarpras.lihat',
                'sarpras.kelola',
                'barang.lihat',
                'barang.kelola',
                'barang.peminjaman_kelola',
                'laporan.export',
            ],
            'wakil_pimpinan_kurikulum' => [
                'beranda.akses',
                'tahun_pelajaran.lihat',
                'kelas.lihat',
                'mata_pelajaran.lihat',
                'guru_mapel.lihat',
                'nilai.lihat',
                'nilai.rekap',
                'perangkat_ajar.lihat',
                'perangkat_ajar.periksa',
                'laporan.export',
            ],
            'guru_mapel' => [
                'beranda.akses',
                'kelas.lihat',
                'mata_pelajaran.lihat',
                'guru_mapel.lihat',
                'nilai.lihat',
                'nilai.input',
                'nilai.rekap',
                'perangkat_ajar.upload',
            ],
            'wali_kelas' => [
                'beranda.akses',
                'siswa.lihat',
                'kelas.lihat',
                'nilai.lihat',
                'nilai.rekap',
                'absensi.lihat',
                'absensi.laporan',
                'bk.lihat',
            ],
            'bk' => [
                'beranda.akses',
                'siswa.lihat',
                'absensi.lihat',
                'bk.lihat',
                'bk.kelola',
                'laporan.export',
            ],
            'pegawai' => [
                'beranda.akses',
            ],
            'satpam' => [
                'beranda.akses',
                'absensi.scan',
                'absensi.lihat',
                'keamanan.lihat',
                'keamanan.kelola',
            ],
            'petugas_kebersihan' => [
                'beranda.akses',
                'sarpras.lihat',
                'kebersihan.lihat',
                'kebersihan.kelola',
            ],
        ];
    }
};
