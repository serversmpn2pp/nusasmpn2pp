<?php

namespace Tests\Feature;

use Tests\TestCase;

class AutentikasiTest extends TestCase
{
    public function test_halaman_login_dapat_dibuka(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Masuk ke aplikasi');
        $response->assertSee('aria-controls="password"', false);
        $response->assertSee('aria-label="Tampilkan kata sandi"', false);
        $response->assertSee('Caps Lock aktif.');
    }

    public function test_halaman_pegawai_dikunci_sebelum_login(): void
    {
        $response = $this->get('/pegawai');

        $response->assertRedirect(route('login'));
    }

    public function test_halaman_beranda_dikunci_sebelum_login(): void
    {
        $response = $this->get('/beranda');

        $response->assertRedirect(route('login'));
    }

    public function test_halaman_akun_pegawai_dikunci_sebelum_login(): void
    {
        $response = $this->get('/akun-pegawai');

        $response->assertRedirect(route('login'));
    }

    public function test_halaman_akun_siswa_dikunci_sebelum_login(): void
    {
        $response = $this->get('/akun-siswa');

        $response->assertRedirect(route('login'));
    }

    public function test_halaman_peran_dikunci_sebelum_login(): void
    {
        $response = $this->get('/peran');

        $response->assertRedirect(route('login'));
    }

    public function test_halaman_mata_pelajaran_dikunci_sebelum_login(): void
    {
        $response = $this->get('/mata-pelajaran');

        $response->assertRedirect(route('login'));
    }

    public function test_halaman_kartu_pelajar_dikunci_sebelum_login(): void
    {
        $response = $this->get('/kartu-pelajar');

        $response->assertRedirect(route('login'));
    }

    public function test_halaman_guru_mata_pelajaran_dikunci_sebelum_login(): void
    {
        $response = $this->get('/guru-mata-pelajaran');

        $response->assertRedirect(route('login'));
    }

    public function test_halaman_skema_bobot_nilai_dikunci_sebelum_login(): void
    {
        $response = $this->get('/skema-bobot-nilai');

        $response->assertRedirect(route('login'));
    }

    public function test_halaman_komponen_nilai_dikunci_sebelum_login(): void
    {
        $response = $this->get('/komponen-nilai');

        $response->assertRedirect(route('login'));
    }

    public function test_halaman_input_nilai_dikunci_sebelum_login(): void
    {
        $response = $this->get('/input-nilai');

        $response->assertRedirect(route('login'));
    }

    public function test_halaman_rekap_nilai_rapor_dikunci_sebelum_login(): void
    {
        $response = $this->get('/rekap-nilai-rapor');

        $response->assertRedirect(route('login'));
    }

    public function test_halaman_pengaturan_absensi_dikunci_sebelum_login(): void
    {
        $response = $this->get('/pengaturan-absensi');

        $response->assertRedirect(route('login'));
    }

    public function test_halaman_scan_absensi_dikunci_sebelum_login(): void
    {
        $response = $this->get('/scan-absensi');

        $response->assertRedirect(route('login'));
    }

    public function test_halaman_rekap_absensi_harian_dikunci_sebelum_login(): void
    {
        $response = $this->get('/rekap-absensi-harian');

        $response->assertRedirect(route('login'));
    }

    public function test_halaman_koreksi_absensi_dikunci_sebelum_login(): void
    {
        $response = $this->get('/rekap-absensi-harian/1/koreksi');

        $response->assertRedirect(route('login'));
    }

    public function test_halaman_koreksi_absensi_pegawai_dikunci_sebelum_login(): void
    {
        $response = $this->get('/rekap-absensi-pegawai-harian/1/koreksi');

        $response->assertRedirect(route('login'));
    }

    public function test_halaman_laporan_absensi_dikunci_sebelum_login(): void
    {
        $response = $this->get('/laporan-absensi');

        $response->assertRedirect(route('login'));
    }

    public function test_export_laporan_absensi_dikunci_sebelum_login(): void
    {
        $response = $this->get('/laporan-absensi/export');

        $response->assertRedirect(route('login'));
    }

    public function test_halaman_laporan_absensi_pegawai_bulanan_dikunci_sebelum_login(): void
    {
        $response = $this->get('/laporan-absensi-pegawai-bulanan');

        $response->assertRedirect(route('login'));
    }

    public function test_halaman_kategori_pembinaan_siswa_dikunci_sebelum_login(): void
    {
        $response = $this->get('/kategori-pembinaan-siswa');

        $response->assertRedirect(route('login'));
    }

    public function test_halaman_laporan_pembinaan_siswa_dikunci_sebelum_login(): void
    {
        $response = $this->get('/laporan-pembinaan-siswa');

        $response->assertRedirect(route('login'));
    }

    public function test_halaman_tindak_lanjut_pembinaan_siswa_dikunci_sebelum_login(): void
    {
        $response = $this->get('/laporan-pembinaan-siswa/1/tindak-lanjut/create');

        $response->assertRedirect(route('login'));
    }

    public function test_halaman_edit_tindak_lanjut_pembinaan_siswa_dikunci_sebelum_login(): void
    {
        $response = $this->get('/tindak-lanjut-pembinaan-siswa/1/edit');

        $response->assertRedirect(route('login'));
    }

    public function test_cetak_laporan_absensi_pegawai_bulanan_dikunci_sebelum_login(): void
    {
        $response = $this->get('/laporan-absensi-pegawai-bulanan/cetak');

        $response->assertRedirect(route('login'));
    }

    public function test_cetak_laporan_absensi_satu_pegawai_dikunci_sebelum_login(): void
    {
        $response = $this->get('/laporan-absensi-pegawai-bulanan/1/cetak');

        $response->assertRedirect(route('login'));
    }
}
