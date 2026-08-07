<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BahasaAlurPenangananSiswaTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_dan_halaman_penanganan_siswa_memakai_istilah_yang_jelas(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->get(route('laporan-pembinaan-siswa.index'))
            ->assertOk()
            ->assertSee('Pemeriksaan &amp; Pengesahan', false)
            ->assertSee('Daftar Laporan Siswa')
            ->assertSee('Pendampingan Siswa')
            ->assertSee('Pelaksanaan Sanksi Siswa')
            ->assertSee('Penghargaan &amp; Pengurangan Poin', false)
            ->assertSee('Arsip lengkap penanganan laporan siswa')
            ->assertSee('Catatan pembinaan langsung tidak menambah poin.');

        $this->get(route('pusat-verifikasi-pelanggaran.index'))
            ->assertOk()
            ->assertSee('BK memeriksa, Wakil Kesiswaan mengesahkan poin')
            ->assertSee('Poin belum resmi sebelum disahkan Wakil Kesiswaan.');

        $this->get(route('pendampingan-siswa.index'))
            ->assertOk()
            ->assertSee('Pendampingan membantu perubahan perilaku siswa')
            ->assertSee('Pendampingan tidak otomatis menambah poin.');

        $this->get(route('sanksi-poin-siswa.index'))
            ->assertOk()
            ->assertSee('Sanksi dijalankan setelah batas poin tercapai');

        $this->get(route('pengurangan-poin-siswa.index'))
            ->assertOk()
            ->assertSee('Pengurangan poin tidak menghapus riwayat pelanggaran atau sanksi yang sudah tercatat.');
    }
}
