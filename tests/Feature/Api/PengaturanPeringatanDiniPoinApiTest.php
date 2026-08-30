<?php

namespace Tests\Feature\Api;

use App\Models\Izin;
use App\Models\PengaturanPeringatanDiniPoin;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\TahunPelajaran;
use App\Services\Pembinaan\PengaturanPeringatanDiniPoinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PengaturanPeringatanDiniPoinApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_memerlukan_token_dan_mengirim_nilai_bawaan_ringkasan_filter_serta_menu_native(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = $this->buatTahun('2026/2027', true);

        $this->getJson(route('api.v1.pengaturan-peringatan-dini-poin.index'))
            ->assertUnauthorized();

        $response = $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.pengaturan-peringatan-dini-poin.index', [
                'cari' => '2026',
                'status' => 'aktif',
            ]))
            ->assertOk()
            ->assertJsonPath('data.filter.cari', '2026')
            ->assertJsonPath('data.filter.status', 'aktif')
            ->assertJsonPath('data.ringkasan.jumlah_tahun', 1)
            ->assertJsonPath('data.ringkasan.tahun_aktif_id', $tahun->id)
            ->assertJsonPath('data.ringkasan.sudah_diatur', 0)
            ->assertJsonPath('data.ringkasan.deteksi_aktif', 1)
            ->assertJsonPath('data.ringkasan.notifikasi_aktif', 1)
            ->assertJsonPath('data.hak_akses.dapat_kelola', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.tahun_pelajaran.nama', '2026/2027')
            ->assertJsonPath('data.items.0.tersimpan', false)
            ->assertJsonPath('data.items.0.deteksi_aktif', true)
            ->assertJsonPath('data.items.0.notifikasi_aktif', true)
            ->assertJsonPath('data.items.0.persentase_mendekati_ambang', 80)
            ->assertJsonPath('data.items.0.jumlah_pelanggaran_berulang', 3)
            ->assertJsonPath('data.items.0.periode_pelanggaran_hari', 30)
            ->assertJsonPath('data.items.0.jumlah_keterlambatan_berulang', 3)
            ->assertJsonPath('data.items.0.periode_keterlambatan_hari', 30);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'peringatan-dini-poin',
                'status' => 'tersedia',
                'rute' => '/pengaturan-peringatan-dini-poin',
            ]);
    }

    public function test_administrator_dapat_menyimpan_pengaturan_dan_mesin_membaca_nilai_yang_sama(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = $this->buatTahun('2027/2028', true);
        $token = $this->token($administrator);

        $this->withToken($token)
            ->putJson(route('api.v1.pengaturan-peringatan-dini-poin.update', $tahun), [
                'aktif' => false,
                'notifikasi_aktif' => false,
                'persentase_mendekati_ambang' => 85,
                'jumlah_pelanggaran_berulang' => 4,
                'periode_pelanggaran_hari' => 45,
                'jumlah_keterlambatan_berulang' => 5,
                'periode_keterlambatan_hari' => 60,
            ])
            ->assertOk()
            ->assertJsonPath('pesan', 'Pengaturan peringatan dini tahun 2027/2028 berhasil disimpan.');

        $this->assertDatabaseHas('pengaturan_peringatan_dini_poin', [
            'tahun_pelajaran_id' => $tahun->id,
            'aktif' => false,
            'notifikasi_aktif' => false,
            'persentase_mendekati_ambang' => 85,
            'jumlah_pelanggaran_berulang' => 4,
            'periode_pelanggaran_hari' => 45,
            'jumlah_keterlambatan_berulang' => 5,
            'periode_keterlambatan_hari' => 60,
            'diperbarui_oleh_pengguna_id' => $administrator->id,
        ]);

        $nilaiMesin = app(PengaturanPeringatanDiniPoinService::class)->nilaiUntukTahun($tahun->id);
        $this->assertFalse($nilaiMesin->aktif);
        $this->assertFalse($nilaiMesin->notifikasi_aktif);
        $this->assertSame(85, $nilaiMesin->persentase_mendekati_ambang);

        $this->withToken($token)
            ->getJson(route('api.v1.pengaturan-peringatan-dini-poin.index', ['status' => 'nonaktif']))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.sudah_diatur', 1)
            ->assertJsonPath('data.ringkasan.deteksi_aktif', 0)
            ->assertJsonPath('data.ringkasan.notifikasi_aktif', 0)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.tersimpan', true)
            ->assertJsonPath('data.items.0.persentase_mendekati_ambang', 85);
    }

    public function test_batas_pengaturan_divalidasi(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = $this->buatTahun('2028/2029', false);

        $this->withToken($this->token($administrator))
            ->putJson(route('api.v1.pengaturan-peringatan-dini-poin.update', $tahun), [
                'aktif' => true,
                'notifikasi_aktif' => true,
                'persentase_mendekati_ambang' => 49,
                'jumlah_pelanggaran_berulang' => 1,
                'periode_pelanggaran_hari' => 6,
                'jumlah_keterlambatan_berulang' => 31,
                'periode_keterlambatan_hari' => 366,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'persentase_mendekati_ambang',
                'jumlah_pelanggaran_berulang',
                'periode_pelanggaran_hari',
                'jumlah_keterlambatan_berulang',
                'periode_keterlambatan_hari',
            ]);

        $this->assertSame(0, PengaturanPeringatanDiniPoin::query()->count());
    }

    public function test_pengguna_tanpa_izin_pengaturan_poin_tidak_dapat_membuka_modul(): void
    {
        $pengguna = $this->penggunaDenganIzin('poin_siswa.lihat');

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.pengaturan-peringatan-dini-poin.index'))
            ->assertForbidden();
    }

    private function buatTahun(string $nama, bool $aktif): TahunPelajaran
    {
        $awal = (int) substr($nama, 0, 4);

        return TahunPelajaran::create([
            'nama' => $nama,
            'tanggal_mulai' => $awal.'-07-01',
            'tanggal_selesai' => ($awal + 1).'-06-30',
            'aktif' => $aktif,
        ]);
    }

    private function penggunaDenganIzin(string $kodeIzin): Pengguna
    {
        $peran = Peran::create([
            'nama' => 'Pembaca Peringatan Dini Mobile',
            'kode' => 'pembaca_peringatan_dini_mobile',
            'aktif' => true,
            'sistem' => false,
        ]);
        $peran->izin()->attach(Izin::where('kode', $kodeIzin)->firstOrFail());
        $pengguna = Pengguna::create([
            'nama' => 'Pembaca Peringatan Dini Mobile',
            'username' => 'pembaca.peringatan.dini',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $pengguna->daftarPeran()->attach($peran);

        return $pengguna;
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Perangkat Peringatan Dini', ['mobile'])->plainTextToken;
    }
}
