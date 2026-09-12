<?php

namespace Tests\Feature\Api;

use App\Models\NotifikasiPengguna;
use App\Models\Pengguna;
use App\Services\Mobile\TujuanNotifikasiMobileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotifikasiApiTest extends TestCase
{
    use RefreshDatabase;

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
            ['/pengajuan-barang/12', null, '/pengajuan-barang/12'],
            ['/pengajuan-barang-saya/13', null, '/pengajuan-saya/13'],
            ['/tugas-pengawas-ujian/14?kembali=panitia', null, '/tugas-pengawas-ujian/14'],
            ['/konfirmasi-berhalangan-ibadah/15', null, '/konfirmasi-berhalangan-ibadah/15'],
            ['/pemeriksaan-perangkat-ajar/guru/16?semester=1', null, '/pemeriksaan-perangkat-ajar/guru/16'],
            ['/perangkat-ajar-saya/17', null, '/perangkat-ajar-saya/17'],
            ['/nilai-saya?tahun_pelajaran_id=1', null, '/nilai-saya'],
            ['/akademik-anak?tab=nilai&semester=genap&tahun_pelajaran_id=1', null, '/nilai-anak-saya?semester=genap'],
            ['/akademik-anak?tab=jadwal', null, '/jadwal-pelajaran-anak'],
            ['/hasil-survei-saya#rincian-survei', null, '/hasil-survei-saya'],
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
