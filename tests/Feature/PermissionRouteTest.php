<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PermissionRouteTest extends TestCase
{
    public function test_route_siswa_memisahkan_izin_lihat_dan_kelola(): void
    {
        $this->assertRouteMemakaiMiddleware('siswa.index', 'izin:siswa.lihat,siswa.kelola');
        $this->assertRouteMemakaiMiddleware('siswa.show', 'izin:siswa.lihat,siswa.kelola');
        $this->assertRouteMemakaiMiddleware('siswa.create', 'izin:siswa.kelola');
        $this->assertRouteMemakaiMiddleware('siswa.store', 'izin:siswa.kelola');
        $this->assertRouteMemakaiMiddleware('siswa.edit', 'izin:siswa.kelola');
        $this->assertRouteMemakaiMiddleware('siswa.update', 'izin:siswa.kelola');
        $this->assertRouteMemakaiMiddleware('siswa.destroy', 'izin:siswa.kelola');
    }

    public function test_route_role_dan_akun_memakai_permission_sistem(): void
    {
        $this->assertRouteMemakaiMiddleware('peran.index', 'izin:peran.lihat,peran.kelola');
        $this->assertRouteMemakaiMiddleware('peran.create', 'izin:peran.kelola');
        $this->assertRouteMemakaiMiddleware('akun-pegawai.index', 'izin:akun.lihat,akun.kelola');
        $this->assertRouteMemakaiMiddleware('akun-pegawai.peran.update', 'izin:akun.kelola');
        $this->assertRouteMemakaiMiddleware('profil-pegawai.edit', 'izin:pegawai.profil');
        $this->assertRouteMemakaiMiddleware('profil-pegawai.update', 'izin:pegawai.profil');
    }

    public function test_route_absensi_dan_bk_memakai_permission_modul(): void
    {
        $this->assertRouteMemakaiMiddleware('scan-absensi.index', 'izin:absensi.scan');
        $this->assertRouteMemakaiMiddleware('rekap-absensi-harian.index', 'izin:absensi.lihat,absensi.koreksi,absensi.laporan');
        $this->assertRouteMemakaiMiddleware('rekap-absensi-harian.koreksi.edit', 'izin:absensi.koreksi');
        $this->assertRouteMemakaiMiddleware('rekap-absensi-pegawai-harian.index', 'izin:absensi.lihat,absensi.koreksi,absensi.laporan,absensi_pegawai.pribadi');
        $this->assertRouteMemakaiMiddleware('laporan-absensi.index', 'izin:absensi.laporan');
        $this->assertRouteMemakaiMiddleware('notifikasi-absensi-siswa.index', 'izin:absensi.laporan');
        $this->assertRouteMemakaiMiddleware('laporan-absensi.export', 'izin:laporan.export');
        $this->assertRouteMemakaiMiddleware('laporan-absensi-pegawai-bulanan.index', 'izin:absensi.laporan,absensi_pegawai.pribadi');
        $this->assertRouteMemakaiMiddleware('laporan-absensi-pegawai-bulanan.cetak-pegawai', 'izin:laporan.export,absensi_pegawai.pribadi');
        $this->assertRouteMemakaiMiddleware('laporan-pembinaan-siswa.index', 'izin:bk.lihat,bk.kelola');
        $this->assertRouteMemakaiMiddleware('laporan-pembinaan-siswa.create', 'izin:bk.kelola');
    }

    public function test_route_penempatan_siswa_memakai_permission_kelas(): void
    {
        $this->assertRouteMemakaiMiddleware('penempatan-siswa.index', 'izin:kelas.lihat,kelas.kelola');
        $this->assertRouteMemakaiMiddleware('penempatan-siswa.store-massal', 'izin:kelas.kelola');
        $this->assertRouteMemakaiMiddleware('anggota-kelas.update', 'izin:kelas.kelola');
        $this->assertRouteMemakaiMiddleware('anggota-kelas.destroy', 'izin:kelas.kelola');
    }

    public function test_route_jadwal_pelajaran_memakai_permission_jadwal(): void
    {
        $this->assertRouteMemakaiMiddleware('jam-pelajaran.index', 'izin:jadwal.lihat,jadwal.kelola');
        $this->assertRouteMemakaiMiddleware('jam-pelajaran.create', 'izin:jadwal.kelola');
        $this->assertRouteMemakaiMiddleware('jadwal-pelajaran.index', 'izin:jadwal.lihat,jadwal.kelola');
        $this->assertRouteMemakaiMiddleware('jadwal-pelajaran.create', 'izin:jadwal.kelola');
        $this->assertRouteMemakaiMiddleware('jadwal-saya.index', 'izin:jadwal.pribadi');
    }

    public function test_route_jenis_perangkat_ajar_memakai_permission_kurikulum(): void
    {
        $this->assertRouteMemakaiMiddleware('jenis-perangkat-ajar.index', 'izin:perangkat_ajar.jenis_kelola');
        $this->assertRouteMemakaiMiddleware('jenis-perangkat-ajar.create', 'izin:perangkat_ajar.jenis_kelola');
        $this->assertRouteMemakaiMiddleware('jenis-perangkat-ajar.update', 'izin:perangkat_ajar.jenis_kelola');
        $this->assertRouteMemakaiMiddleware('perangkat-ajar-saya.index', 'izin:perangkat_ajar.upload');
        $this->assertRouteMemakaiMiddleware('perangkat-ajar-saya.store', 'izin:perangkat_ajar.upload');
        $this->assertRouteMemakaiMiddleware('perangkat-ajar-saya.download', 'izin:perangkat_ajar.upload,perangkat_ajar.lihat,perangkat_ajar.periksa');
        $this->assertRouteMemakaiMiddleware('pemeriksaan-perangkat-ajar.index', 'izin:perangkat_ajar.lihat,perangkat_ajar.periksa');
        $this->assertRouteMemakaiMiddleware('pemeriksaan-perangkat-ajar.show', 'izin:perangkat_ajar.lihat,perangkat_ajar.periksa');
        $this->assertRouteMemakaiMiddleware('pemeriksaan-perangkat-ajar.update', 'izin:perangkat_ajar.periksa');
    }

    public function test_route_master_inventaris_memakai_permission_barang(): void
    {
        $this->assertRouteMemakaiMiddleware('barang.index', 'izin:barang.lihat,barang.kelola');
        $this->assertRouteMemakaiMiddleware('barang.create', 'izin:barang.kelola');
        $this->assertRouteMemakaiMiddleware('kategori-barang.index', 'izin:barang.lihat,barang.kelola');
        $this->assertRouteMemakaiMiddleware('kategori-barang.store', 'izin:barang.kelola');
        $this->assertRouteMemakaiMiddleware('satuan-barang.index', 'izin:barang.lihat,barang.kelola');
        $this->assertRouteMemakaiMiddleware('satuan-barang.update', 'izin:barang.kelola');
        $this->assertRouteMemakaiMiddleware('lokasi-barang.index', 'izin:barang.lihat,barang.kelola');
        $this->assertRouteMemakaiMiddleware('lokasi-barang.destroy', 'izin:barang.kelola');
        $this->assertRouteMemakaiMiddleware('unit-barang.index', 'izin:barang.lihat,barang.kelola');
        $this->assertRouteMemakaiMiddleware('unit-barang.store', 'izin:barang.kelola');
        $this->assertRouteMemakaiMiddleware('label-barcode-inventaris.index', 'izin:barang.lihat,barang.kelola');
        $this->assertRouteMemakaiMiddleware('saldo-stok-barang.index', 'izin:barang.lihat,barang.kelola');
        $this->assertRouteMemakaiMiddleware('mutasi-stok-barang.index', 'izin:barang.lihat,barang.kelola');
        $this->assertRouteMemakaiMiddleware('mutasi-stok-barang.store', 'izin:barang.kelola');
        $this->assertRouteMemakaiMiddleware('peminjaman-barang.index', 'izin:barang.lihat,barang.peminjaman_kelola');
        $this->assertRouteMemakaiMiddleware('peminjaman-barang.show', 'izin:barang.lihat,barang.peminjaman_kelola');
        $this->assertRouteMemakaiMiddleware('peminjaman-barang.store', 'izin:barang.peminjaman_kelola');
        $this->assertRouteMemakaiMiddleware('pengembalian-barang.store', 'izin:barang.peminjaman_kelola');
    }

    private function assertRouteMemakaiMiddleware(string $namaRoute, string $middleware): void
    {
        $route = Route::getRoutes()->getByName($namaRoute);

        $this->assertNotNull($route, "Route {$namaRoute} tidak ditemukan.");
        $this->assertContains($middleware, $route->gatherMiddleware());
    }
}
