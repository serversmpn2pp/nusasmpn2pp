<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AkunPegawaiController;
use App\Http\Controllers\AnggotaKelasController;
use App\Http\Controllers\AutentikasiController;
use App\Http\Controllers\BarangController;
use App\Http\Controllers\BerandaController;
use App\Http\Controllers\GuruMataPelajaranController;
use App\Http\Controllers\InputNilaiController;
use App\Http\Controllers\JadwalPelajaranController;
use App\Http\Controllers\JadwalSayaController;
use App\Http\Controllers\JamPelajaranController;
use App\Http\Controllers\JenisPerangkatAjarController;
use App\Http\Controllers\KartuPelajarController;
use App\Http\Controllers\KartuPegawaiController;
use App\Http\Controllers\KenaikanKelasController;
use App\Http\Controllers\KategoriBarangController;
use App\Http\Controllers\KategoriPembinaanSiswaController;
use App\Http\Controllers\KelasController;
use App\Http\Controllers\KomponenNilaiController;
use App\Http\Controllers\LaporanAbsensiController;
use App\Http\Controllers\LaporanAbsensiPegawaiBulananController;
use App\Http\Controllers\LaporanPembinaanSiswaController;
use App\Http\Controllers\LabelBarcodeInventarisController;
use App\Http\Controllers\LokasiBarangController;
use App\Http\Controllers\MataPelajaranController;
use App\Http\Controllers\MutasiStokBarangController;
use App\Http\Controllers\NotifikasiAbsensiSiswaController;
use App\Http\Controllers\PegawaiController;
use App\Http\Controllers\PeminjamanBarangController;
use App\Http\Controllers\PerangkatAjarSayaController;
use App\Http\Controllers\PemeriksaanPerangkatAjarController;
use App\Http\Controllers\PengembalianBarangController;
use App\Http\Controllers\PengaturanAbsensiController;
use App\Http\Controllers\PengaturanAbsensiPegawaiController;
use App\Http\Controllers\PenempatanSiswaController;
use App\Http\Controllers\PeranController;
use App\Http\Controllers\ProfilPegawaiController;
use App\Http\Controllers\RekapAbsensiPegawaiHarianController;
use App\Http\Controllers\RekapAbsensiHarianController;
use App\Http\Controllers\RekapNilaiRaporController;
use App\Http\Controllers\ScanAbsensiController;
use App\Http\Controllers\ScanAbsensiPegawaiController;
use App\Http\Controllers\SaldoStokBarangController;
use App\Http\Controllers\SatuanBarangController;
use App\Http\Controllers\SkemaBobotNilaiController;
use App\Http\Controllers\SiswaController;
use App\Http\Controllers\TahunPelajaranController;
use App\Http\Controllers\TindakLanjutPembinaanSiswaController;
use App\Http\Controllers\UnitBarangController;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('beranda');
    }

    return redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [AutentikasiController::class, 'createLogin'])->name('login');
    Route::post('login', [AutentikasiController::class, 'storeLogin'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('logout', [AutentikasiController::class, 'logout'])->name('logout');
    Route::get('ganti-kata-sandi', [AutentikasiController::class, 'editKataSandi'])->name('kata-sandi.edit');
    Route::put('ganti-kata-sandi', [AutentikasiController::class, 'updateKataSandi'])->name('kata-sandi.update');

    Route::middleware('kata_sandi_bukan_default')->group(function () {
        Route::get('beranda', [BerandaController::class, 'index'])
            ->middleware('izin:beranda.akses')
            ->name('beranda');

        Route::middleware('izin:akun.lihat,akun.kelola')->group(function () {
            Route::get('akun-pegawai', [AkunPegawaiController::class, 'index'])->name('akun-pegawai.index');
        });

        Route::middleware('izin:akun.kelola')->group(function () {
            Route::post('akun-pegawai/buat-massal', [AkunPegawaiController::class, 'storeMassal'])->name('akun-pegawai.buat-massal');
            Route::post('akun-pegawai/{pegawai}', [AkunPegawaiController::class, 'store'])->name('akun-pegawai.store');
            Route::patch('akun-pegawai/{pengguna}/reset-password', [AkunPegawaiController::class, 'resetPassword'])->name('akun-pegawai.reset-password');
            Route::patch('akun-pegawai/{pengguna}/status', [AkunPegawaiController::class, 'ubahStatus'])->name('akun-pegawai.status');
            Route::patch('akun-pegawai/{pengguna}/peran', [AkunPegawaiController::class, 'updatePeran'])->name('akun-pegawai.peran.update');
        });

        Route::resource('peran', PeranController::class)
            ->only(['index'])
            ->middleware('izin:peran.lihat,peran.kelola');
        Route::resource('peran', PeranController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:peran.kelola');

        Route::middleware('izin:pegawai.kelola')->group(function () {
            Route::get('pegawai/import', [PegawaiController::class, 'createImport'])->name('pegawai.import.create');
            Route::post('pegawai/import', [PegawaiController::class, 'storeImport'])->name('pegawai.import.store');
        });
        Route::middleware('izin:pegawai.profil')->group(function () {
            Route::get('profil-saya', [ProfilPegawaiController::class, 'edit'])->name('profil-pegawai.edit');
            Route::put('profil-saya', [ProfilPegawaiController::class, 'update'])->name('profil-pegawai.update');
        });
        Route::resource('pegawai', PegawaiController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:pegawai.kelola');
        Route::resource('pegawai', PegawaiController::class)
            ->only(['index', 'show'])
            ->middleware('izin:pegawai.lihat,pegawai.kelola');

        Route::middleware('izin:siswa.kelola')->group(function () {
            Route::get('siswa/import', [SiswaController::class, 'createImport'])->name('siswa.import.create');
            Route::post('siswa/import', [SiswaController::class, 'storeImport'])->name('siswa.import.store');
        });
        Route::resource('siswa', SiswaController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:siswa.kelola');
        Route::resource('siswa', SiswaController::class)
            ->only(['index', 'show'])
            ->middleware('izin:siswa.lihat,siswa.kelola');

        Route::get('kartu-pelajar', [KartuPelajarController::class, 'index'])
            ->middleware('izin:kartu_pelajar.lihat,kartu_pelajar.cetak')
            ->name('kartu-pelajar.index');
        Route::get('kartu-pegawai', [KartuPegawaiController::class, 'index'])
            ->middleware('izin:pegawai.lihat,pegawai.kelola')
            ->name('kartu-pegawai.index');

        Route::resource('tahun-pelajaran', TahunPelajaranController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:tahun_pelajaran.kelola');
        Route::resource('tahun-pelajaran', TahunPelajaranController::class)
            ->only(['index', 'show'])
            ->middleware('izin:tahun_pelajaran.lihat,tahun_pelajaran.kelola');

        Route::resource('mata-pelajaran', MataPelajaranController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:mata_pelajaran.kelola');
        Route::resource('mata-pelajaran', MataPelajaranController::class)
            ->only(['index', 'show'])
            ->middleware('izin:mata_pelajaran.lihat,mata_pelajaran.kelola');

        Route::resource('guru-mata-pelajaran', GuruMataPelajaranController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:guru_mapel.kelola');
        Route::resource('guru-mata-pelajaran', GuruMataPelajaranController::class)
            ->only(['index', 'show'])
            ->middleware('izin:guru_mapel.lihat,guru_mapel.kelola');
        Route::resource('jam-pelajaran', JamPelajaranController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:jadwal.kelola');
        Route::resource('jam-pelajaran', JamPelajaranController::class)
            ->only(['index', 'show'])
            ->middleware('izin:jadwal.lihat,jadwal.kelola');
        Route::resource('jadwal-pelajaran', JadwalPelajaranController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:jadwal.kelola');
        Route::resource('jadwal-pelajaran', JadwalPelajaranController::class)
            ->only(['index', 'show'])
            ->middleware('izin:jadwal.lihat,jadwal.kelola');
        Route::get('jadwal-saya', [JadwalSayaController::class, 'index'])
            ->middleware('izin:jadwal.pribadi')
            ->name('jadwal-saya.index');
        Route::resource('jenis-perangkat-ajar', JenisPerangkatAjarController::class)
            ->middleware('izin:perangkat_ajar.jenis_kelola');
        Route::middleware('izin:perangkat_ajar.upload')->group(function () {
            Route::get('perangkat-ajar-saya', [PerangkatAjarSayaController::class, 'index'])->name('perangkat-ajar-saya.index');
            Route::get('perangkat-ajar-saya/create', [PerangkatAjarSayaController::class, 'create'])->name('perangkat-ajar-saya.create');
            Route::post('perangkat-ajar-saya', [PerangkatAjarSayaController::class, 'store'])->name('perangkat-ajar-saya.store');
            Route::get('perangkat-ajar-saya/{perangkatAjar}/edit', [PerangkatAjarSayaController::class, 'edit'])->name('perangkat-ajar-saya.edit');
            Route::put('perangkat-ajar-saya/{perangkatAjar}', [PerangkatAjarSayaController::class, 'update'])->name('perangkat-ajar-saya.update');
        });
        Route::middleware('izin:perangkat_ajar.upload,perangkat_ajar.lihat,perangkat_ajar.periksa')->group(function () {
            Route::get('perangkat-ajar-saya/{perangkatAjar}', [PerangkatAjarSayaController::class, 'show'])->name('perangkat-ajar-saya.show');
            Route::get('perangkat-ajar-saya/{perangkatAjar}/unduh', [PerangkatAjarSayaController::class, 'download'])->name('perangkat-ajar-saya.download');
            Route::get('riwayat-file-perangkat-ajar/{riwayatFilePerangkatAjar}/unduh', [PerangkatAjarSayaController::class, 'downloadRiwayat'])->name('perangkat-ajar-saya.download-riwayat');
        });
        Route::middleware('izin:perangkat_ajar.lihat,perangkat_ajar.periksa')->group(function () {
            Route::get('pemeriksaan-perangkat-ajar', [PemeriksaanPerangkatAjarController::class, 'index'])->name('pemeriksaan-perangkat-ajar.index');
            Route::get('pemeriksaan-perangkat-ajar/guru/{pegawai}', [PemeriksaanPerangkatAjarController::class, 'show'])->name('pemeriksaan-perangkat-ajar.show');
        });
        Route::middleware('izin:perangkat_ajar.periksa')->group(function () {
            Route::get('pemeriksaan-perangkat-ajar/dokumen/{perangkatAjar}/edit', [PemeriksaanPerangkatAjarController::class, 'edit'])->name('pemeriksaan-perangkat-ajar.edit');
            Route::put('pemeriksaan-perangkat-ajar/dokumen/{perangkatAjar}', [PemeriksaanPerangkatAjarController::class, 'update'])->name('pemeriksaan-perangkat-ajar.update');
        });

        Route::resource('barang', BarangController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:barang.kelola');
        Route::resource('barang', BarangController::class)
            ->only(['index', 'show'])
            ->middleware('izin:barang.lihat,barang.kelola');
        Route::resource('kategori-barang', KategoriBarangController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:barang.kelola');
        Route::resource('kategori-barang', KategoriBarangController::class)
            ->only(['index', 'show'])
            ->middleware('izin:barang.lihat,barang.kelola');
        Route::resource('satuan-barang', SatuanBarangController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:barang.kelola');
        Route::resource('satuan-barang', SatuanBarangController::class)
            ->only(['index', 'show'])
            ->middleware('izin:barang.lihat,barang.kelola');
        Route::resource('lokasi-barang', LokasiBarangController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:barang.kelola');
        Route::resource('lokasi-barang', LokasiBarangController::class)
            ->only(['index', 'show'])
            ->middleware('izin:barang.lihat,barang.kelola');
        Route::resource('unit-barang', UnitBarangController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:barang.kelola');
        Route::resource('unit-barang', UnitBarangController::class)
            ->only(['index', 'show'])
            ->middleware('izin:barang.lihat,barang.kelola');
        Route::get('label-barcode-inventaris', [LabelBarcodeInventarisController::class, 'index'])
            ->middleware('izin:barang.lihat,barang.kelola')
            ->name('label-barcode-inventaris.index');
        Route::get('stok-barang', [SaldoStokBarangController::class, 'index'])
            ->middleware('izin:barang.lihat,barang.kelola')
            ->name('saldo-stok-barang.index');
        Route::resource('mutasi-stok-barang', MutasiStokBarangController::class)
            ->only(['create', 'store'])
            ->middleware('izin:barang.kelola');
        Route::resource('mutasi-stok-barang', MutasiStokBarangController::class)
            ->only(['index', 'show'])
            ->middleware('izin:barang.lihat,barang.kelola');
        Route::middleware('izin:barang.peminjaman_kelola')->group(function () {
            Route::get('peminjaman-barang/create', [PeminjamanBarangController::class, 'create'])->name('peminjaman-barang.create');
            Route::post('peminjaman-barang', [PeminjamanBarangController::class, 'store'])->name('peminjaman-barang.store');
            Route::get('peminjaman-barang/identifikasi-peminjam', [PeminjamanBarangController::class, 'identifikasiPeminjam'])->name('peminjaman-barang.identifikasi-peminjam');
            Route::get('peminjaman-barang/identifikasi-barang', [PeminjamanBarangController::class, 'identifikasiBarang'])->name('peminjaman-barang.identifikasi-barang');
            Route::get('peminjaman-barang/{peminjamanBarang}/pengembalian/create', [PengembalianBarangController::class, 'create'])->name('pengembalian-barang.create');
            Route::post('peminjaman-barang/{peminjamanBarang}/pengembalian', [PengembalianBarangController::class, 'store'])->name('pengembalian-barang.store');
        });
        Route::resource('peminjaman-barang', PeminjamanBarangController::class)
            ->only(['index', 'show'])
            ->middleware('izin:barang.lihat,barang.peminjaman_kelola');

        Route::resource('skema-bobot-nilai', SkemaBobotNilaiController::class)
            ->middleware('izin:nilai.skema_kelola');
        Route::resource('komponen-nilai', KomponenNilaiController::class)
            ->middleware('izin:nilai.komponen_kelola');
        Route::middleware('izin:nilai.input')->group(function () {
            Route::get('input-nilai', [InputNilaiController::class, 'index'])->name('input-nilai.index');
            Route::post('input-nilai', [InputNilaiController::class, 'store'])->name('input-nilai.store');
        });
        Route::get('rekap-nilai-rapor', [RekapNilaiRaporController::class, 'index'])
            ->middleware('izin:nilai.rekap')
            ->name('rekap-nilai-rapor.index');

        Route::resource('pengaturan-absensi', PengaturanAbsensiController::class)
            ->middleware('izin:absensi.pengaturan_kelola');
        Route::resource('pengaturan-absensi-pegawai', PengaturanAbsensiPegawaiController::class)
            ->middleware('izin:absensi.pengaturan_kelola');
        Route::middleware('izin:absensi.scan')->group(function () {
            Route::get('scan-absensi', [ScanAbsensiController::class, 'index'])->name('scan-absensi.index');
            Route::post('scan-absensi', [ScanAbsensiController::class, 'store'])->name('scan-absensi.store');
            Route::get('scan-absensi-pegawai', [ScanAbsensiPegawaiController::class, 'index'])->name('scan-absensi-pegawai.index');
            Route::post('scan-absensi-pegawai', [ScanAbsensiPegawaiController::class, 'store'])->name('scan-absensi-pegawai.store');
        });
        Route::get('rekap-absensi-harian', [RekapAbsensiHarianController::class, 'index'])
            ->middleware('izin:absensi.lihat,absensi.koreksi,absensi.laporan')
            ->name('rekap-absensi-harian.index');
        Route::middleware('izin:absensi.koreksi')->group(function () {
            Route::get('rekap-absensi-harian/{anggotaKelas}/koreksi', [RekapAbsensiHarianController::class, 'editKoreksi'])->name('rekap-absensi-harian.koreksi.edit');
            Route::put('rekap-absensi-harian/{anggotaKelas}/koreksi', [RekapAbsensiHarianController::class, 'updateKoreksi'])->name('rekap-absensi-harian.koreksi.update');
        });
        Route::get('rekap-absensi-pegawai-harian', [RekapAbsensiPegawaiHarianController::class, 'index'])
            ->middleware('izin:absensi.lihat,absensi.koreksi,absensi.laporan,absensi_pegawai.pribadi')
            ->name('rekap-absensi-pegawai-harian.index');
        Route::middleware('izin:absensi.koreksi')->group(function () {
            Route::get('rekap-absensi-pegawai-harian/{pegawai}/koreksi', [RekapAbsensiPegawaiHarianController::class, 'editKoreksi'])->name('rekap-absensi-pegawai-harian.koreksi.edit');
            Route::put('rekap-absensi-pegawai-harian/{pegawai}/koreksi', [RekapAbsensiPegawaiHarianController::class, 'updateKoreksi'])->name('rekap-absensi-pegawai-harian.koreksi.update');
        });
        Route::get('laporan-absensi', [LaporanAbsensiController::class, 'index'])
            ->middleware('izin:absensi.laporan')
            ->name('laporan-absensi.index');
        Route::get('notifikasi-absensi-siswa', [NotifikasiAbsensiSiswaController::class, 'index'])
            ->middleware('izin:absensi.laporan')
            ->name('notifikasi-absensi-siswa.index');
        Route::get('laporan-absensi/export', [LaporanAbsensiController::class, 'exportExcel'])
            ->middleware('izin:laporan.export')
            ->name('laporan-absensi.export');
        Route::get('laporan-absensi-pegawai-bulanan', [LaporanAbsensiPegawaiBulananController::class, 'index'])
            ->middleware('izin:absensi.laporan,absensi_pegawai.pribadi')
            ->name('laporan-absensi-pegawai-bulanan.index');
        Route::middleware('izin:laporan.export,absensi_pegawai.pribadi')->group(function () {
            Route::get('laporan-absensi-pegawai-bulanan/cetak', [LaporanAbsensiPegawaiBulananController::class, 'cetak'])->name('laporan-absensi-pegawai-bulanan.cetak');
            Route::get('laporan-absensi-pegawai-bulanan/{pegawai}/cetak', [LaporanAbsensiPegawaiBulananController::class, 'cetakPegawai'])->name('laporan-absensi-pegawai-bulanan.cetak-pegawai');
        });

        Route::middleware('izin:kenaikan_kelas.kelola')->group(function () {
            Route::get('kenaikan-kelas', [KenaikanKelasController::class, 'index'])->name('kenaikan-kelas.index');
            Route::post('kenaikan-kelas', [KenaikanKelasController::class, 'store'])->name('kenaikan-kelas.store');
        });

        Route::resource('kategori-pembinaan-siswa', KategoriPembinaanSiswaController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:bk.kelola');
        Route::resource('kategori-pembinaan-siswa', KategoriPembinaanSiswaController::class)
            ->only(['index', 'show'])
            ->middleware('izin:bk.lihat,bk.kelola');

        Route::resource('laporan-pembinaan-siswa', LaporanPembinaanSiswaController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:bk.kelola');
        Route::resource('laporan-pembinaan-siswa', LaporanPembinaanSiswaController::class)
            ->only(['index', 'show'])
            ->middleware('izin:bk.lihat,bk.kelola');
        Route::middleware('izin:bk.kelola')->group(function () {
            Route::get('laporan-pembinaan-siswa/{laporanPembinaanSiswa}/tindak-lanjut/create', [TindakLanjutPembinaanSiswaController::class, 'create'])->name('tindak-lanjut-pembinaan-siswa.create');
            Route::post('laporan-pembinaan-siswa/{laporanPembinaanSiswa}/tindak-lanjut', [TindakLanjutPembinaanSiswaController::class, 'store'])->name('tindak-lanjut-pembinaan-siswa.store');
            Route::get('tindak-lanjut-pembinaan-siswa/{tindakLanjutPembinaanSiswa}/edit', [TindakLanjutPembinaanSiswaController::class, 'edit'])->name('tindak-lanjut-pembinaan-siswa.edit');
            Route::put('tindak-lanjut-pembinaan-siswa/{tindakLanjutPembinaanSiswa}', [TindakLanjutPembinaanSiswaController::class, 'update'])->name('tindak-lanjut-pembinaan-siswa.update');
            Route::delete('tindak-lanjut-pembinaan-siswa/{tindakLanjutPembinaanSiswa}', [TindakLanjutPembinaanSiswaController::class, 'destroy'])->name('tindak-lanjut-pembinaan-siswa.destroy');
        });

        Route::middleware('izin:kelas.kelola')->group(function () {
            Route::post('penempatan-siswa/masukkan', [PenempatanSiswaController::class, 'storeMassal'])->name('penempatan-siswa.store-massal');
            Route::post('kelas/{kelas}/anggota-kelas', [AnggotaKelasController::class, 'store'])->name('anggota-kelas.store');
            Route::patch('anggota-kelas/{anggotaKelas}', [AnggotaKelasController::class, 'update'])->name('anggota-kelas.update');
            Route::delete('anggota-kelas/{anggotaKelas}', [AnggotaKelasController::class, 'destroy'])->name('anggota-kelas.destroy');
        });
        Route::get('penempatan-siswa', [PenempatanSiswaController::class, 'index'])
            ->middleware('izin:kelas.lihat,kelas.kelola')
            ->name('penempatan-siswa.index');
        Route::resource('kelas', KelasController::class)
            ->only(['create', 'store', 'edit', 'update', 'destroy'])
            ->middleware('izin:kelas.kelola')
            ->parameters([
                'kelas' => 'kelas',
            ]);
        Route::resource('kelas', KelasController::class)
            ->only(['index', 'show'])
            ->middleware('izin:kelas.lihat,kelas.kelola')
            ->parameters([
                'kelas' => 'kelas',
            ]);
    });
});
