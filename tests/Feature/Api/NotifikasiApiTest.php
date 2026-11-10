<?php

namespace Tests\Feature\Api;

use App\Models\NotifikasiPengguna;
use App\Models\Pengguna;
use App\Services\Mobile\TujuanNotifikasiMobileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class NotifikasiApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_riwayat_dua_puluh_per_halaman_tetap_stabil_saat_notifikasi_baru_masuk(): void
    {
        $akun = Pengguna::where('username', 'administrator')->firstOrFail();
        $ids = [];
        for ($i = 0; $i < 45; $i++) {
            $n = $this->buatNotifikasi($akun, 'Riwayat '.$i);
            $n->forceFill(['created_at' => '2026-10-01 08:00:00', 'dibaca_pada' => $i % 3 === 0 ? now() : null])->save();
            $ids[] = $n->id;
        }
        $rahasia = $this->buatNotifikasi($this->buatPengguna('pemilik-lain'), 'Notifikasi privat akun lain');
        $this->withToken($this->token($akun));
        $url = route('api.v1.notifikasi.index');
        $pertama = $this->getJson($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonCount(20, 'data.items')->assertJsonPath('data.jumlah_belum_dibaca', 30)
            ->assertJsonPath('data.paginasi.per_halaman', 20)->assertJsonPath('data.paginasi.ada_lagi', true)
            ->assertDontSee($rahasia->judul);
        $this->assertSame(array_slice(array_reverse($ids), 0, 20), array_column($pertama->json('data.items'), 'id'));
        $anchor = $pertama->json('data.paginasi.sebelum_id_berikutnya');
        $this->assertSame($ids[25], $anchor);
        NotifikasiPengguna::findOrFail($anchor)->tandaiDibaca();
        $this->buatNotifikasi($akun, 'Pesan baru ketika riwayat dibuka');
        $kedua = $this->getJson($url.'?sebelum_id='.$anchor)->assertOk()->assertJsonCount(20, 'data.items');
        $this->assertSame(array_slice(array_reverse($ids), 20, 20), array_column($kedua->json('data.items'), 'id'));
        $ketiga = $this->getJson($url.'?sebelum_id='.$kedua->json('data.paginasi.sebelum_id_berikutnya'))
            ->assertOk()->assertJsonCount(5, 'data.items')->assertJsonPath('data.paginasi.ada_lagi', false)
            ->assertJsonPath('data.paginasi.sebelum_id_berikutnya', null);
        $this->assertSame(array_slice(array_reverse($ids), 40), array_column($ketiga->json('data.items'), 'id'));
        $this->assertNull(NotifikasiPengguna::findOrFail($ids[1])->dibaca_pada);
        $this->getJson($url.'?sebelum_id='.$rahasia->id)->assertNotFound()->assertDontSee($rahasia->judul);
    }

    public function test_filter_belum_dibaca_menelusuri_riwayat_dan_baca_semua_mencakup_halaman_lama(): void
    {
        $akun = Pengguna::where('username', 'administrator')->firstOrFail();
        for ($i = 0; $i < 28; $i++) {
            $n = $this->buatNotifikasi($akun, 'Riwayat baca '.$i);
            if ($i < 3) {
                $n->tandaiDibaca();
            }
        }
        $this->withToken($this->token($akun));
        $url = route('api.v1.notifikasi.index', ['status' => 'belum_dibaca']);
        $pertama = $this->getJson($url)->assertOk()->assertJsonCount(20, 'data.items')
            ->assertJsonPath('data.jumlah_belum_dibaca', 25)->assertJsonPath('data.filter.status', 'belum_dibaca');
        $this->assertSame([true], array_values(array_unique(array_column($pertama->json('data.items'), 'belum_dibaca'))));
        $anchor = $pertama->json('data.paginasi.sebelum_id_berikutnya');
        NotifikasiPengguna::findOrFail($anchor)->tandaiDibaca();
        $this->getJson($url.'&sebelum_id='.$anchor)->assertOk()->assertJsonCount(5, 'data.items')
            ->assertJsonPath('data.paginasi.ada_lagi', false);
        $this->patchJson(route('api.v1.notifikasi.baca-semua'))->assertOk()->assertJsonPath('data.jumlah_belum_dibaca', 0);
        $this->getJson($url)->assertOk()->assertJsonCount(0, 'data.items')->assertJsonPath('data.paginasi.ada_lagi', false);
        $this->assertSame(28, $akun->notifikasiPengguna()->sudahDibaca()->count());
    }

    public function test_riwayat_notifikasi_memerlukan_akun_aktif_dan_filter_valid(): void
    {
        $url = route('api.v1.notifikasi.index');
        $this->getJson($url)->assertUnauthorized();
        $akun = $this->buatPengguna('akun-riwayat');
        $this->withToken($this->token($akun));
        $this->getJson($url.'?status=sudah_dibaca')->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->getJson($url.'?sebelum_id[]=1')->assertUnprocessable()->assertJsonValidationErrors('sebelum_id');
        $this->getJson($url.'?sebelum_id=-1')->assertUnprocessable();
        $this->getJson($url.'?sebelum_id=99999999')->assertNotFound();
        $this->getJson($url.'?pengguna_id=1')->assertOk()->assertJsonCount(0, 'data.items');
        $akun->update(['wajib_ganti_kata_sandi' => true]);
        $this->getJson($url)->assertStatus(428);
        $akun->update(['wajib_ganti_kata_sandi' => false, 'aktif' => false]);
        $this->getJson($url)->assertUnauthorized();
    }

    public function test_detail_push_hanya_untuk_pemilik_dan_tidak_menandai_baca(): void
    {
        $pengguna = Pengguna::where('username', 'administrator')->firstOrFail();
        $notifikasi = $this->buatNotifikasi($pengguna, 'Nama siswa dan rincian privat');
        $notifikasi->update(['tautan' => '/nilai-saya']);
        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.notifikasi.tujuan', $notifikasi))
            ->assertOk()->assertExactJson(['data' => [
                'id' => $notifikasi->id, 'tautan_mobile' => '/nilai-saya',
                'judul' => $notifikasi->judul, 'pesan' => $notifikasi->pesan,
            ]])->assertHeader('Cache-Control', 'no-store, private');
        $this->assertNull($notifikasi->fresh()->dibaca_pada);

        Auth::forgetGuards();
        $this->withToken($this->token($this->buatPengguna('akun-push-lain')))
            ->getJson(route('api.v1.notifikasi.tujuan', $notifikasi))->assertForbidden()
            ->assertDontSee($notifikasi->judul)->assertDontSee($notifikasi->pesan);
        $this->assertNull($notifikasi->fresh()->dibaca_pada);
    }

    public function test_tujuan_push_memerlukan_sesi_akun_aktif_dan_sandi_bukan_default(): void
    {
        $pengguna = $this->buatPengguna('akun-push-status');
        $notifikasi = $this->buatNotifikasi($pengguna, 'Pesan status');
        $url = route('api.v1.notifikasi.tujuan', $notifikasi);
        $this->getJson($url)->assertUnauthorized()->assertDontSee($notifikasi->judul);

        $pengguna->update(['wajib_ganti_kata_sandi' => true]);
        $token = $this->token($pengguna);
        $this->withToken($token)->getJson($url)->assertStatus(428)->assertDontSee($notifikasi->judul);

        $pengguna->update(['wajib_ganti_kata_sandi' => false, 'aktif' => false]);
        $this->withToken($token)->getJson($url)->assertUnauthorized()->assertDontSee($notifikasi->judul);
        $this->assertNull($notifikasi->fresh()->dibaca_pada);
    }

    public function test_pengguna_dapat_menandai_notifikasi_miliknya_sudah_dibaca(): void
    {
        $pengguna = Pengguna::where('username', 'administrator')->firstOrFail();
        $notifikasi = $this->buatNotifikasi($pengguna, 'Notifikasi milik sendiri');
        $notifikasiLain = $this->buatNotifikasi(
            $this->buatPengguna('pengguna.lain'),
            'Notifikasi pengguna lain',
        );

        $this->withToken($this->token($pengguna))
            ->patchJson(route('api.v1.notifikasi.baca', $notifikasi))
            ->assertOk()
            ->assertJsonPath('data.id', $notifikasi->id)
            ->assertJsonPath('data.jumlah_belum_dibaca', 0);

        $this->assertNotNull($notifikasi->fresh()->dibaca_pada);

        $this->withToken($this->token($pengguna))
            ->patchJson(route('api.v1.notifikasi.baca', $notifikasiLain))
            ->assertForbidden();

        $this->assertNull($notifikasiLain->fresh()->dibaca_pada);
    }

    public function test_pengguna_dapat_menandai_semua_notifikasi_miliknya_sudah_dibaca(): void
    {
        $pengguna = Pengguna::where('username', 'administrator')->firstOrFail();
        $penggunaLain = $this->buatPengguna('pengguna.lain');
        $milikSendiri = collect([
            $this->buatNotifikasi($pengguna, 'Notifikasi pertama'),
            $this->buatNotifikasi($pengguna, 'Notifikasi kedua'),
        ]);
        $milikPenggunaLain = $this->buatNotifikasi($penggunaLain, 'Tetap belum dibaca');

        $this->withToken($this->token($pengguna))
            ->patchJson(route('api.v1.notifikasi.baca-semua'))
            ->assertOk()
            ->assertJsonPath('data.jumlah_ditandai', 2)
            ->assertJsonPath('data.jumlah_belum_dibaca', 0);

        $milikSendiri->each(
            fn (NotifikasiPengguna $item) => $this->assertNotNull($item->fresh()->dibaca_pada),
        );
        $this->assertNull($milikPenggunaLain->fresh()->dibaca_pada);
    }

    public function test_tujuan_notifikasi_web_dipetakan_hanya_ke_halaman_native_yang_dikenal(): void
    {
        $service = app(TujuanNotifikasiMobileService::class);

        $kasus = [
            ['/notifikasi', 'tahfidz-pertemuan:1:12', '/tahfidz-saya'],
            ['/notifikasi?siswa=99&tujuan=/role-hak-akses', 'tahfidz-rapor:1:2', '/tahfidz-saya'],
            ['/notifikasi', 'tahfidz-sertifikat:1:2', '/tahfidz-saya'],
            ['/notifikasi', 'tahfidz-tidak-dikenal:1:2', null],
            ['/notifikasi', 'tahfidz-pertemuan:1:0', null],
            ['/tahfidz-saya?siswa=99', null, '/tahfidz-saya'],
            ['/tahfidz-anak?siswa=12&tujuan=/role-hak-akses', 'tahfidz-pertemuan:1:12', '/tahfidz-anak?siswa=12'],
            ['/tahfidz-anak?siswa[]=12', null, '/tahfidz-anak'],
            ['/presensi-anak?siswa_id=12&bulan=2026-10&catatan=privat', 'presensi-masuk-orang-tua:1', '/kehadiran-anak-saya?siswa_id=12&bulan=2026-10'],
            ['/notifikasi?siswa_id=13&bulan=2026-09', 'presensi-masuk-siswa:1', '/kehadiran-saya?siswa_id=13&bulan=2026-09'],
            ['/kehadiran-anak-saya?siswa_id[]=12&bulan=2026-99', null, '/kehadiran-anak-saya'],
            ['/pengajuan-barang/12', null, '/pengajuan-barang/12'],
            ['/pengajuan-barang-saya/13', null, '/pengajuan-saya/13'],
            ['/tugas-pengawas-ujian/14?kembali=panitia', null, '/tugas-pengawas-ujian/14'],
            ['/konfirmasi-berhalangan-ibadah/15', null, '/konfirmasi-berhalangan-ibadah/15'],
            ['/pemeriksaan-perangkat-ajar/guru/16?semester=1', null, '/pemeriksaan-perangkat-ajar/guru/16'],
            ['/perangkat-ajar-saya/17', null, '/perangkat-ajar-saya/17'],
            ['/nilai-saya?tahun_pelajaran_id=1', null, '/nilai-saya'],
            ['/ujian-anak-saya', null, '/ujian-anak-saya'],
            ['/akademik-anak?tab=nilai&semester=genap&tahun_pelajaran_id=1', null, '/nilai-anak-saya?semester=genap'],
            ['/akademik-anak?tab=jadwal', null, '/jadwal-pelajaran-anak'],
            ['/hasil-survei-saya#rincian-survei', null, '/hasil-survei-saya'],
            ['/dashboard-humas', null, '/humas'],
            ['/agenda-humas/4', null, '/humas/agenda/4'],
            ['/dokumen-humas/8', null, '/humas/dokumen/8'],
            ['/pengaduan-humas/12', null, '/humas/pengaduan/12'],
            ['/pengaduan-saya/12', null, '/humas/pengaduan-saya/12'],
            ['/pertemuan-saya/4', null, '/humas/pertemuan-saya/4'],
            ['/umpan-balik-saya/6', null, '/humas/umpan-balik-saya/6'],
            ['/umpan-balik-humas/6', null, '/humas/umpan-balik/6'],
            ['/persuratan/7?catatan=privat', null, '/persuratan/7'],
            ['/surat-saya/8', null, '/surat-saya/8'],
            ['/tindak-lanjut-surat/9', null, '/tindak-lanjut-surat/9'],
            ['/persuratan', null, '/persuratan'],
            ['/surat-saya', null, '/surat-saya'],
            ['/tindak-lanjut-surat', null, '/tindak-lanjut-surat'],
            ['/persuratan/pengesahan', null, '/persuratan/pengesahan'],
            ['/persuratan/tindak-lanjut', null, '/persuratan/tindak-lanjut'],
            ['/persuratan/7/berkas/8', null, null],
            ['/agenda-humas/4/cetak', null, null],
            ['/sanksi-poin-siswa/18', null, '/pelaksanaan-sanksi-siswa/18'],
            ['/progress-kasus-saya/22', 'perkembangan-kasus-siswa:22:disahkan', '/progress-kasus-saya/22'],
            ['/pembinaan-poin-anak/23', 'perkembangan-kasus-orang-tua:23:disahkan', '/pembinaan-poin-anak/23'],
            ['/pembinaan-poin-anak?tab=poin', null, '/pembinaan-poin-anak?tab=poin'],
            ['/laporan-pembinaan-siswa/19', 'laporan-pembinaan-wali-kelas:19', '/laporan-siswa-kelas/19'],
            ['/laporan-pembinaan-siswa/20', 'batas-proses:20:bk:202609070800:peringatan', '/pemeriksaan-pengesahan/20'],
            ['/laporan-pembinaan-siswa/21', 'keputusan-bk:21:pembinaan', '/laporan-saya/21'],
            ['/fitur-web-yang-belum-native/99', null, null],
            ['https://contoh.test/fitur-web-yang-belum-native/99', null, null],
        ];

        foreach ($kasus as [$tautan, $kunci, $tujuan]) {
            $notifikasi = new NotifikasiPengguna([
                'tautan' => $tautan,
                'kunci_unik' => $kunci,
            ]);

            $this->assertSame($tujuan, $service->untuk($notifikasi));
        }
    }

    private function buatNotifikasi(Pengguna $pengguna, string $judul): NotifikasiPengguna
    {
        return NotifikasiPengguna::create([
            'pengguna_id' => $pengguna->id,
            'jenis' => 'informasi',
            'judul' => $judul,
            'pesan' => 'Isi notifikasi untuk pengujian API mobile.',
        ]);
    }

    private function buatPengguna(string $username): Pengguna
    {
        return Pengguna::create([
            'nama' => 'Pengguna Lain',
            'username' => $username,
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
