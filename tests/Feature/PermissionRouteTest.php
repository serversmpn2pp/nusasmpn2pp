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
    }

    public function test_route_absensi_dan_bk_memakai_permission_modul(): void
    {
        $this->assertRouteMemakaiMiddleware('scan-absensi.index', 'izin:absensi.scan');
        $this->assertRouteMemakaiMiddleware('rekap-absensi-harian.index', 'izin:absensi.lihat,absensi.koreksi,absensi.laporan');
        $this->assertRouteMemakaiMiddleware('rekap-absensi-harian.koreksi.edit', 'izin:absensi.koreksi');
        $this->assertRouteMemakaiMiddleware('laporan-absensi.index', 'izin:absensi.laporan');
        $this->assertRouteMemakaiMiddleware('laporan-absensi.export', 'izin:laporan.export');
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

    private function assertRouteMemakaiMiddleware(string $namaRoute, string $middleware): void
    {
        $route = Route::getRoutes()->getByName($namaRoute);

        $this->assertNotNull($route, "Route {$namaRoute} tidak ditemukan.");
        $this->assertContains($middleware, $route->gatherMiddleware());
    }
}
