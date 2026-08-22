<?php

namespace Database\Seeders;

use App\Models\Izin;
use App\Models\JenisUjianCbt;
use App\Models\PengaturanInventaris;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\SumberPerolehanBarang;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $administrator = Pengguna::firstOrNew([
            'username' => 'administrator',
        ]);

        $administrator->fill([
            'nama' => 'Administrator NUSA',
            'peran' => 'administrator',
            'aktif' => true,
            'akun_sistem' => true,
        ]);

        if (! $administrator->exists) {
            $administrator->kata_sandi = Hash::make('administrator');
        }

        $administrator->save();

        $this->isiPeranBawaan();
        $this->isiIzinBawaan();
        $this->isiJenisUjianCbtBawaan();
        $this->isiPengaturanInventarisBawaan();
        $this->hubungkanIzinPeranBawaan();

        $administrator->daftarPeran()->syncWithoutDetaching([
            Peran::where('kode', 'administrator')->value('id'),
        ]);
        Pengguna::query()
            ->where('peran', 'pegawai')
            ->whereDoesntHave('daftarPeran', function ($query) {
                $query->where('kode', 'pegawai');
            })
            ->get()
            ->each(function (Pengguna $pengguna) {
                $pengguna->daftarPeran()->syncWithoutDetaching([
                    Peran::where('kode', 'pegawai')->value('id'),
                ]);
            });
        Pengguna::query()
            ->where('peran', 'siswa')
            ->whereDoesntHave('daftarPeran', function ($query) {
                $query->where('kode', 'siswa');
            })
            ->get()
            ->each(function (Pengguna $pengguna) {
                $pengguna->daftarPeran()->syncWithoutDetaching([
                    Peran::where('kode', 'siswa')->value('id'),
                ]);
            });
        Pengguna::query()
            ->where('peran', 'orang_tua')
            ->whereDoesntHave('daftarPeran', function ($query) {
                $query->where('kode', 'orang_tua');
            })
            ->get()
            ->each(function (Pengguna $pengguna) {
                $pengguna->daftarPeran()->syncWithoutDetaching([
                    Peran::where('kode', 'orang_tua')->value('id'),
                ]);
            });
    }

    private function isiPeranBawaan(): void
    {
        $peranBawaan = [
            [
                'nama' => 'Administrator',
                'kode' => 'administrator',
                'deskripsi' => 'Akses penuh untuk mengelola seluruh data dan pengaturan NUSA.',
            ],
            [
                'nama' => 'Pimpinan',
                'kode' => 'pimpinan',
                'deskripsi' => 'Monitoring seluruh data sekolah secara read-only.',
            ],
            [
                'nama' => 'Wakil Pimpinan Kesiswaan',
                'kode' => 'wakil_pimpinan_kesiswaan',
                'deskripsi' => 'Monitoring data kesiswaan, perilaku siswa, dan data BK.',
            ],
            [
                'nama' => 'Wakil Pimpinan Sarana Prasarana',
                'kode' => 'wakil_pimpinan_sarana_prasarana',
                'deskripsi' => 'Mengelola dan memonitor inventaris, peminjaman, serta keluar masuk barang.',
            ],
            [
                'nama' => 'Wakil Pimpinan Kurikulum',
                'kode' => 'wakil_pimpinan_kurikulum',
                'deskripsi' => 'Monitoring perangkat ajar, nilai, guru mapel, dan kelengkapan kurikulum.',
            ],
            [
                'nama' => 'Guru Mapel',
                'kode' => 'guru_mapel',
                'deskripsi' => 'Input dan melihat nilai sesuai mata pelajaran dan kelas yang diajar.',
            ],
            [
                'nama' => 'Wali Kelas',
                'kode' => 'wali_kelas',
                'deskripsi' => 'Melihat data kelas binaan, presensi, nilai, dan perilaku siswa.',
            ],
            [
                'nama' => 'BK',
                'kode' => 'bk',
                'deskripsi' => 'Mengelola dan memonitor catatan pembinaan, perilaku, dan konseling siswa.',
            ],
            [
                'nama' => 'Pegawai',
                'kode' => 'pegawai',
                'deskripsi' => 'Akses dasar untuk pegawai: beranda, profil, presensi pribadi, dan pelaporan kejadian siswa.',
            ],
            [
                'nama' => 'Siswa',
                'kode' => 'siswa',
                'deskripsi' => 'Akses siswa untuk melihat data akademik dan kesiswaan miliknya sendiri.',
            ],
            [
                'nama' => 'Orang Tua/Wali',
                'kode' => 'orang_tua',
                'deskripsi' => 'Akses orang tua atau wali untuk memantau informasi anak yang terhubung.',
            ],
            [
                'nama' => 'Satpam',
                'kode' => 'satpam',
                'deskripsi' => 'Akses petugas keamanan untuk fitur keamanan sekolah yang akan dikembangkan.',
            ],
            [
                'nama' => 'Petugas Kebersihan',
                'kode' => 'petugas_kebersihan',
                'deskripsi' => 'Akses petugas kebersihan untuk jadwal dan laporan area kerja yang akan dikembangkan.',
            ],
            [
                'nama' => 'Petugas Inventaris',
                'kode' => 'petugas_inventaris',
                'deskripsi' => 'Mengelola inventaris, stok, peminjaman, pengembalian, dan laporan sarana prasarana.',
            ],
        ];

        foreach ($peranBawaan as $item) {
            Peran::updateOrCreate(
                ['kode' => $item['kode']],
                [
                    'nama' => $item['nama'],
                    'deskripsi' => $item['deskripsi'],
                    'sistem' => true,
                    'aktif' => true,
                ],
            );
        }
    }

    private function isiIzinBawaan(): void
    {
        foreach ($this->daftarIzinBawaan() as $item) {
            Izin::updateOrCreate(
                ['kode' => $item['kode']],
                [
                    'nama' => $item['nama'],
                    'kelompok' => $item['kelompok'],
                    'deskripsi' => $item['deskripsi'],
                    'sistem' => true,
                    'aktif' => true,
                ],
            );
        }
    }

    private function hubungkanIzinPeranBawaan(): void
    {
        $semuaKodeIzin = Izin::pluck('kode')->all();

        foreach ($this->petaIzinPeran($semuaKodeIzin) as $kodePeran => $daftarKodeIzin) {
            $peran = Peran::where('kode', $kodePeran)->first();

            if (! $peran) {
                continue;
            }

            $izinIds = Izin::whereIn('kode', $daftarKodeIzin)->pluck('id')->all();
            $peran->izin()->syncWithoutDetaching($izinIds);
        }
    }

    private function isiJenisUjianCbtBawaan(): void
    {
        $jenisUjian = [
            ['kode' => 'STS', 'nama' => 'Sumatif Tengah Semester', 'deskripsi' => 'Ujian tengah semester yang nilainya dapat diterapkan ke komponen STS.', 'memerlukan_token' => true, 'dapat_diterapkan_ke_nilai' => true, 'tampil_di_kartu_peserta' => true, 'urutan' => 1],
            ['kode' => 'SAS', 'nama' => 'Sumatif Akhir Semester', 'deskripsi' => 'Ujian akhir semester yang nilainya dapat diterapkan ke komponen SAS.', 'memerlukan_token' => true, 'dapat_diterapkan_ke_nilai' => true, 'tampil_di_kartu_peserta' => true, 'urutan' => 2],
            ['kode' => 'SAJ', 'nama' => 'Sumatif Akhir Jenjang', 'deskripsi' => 'Ujian akhir jenjang untuk kelas IX yang nilainya dapat diterapkan ke komponen SAJ.', 'memerlukan_token' => true, 'dapat_diterapkan_ke_nilai' => true, 'tampil_di_kartu_peserta' => true, 'urutan' => 3],
            ['kode' => 'TKA', 'nama' => 'Tes Kemampuan Akademik', 'deskripsi' => 'Profil ujian untuk simulasi atau pelaksanaan TKA berbasis CBT.', 'memerlukan_token' => true, 'dapat_diterapkan_ke_nilai' => true, 'tampil_di_kartu_peserta' => true, 'urutan' => 4],
            ['kode' => 'SIMULASI_AN', 'nama' => 'Simulasi AN', 'deskripsi' => 'Latihan literasi dan numerasi yang dapat digunakan untuk persiapan asesmen nasional.', 'memerlukan_token' => true, 'dapat_diterapkan_ke_nilai' => false, 'tampil_di_kartu_peserta' => true, 'urutan' => 5],
            ['kode' => 'OSN', 'nama' => 'OSN', 'deskripsi' => 'Profil ujian untuk latihan atau seleksi olimpiade sains.', 'memerlukan_token' => true, 'dapat_diterapkan_ke_nilai' => false, 'tampil_di_kartu_peserta' => true, 'urutan' => 6],
        ];

        foreach ($jenisUjian as $item) {
            JenisUjianCbt::updateOrCreate(
                ['kode' => $item['kode']],
                $item + ['aktif' => true],
            );
        }
    }

    private function isiPengaturanInventarisBawaan(): void
    {
        PengaturanInventaris::utama();

        foreach ([
            ['kode' => 'BOS', 'nama' => 'BOS', 'deskripsi' => 'Barang yang diperoleh melalui dana Bantuan Operasional Sekolah.'],
            ['kode' => 'DAK', 'nama' => 'DAK', 'deskripsi' => 'Barang yang diperoleh melalui Dana Alokasi Khusus.'],
        ] as $item) {
            SumberPerolehanBarang::updateOrCreate(
                ['kode' => $item['kode']],
                $item + ['aktif' => true],
            );
        }
    }

    private function daftarIzinBawaan(): array
    {
        return [
            ['kelompok' => 'Umum', 'nama' => 'Akses beranda', 'kode' => 'beranda.akses', 'deskripsi' => 'Masuk ke halaman beranda NUSA.'],
            ['kelompok' => 'Akun', 'nama' => 'Lihat akun pegawai', 'kode' => 'akun.lihat', 'deskripsi' => 'Melihat daftar akun pegawai.'],
            ['kelompok' => 'Akun', 'nama' => 'Kelola akun pegawai', 'kode' => 'akun.kelola', 'deskripsi' => 'Membuat, mengaktifkan, menonaktifkan, dan reset akun pegawai.'],
            ['kelompok' => 'Akun', 'nama' => 'Lihat akun siswa', 'kode' => 'akun_siswa.lihat', 'deskripsi' => 'Melihat status akun siswa sesuai cakupan kelas.'],
            ['kelompok' => 'Akun', 'nama' => 'Kelola akun siswa', 'kode' => 'akun_siswa.kelola', 'deskripsi' => 'Membuat, mereset, mengaktifkan, dan menonaktifkan akun siswa.'],
            ['kelompok' => 'Akun', 'nama' => 'Cetak kredensial akun siswa', 'kode' => 'akun_siswa.cetak', 'deskripsi' => 'Mencetak daftar username dan password awal akun siswa sesuai cakupan kelas.'],
            ['kelompok' => 'Akun', 'nama' => 'Lihat akun orang tua', 'kode' => 'akun_orang_tua.lihat', 'deskripsi' => 'Melihat status akun orang tua sesuai cakupan kelas.'],
            ['kelompok' => 'Akun', 'nama' => 'Kelola akun orang tua', 'kode' => 'akun_orang_tua.kelola', 'deskripsi' => 'Membuat, mereset, mengaktifkan, dan menonaktifkan akun orang tua.'],
            ['kelompok' => 'Akun', 'nama' => 'Cetak kredensial akun orang tua', 'kode' => 'akun_orang_tua.cetak', 'deskripsi' => 'Mencetak daftar username dan password awal akun orang tua sesuai cakupan kelas.'],
            ['kelompok' => 'Akun', 'nama' => 'Lihat role', 'kode' => 'peran.lihat', 'deskripsi' => 'Melihat daftar role/peran.'],
            ['kelompok' => 'Akun', 'nama' => 'Kelola role dan izin', 'kode' => 'peran.kelola', 'deskripsi' => 'Menambah, mengubah, dan mengatur izin role.'],
            ['kelompok' => 'Keamanan', 'nama' => 'Lihat aktivitas login', 'kode' => 'aktivitas_login.lihat', 'deskripsi' => 'Melihat login terakhir serta riwayat login berhasil dan gagal pengguna NUSA.'],
            ['kelompok' => 'Keamanan', 'nama' => 'Kelola backup dan restore database', 'kode' => 'cadangan_database.kelola', 'deskripsi' => 'Membuat, mengunduh, menghapus, dan memulihkan cadangan database NUSA.'],
            ['kelompok' => 'Pegawai', 'nama' => 'Lihat pegawai', 'kode' => 'pegawai.lihat', 'deskripsi' => 'Melihat data pegawai.'],
            ['kelompok' => 'Pegawai', 'nama' => 'Kelola pegawai', 'kode' => 'pegawai.kelola', 'deskripsi' => 'Menambah, mengubah, import, dan menonaktifkan pegawai.'],
            ['kelompok' => 'Pegawai', 'nama' => 'Kelola profil pegawai sendiri', 'kode' => 'pegawai.profil', 'deskripsi' => 'Melihat dan memperbarui data pribadi akun pegawai sendiri.'],
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
            ['kelompok' => 'Akademik', 'nama' => 'Lihat jadwal pelajaran', 'kode' => 'jadwal.lihat', 'deskripsi' => 'Melihat jam pelajaran dan jadwal pelajaran.'],
            ['kelompok' => 'Akademik', 'nama' => 'Kelola jadwal pelajaran', 'kode' => 'jadwal.kelola', 'deskripsi' => 'Mengelola jam pelajaran dan jadwal pelajaran.'],
            ['kelompok' => 'Akademik', 'nama' => 'Lihat jadwal mengajar pribadi', 'kode' => 'jadwal.pribadi', 'deskripsi' => 'Melihat jadwal mengajar milik akun pegawai sendiri.'],
            ['kelompok' => 'Nilai', 'nama' => 'Lihat nilai', 'kode' => 'nilai.lihat', 'deskripsi' => 'Melihat data nilai.'],
            ['kelompok' => 'Nilai', 'nama' => 'Input nilai', 'kode' => 'nilai.input', 'deskripsi' => 'Menginput nilai siswa.'],
            ['kelompok' => 'Nilai', 'nama' => 'Rekap nilai', 'kode' => 'nilai.rekap', 'deskripsi' => 'Melihat rekap nilai rapor.'],
            ['kelompok' => 'Nilai', 'nama' => 'Kelola skema bobot nilai', 'kode' => 'nilai.skema_kelola', 'deskripsi' => 'Mengelola skema bobot nilai.'],
            ['kelompok' => 'Nilai', 'nama' => 'Kelola komponen nilai', 'kode' => 'nilai.komponen_kelola', 'deskripsi' => 'Mengelola komponen nilai.'],
            ['kelompok' => 'CBT', 'nama' => 'Lihat CBT', 'kode' => 'cbt.lihat', 'deskripsi' => 'Melihat data dasar, bank soal, paket ujian, dan hasil CBT sesuai cakupan peran.'],
            ['kelompok' => 'CBT', 'nama' => 'Kelola CBT', 'kode' => 'cbt.kelola', 'deskripsi' => 'Mengelola pengaturan dasar, bank soal, paket ujian, peserta, dan pelaksanaan CBT.'],
            ['kelompok' => 'CBT', 'nama' => 'Kelola soal CBT', 'kode' => 'cbt.soal_kelola', 'deskripsi' => 'Membuat dan mengelola bank soal CBT sesuai cakupan mata pelajaran.'],
            ['kelompok' => 'CBT', 'nama' => 'Catat presensi ujian CBT', 'kode' => 'cbt.presensi', 'deskripsi' => 'Memindai kartu pelajar dan mencatat kehadiran peserta pada ruang CBT yang diawasi.'],
            ['kelompok' => 'Presensi', 'nama' => 'Lihat presensi', 'kode' => 'absensi.lihat', 'deskripsi' => 'Melihat data presensi.'],
            ['kelompok' => 'Presensi', 'nama' => 'Scan presensi', 'kode' => 'absensi.scan', 'deskripsi' => 'Membuka dan menggunakan halaman scan presensi.'],
            ['kelompok' => 'Presensi', 'nama' => 'Koreksi presensi', 'kode' => 'absensi.koreksi', 'deskripsi' => 'Mengoreksi presensi siswa.'],
            ['kelompok' => 'Presensi', 'nama' => 'Kelola pengaturan presensi', 'kode' => 'absensi.pengaturan_kelola', 'deskripsi' => 'Mengatur jam dan hari presensi.'],
            ['kelompok' => 'Presensi', 'nama' => 'Laporan presensi', 'kode' => 'absensi.laporan', 'deskripsi' => 'Melihat laporan presensi.'],
            ['kelompok' => 'Presensi Pegawai', 'nama' => 'Lihat presensi pegawai pribadi', 'kode' => 'absensi_pegawai.pribadi', 'deskripsi' => 'Melihat rekap dan laporan presensi milik akun pegawai sendiri.'],
            ['kelompok' => 'Guru Piket', 'nama' => 'Kelola jadwal guru piket', 'kode' => 'piket_guru.kelola', 'deskripsi' => 'Mengatur jadwal guru piket mingguan.'],
            ['kelompok' => 'Guru Piket', 'nama' => 'Lihat jadwal piket pribadi', 'kode' => 'piket_guru.lihat_pribadi', 'deskripsi' => 'Melihat jadwal piket milik akun guru sendiri.'],
            ['kelompok' => 'Guru Piket', 'nama' => 'Catat kehadiran saat piket', 'kode' => 'piket_guru.catat_kehadiran', 'deskripsi' => 'Mencatat sakit atau izin siswa pada hari piket.'],
            ['kelompok' => 'Kegiatan Ibadah', 'nama' => 'Kelola kegiatan dan jadwal ibadah', 'kode' => 'ibadah.pengaturan_kelola', 'deskripsi' => 'Mengelola kegiatan ibadah siswa dan jadwal presensinya.'],
            ['kelompok' => 'Kegiatan Ibadah', 'nama' => 'Scan presensi kegiatan ibadah', 'kode' => 'ibadah.scan', 'deskripsi' => 'Memindai QR kartu pelajar untuk presensi kegiatan ibadah siswa.'],
            ['kelompok' => 'Kegiatan Ibadah', 'nama' => 'Lihat rekap kegiatan ibadah', 'kode' => 'ibadah.rekap', 'deskripsi' => 'Melihat rekap harian presensi kegiatan ibadah siswa per kelas.'],
            ['kelompok' => 'Kegiatan Ibadah', 'nama' => 'Koreksi presensi kegiatan ibadah', 'kode' => 'ibadah.koreksi', 'deskripsi' => 'Menambah atau mengoreksi presensi kegiatan ibadah siswa dengan riwayat perubahan.'],
            ['kelompok' => 'Laporan', 'nama' => 'Export laporan', 'kode' => 'laporan.export', 'deskripsi' => 'Export laporan ke Excel atau format lain.'],
            ['kelompok' => 'BK', 'nama' => 'Lihat data BK', 'kode' => 'bk.lihat', 'deskripsi' => 'Melihat catatan dan pembinaan siswa.'],
            ['kelompok' => 'BK', 'nama' => 'Kelola data BK', 'kode' => 'bk.kelola', 'deskripsi' => 'Mengelola catatan pembinaan dan konseling siswa.'],
            ['kelompok' => 'Pembinaan dan Poin', 'nama' => 'Sahkan pelanggaran berpoin', 'kode' => 'poin_siswa.sahkan_wakil', 'deskripsi' => 'Mengesahkan atau mengembalikan rekomendasi pelanggaran berpoin dari BK sebagai Wakil Kesiswaan.'],
            ['kelompok' => 'Sarpras', 'nama' => 'Lihat sarpras', 'kode' => 'sarpras.lihat', 'deskripsi' => 'Melihat data sarana dan prasarana.'],
            ['kelompok' => 'Sarpras', 'nama' => 'Kelola sarpras', 'kode' => 'sarpras.kelola', 'deskripsi' => 'Mengelola sarana dan prasarana.'],
            ['kelompok' => 'Sarpras', 'nama' => 'Lihat barang', 'kode' => 'barang.lihat', 'deskripsi' => 'Melihat data barang/inventaris.'],
            ['kelompok' => 'Sarpras', 'nama' => 'Kelola barang', 'kode' => 'barang.kelola', 'deskripsi' => 'Mengelola barang/inventaris.'],
            ['kelompok' => 'Sarpras', 'nama' => 'Kelola peminjaman barang', 'kode' => 'barang.peminjaman_kelola', 'deskripsi' => 'Mengelola pinjam-kembali barang.'],
            ['kelompok' => 'Kurikulum', 'nama' => 'Lihat perangkat ajar', 'kode' => 'perangkat_ajar.lihat', 'deskripsi' => 'Melihat perangkat ajar guru.'],
            ['kelompok' => 'Kurikulum', 'nama' => 'Upload perangkat ajar', 'kode' => 'perangkat_ajar.upload', 'deskripsi' => 'Mengunggah perangkat ajar.'],
            ['kelompok' => 'Kurikulum', 'nama' => 'Periksa perangkat ajar', 'kode' => 'perangkat_ajar.periksa', 'deskripsi' => 'Memeriksa perangkat ajar guru.'],
            ['kelompok' => 'Kurikulum', 'nama' => 'Kelola jenis perangkat ajar', 'kode' => 'perangkat_ajar.jenis_kelola', 'deskripsi' => 'Mengelola daftar jenis dokumen perangkat ajar yang perlu diunggah guru.'],
            ['kelompok' => 'Keamanan', 'nama' => 'Lihat keamanan', 'kode' => 'keamanan.lihat', 'deskripsi' => 'Melihat data keamanan sekolah.'],
            ['kelompok' => 'Keamanan', 'nama' => 'Kelola keamanan', 'kode' => 'keamanan.kelola', 'deskripsi' => 'Mengelola catatan keamanan sekolah.'],
            ['kelompok' => 'Kebersihan', 'nama' => 'Lihat kebersihan', 'kode' => 'kebersihan.lihat', 'deskripsi' => 'Melihat jadwal atau laporan kebersihan.'],
            ['kelompok' => 'Kebersihan', 'nama' => 'Kelola kebersihan', 'kode' => 'kebersihan.kelola', 'deskripsi' => 'Mengelola jadwal atau laporan kebersihan.'],
            ['kelompok' => 'Kurikulum', 'nama' => 'Kelola pernyataan survei pembelajaran', 'kode' => 'survei.pertanyaan_kelola', 'deskripsi' => 'Menambah, mengubah, mengurutkan, dan menonaktifkan pernyataan survei pembelajaran siswa.'],
            ['kelompok' => 'Kurikulum', 'nama' => 'Lihat hasil survei pembelajaran sendiri', 'kode' => 'survei.hasil_pribadi', 'deskripsi' => 'Melihat hasil survei anonim untuk mata pelajaran dan kelas yang diampu sendiri.'],
            ['kelompok' => 'Kurikulum', 'nama' => 'Monitoring survei pembelajaran', 'kode' => 'survei.monitor', 'deskripsi' => 'Memantau tingkat pengisian dan hasil anonim survei pembelajaran seluruh guru.'],
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
            'wakil_pimpinan_kesiswaan' => ['beranda.akses', 'siswa.lihat', 'kartu_pelajar.lihat', 'kartu_pelajar.cetak', 'absensi.lihat', 'absensi.koreksi', 'absensi.laporan', 'piket_guru.kelola', 'ibadah.pengaturan_kelola', 'ibadah.scan', 'ibadah.rekap', 'ibadah.koreksi', 'bk.lihat', 'bk.kelola', 'poin_siswa.sahkan_wakil', 'laporan.export'],
            'wakil_pimpinan_sarana_prasarana' => ['beranda.akses', 'sarpras.lihat', 'sarpras.kelola', 'barang.lihat', 'barang.kelola', 'barang.peminjaman_kelola', 'laporan.export'],
            'wakil_pimpinan_kurikulum' => ['beranda.akses', 'tahun_pelajaran.lihat', 'kelas.lihat', 'mata_pelajaran.lihat', 'guru_mapel.lihat', 'guru_mapel.kelola', 'jadwal.lihat', 'jadwal.kelola', 'nilai.lihat', 'nilai.rekap', 'cbt.lihat', 'cbt.kelola', 'cbt.soal_kelola', 'cbt.presensi', 'perangkat_ajar.lihat', 'perangkat_ajar.periksa', 'perangkat_ajar.jenis_kelola', 'survei.pertanyaan_kelola', 'survei.monitor', 'laporan.export'],
            'guru_mapel' => ['beranda.akses', 'kelas.lihat', 'mata_pelajaran.lihat', 'guru_mapel.lihat', 'jadwal.pribadi', 'piket_guru.lihat_pribadi', 'piket_guru.catat_kehadiran', 'ibadah.scan', 'ibadah.rekap', 'ibadah.koreksi', 'nilai.lihat', 'nilai.komponen_kelola', 'nilai.input', 'nilai.rekap', 'survei.hasil_pribadi', 'cbt.lihat', 'cbt.soal_kelola', 'cbt.presensi', 'perangkat_ajar.upload'],
            'wali_kelas' => ['beranda.akses', 'siswa.lihat', 'kelas.lihat', 'jadwal.lihat', 'nilai.lihat', 'nilai.rekap', 'absensi.lihat', 'absensi.koreksi', 'absensi.laporan', 'bk.lihat', 'akun_siswa.lihat', 'akun_siswa.cetak', 'akun_orang_tua.lihat', 'akun_orang_tua.cetak'],
            'bk' => ['beranda.akses', 'siswa.lihat', 'absensi.lihat', 'bk.lihat', 'bk.kelola', 'laporan.export'],
            'pegawai' => ['beranda.akses', 'pegawai.profil', 'absensi_pegawai.pribadi', 'poin_siswa.lapor'],
            'siswa' => ['beranda.akses'],
            'orang_tua' => ['beranda.akses'],
            'satpam' => ['beranda.akses', 'absensi.scan', 'absensi.lihat', 'keamanan.lihat', 'keamanan.kelola'],
            'petugas_kebersihan' => ['beranda.akses', 'absensi.scan', 'sarpras.lihat', 'kebersihan.lihat', 'kebersihan.kelola'],
            'petugas_inventaris' => ['beranda.akses', 'sarpras.lihat', 'sarpras.kelola', 'barang.lihat', 'barang.kelola', 'barang.peminjaman_kelola', 'laporan.export'],
        ];
    }
}
