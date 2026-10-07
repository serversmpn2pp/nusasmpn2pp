<?php

namespace Tests\Feature;

use App\Models\NotifikasiPengguna;
use App\Services\Notifikasi\IsiPushNotifikasiService;
use Tests\TestCase;

class IsiPushNotifikasiTest extends TestCase
{
    public function test_pesan_rutin_nilai_siswa_orang_tua_dan_survei_memakai_teks_asli(): void
    {
        foreach ([
            ['/nilai-saya', 'nilai-dipublikasikan:12:20261006', 'Nilai Informatika Anda telah tersedia'],
            ['/akademik-anak?tab=nilai', 'nilai-anak-dipublikasikan:12:20261006', 'Nilai Informatika anak Anda telah tersedia'],
            ['/hasil-survei-saya', 'hasil-survei-terbuka:12:ganjil', 'Hasil survei sudah dapat dibuka'],
            ['/ujian-saya', 'hasil-ujian-dipublikasikan:12:20261006', 'Hasil ujian Anda telah dipublikasikan'],
            ['/ujian-anak-saya', 'hasil-ujian-anak-dipublikasikan:12:20261006', 'Hasil ujian anak Anda telah dipublikasikan'],
        ] as [$tautan, $kunci, $judul]) {
            $notifikasi = new NotifikasiPengguna(['tautan' => $tautan, 'kunci_unik' => $kunci,
                'judul' => $judul, 'pesan' => '<b>Pembaruan</b> untuk akun Anda.']);
            $this->assertSame(['title' => $judul, 'body' => 'Pembaruan untuk akun Anda.'], app(IsiPushNotifikasiService::class)->untuk($notifikasi));
            $this->assertSame('<b>Pembaruan</b> untuk akun Anda.', $notifikasi->pesan);
        }
    }

    public function test_bk_sanksi_dan_berhalangan_selalu_ringkas_meski_jenisnya_informasi(): void
    {
        foreach (['/sanksi-poin-siswa/1', '/laporan-pembinaan-siswa/2', '/pembinaan-poin-anak?tab=poin',
            '/peringatan-dini-siswa', '/progress-kasus-saya/3', '/konfirmasi-berhalangan-ibadah/4'] as $tautan) {
            $hasil = app(IsiPushNotifikasiService::class)->untuk(new NotifikasiPengguna([
                'jenis' => 'informasi', 'tautan' => $tautan, 'kunci_unik' => 'nilai-dipublikasikan:1:x',
                'judul' => 'Nadia sanksi 125 poin', 'pesan' => 'Catatan privat kesehatan siswa',
            ]));
            $this->assertStringNotContainsString('Nadia', json_encode($hasil));
            $this->assertStringNotContainsString('125', json_encode($hasil));
            $this->assertStringNotContainsString('kesehatan', json_encode($hasil));
            $this->assertStringContainsString('Buka NUSA', $hasil['body']);
        }
        $hasil = app(IsiPushNotifikasiService::class)->untuk(new NotifikasiPengguna([
            'tautan' => '/nilai-saya', 'kunci_unik' => 'konfirmasi-berhalangan-1', 'judul' => 'Privat', 'pesan' => 'Rahasia',
        ]));
        $this->assertSame('Pembaruan ibadah privat', $hasil['title']);
    }

    public function test_catatan_bebas_modul_lain_tidak_disalin_ke_layar_kunci(): void
    {
        foreach (['/tugas-pengawas-ujian/1', '/perangkat-ajar-saya/2', '/pengajuan-barang-saya/3',
            '/agenda-humas/4', '/pengaduan-saya/5', '/umpan-balik-saya/6'] as $tautan) {
            $hasil = app(IsiPushNotifikasiService::class)->untuk(new NotifikasiPengguna([
                'tautan' => $tautan, 'judul' => 'Identitas rahasia', 'pesan' => 'Alasan medis rahasia',
            ]));
            $this->assertNotSame('NUSA', $hasil['title']);
            $this->assertStringNotContainsString('rahasia', json_encode($hasil));
        }
    }

    public function test_jenis_baru_dan_data_presensi_tidak_valid_tetap_aman(): void
    {
        foreach ([['/modul-baru', 'baru:1'], ['/nilai-saya', null], ['/presensi-anak', 'presensi-masuk-orang-tua:1']] as [$tautan, $kunci]) {
            $hasil = app(IsiPushNotifikasiService::class)->untuk(new NotifikasiPengguna([
                'tautan' => $tautan, 'kunci_unik' => $kunci, 'judul' => 'Rahasia', 'pesan' => 'Catatan privat',
                'data_tambahan' => ['jam_masuk' => '99:99', 'menit_terlambat' => 'rahasia'],
            ]));
            $this->assertSame('NUSA', $hasil['title']);
            $this->assertStringNotContainsString('privat', $hasil['body']);
        }
    }
}
