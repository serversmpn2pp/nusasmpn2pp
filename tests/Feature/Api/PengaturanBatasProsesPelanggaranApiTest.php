<?php

namespace Tests\Feature\Api;

use App\Models\Izin;
use App\Models\PengaturanBatasProsesPelanggaran;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\TahunPelajaran;
use App\Services\Pembinaan\PengaturanBatasProsesPelanggaranService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PengaturanBatasProsesPelanggaranApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_memerlukan_token_dan_mengirim_nilai_bawaan_ringkasan_filter_serta_menu_native(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = $this->buatTahun('2026/2027', true);

        $this->getJson(route('api.v1.pengaturan-batas-proses-pelanggaran.index'))
            ->assertUnauthorized();

        $response = $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.pengaturan-batas-proses-pelanggaran.index', [
                'cari' => '2026',
                'status' => 'bawaan',
            ]))
            ->assertOk()
            ->assertJsonPath('data.filter.cari', '2026')
            ->assertJsonPath('data.filter.status', 'bawaan')
            ->assertJsonPath('data.ringkasan.jumlah_tahun', 1)
            ->assertJsonPath('data.ringkasan.tahun_aktif_id', $tahun->id)
            ->assertJsonPath('data.ringkasan.sudah_diatur', 0)
            ->assertJsonPath('data.ringkasan.memakai_bawaan', 1)
            ->assertJsonPath('data.ringkasan.pengingat_aktif', 1)
            ->assertJsonPath('data.ringkasan.terlambat_aktif', 1)
            ->assertJsonPath('data.hak_akses.dapat_kelola', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.tahun_pelajaran.nama', '2026/2027')
            ->assertJsonPath('data.items.0.tersimpan', false)
            ->assertJsonPath('data.items.0.batas_hari_pemeriksaan_bk', 2)
            ->assertJsonPath('data.items.0.batas_hari_persetujuan', 2)
            ->assertJsonPath('data.items.0.pengingat_hari_sebelum_batas', 1)
            ->assertJsonPath('data.items.0.notifikasi_pengingat_aktif', true)
            ->assertJsonPath('data.items.0.notifikasi_terlambat_aktif', true);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'batas-proses-pelanggaran',
                'status' => 'tersedia',
                'rute' => '/pengaturan-batas-proses-pelanggaran',
            ]);
    }

    public function test_administrator_dapat_menyimpan_dan_mesin_memakai_tenggat_tahap_yang_sama(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = $this->buatTahun('2027/2028', true);
        $token = $this->token($administrator);

        $this->withToken($token)
            ->putJson(route('api.v1.pengaturan-batas-proses-pelanggaran.update', $tahun), [
                'batas_hari_pemeriksaan_bk' => 4,
                'batas_hari_persetujuan' => 3,
                'pengingat_hari_sebelum_batas' => 2,
                'notifikasi_pengingat_aktif' => true,
                'notifikasi_terlambat_aktif' => false,
            ])
            ->assertOk()
            ->assertJsonPath('pesan', 'Batas proses pelanggaran tahun 2027/2028 berhasil disimpan.');

        $this->assertDatabaseHas('pengaturan_batas_proses_pelanggaran', [
            'tahun_pelajaran_id' => $tahun->id,
            'batas_hari_pemeriksaan_bk' => 4,
            'batas_hari_persetujuan' => 3,
            'pengingat_hari_sebelum_batas' => 2,
            'notifikasi_pengingat_aktif' => true,
            'notifikasi_terlambat_aktif' => false,
            'diperbarui_oleh_pengguna_id' => $administrator->id,
        ]);

        $mesin = app(PengaturanBatasProsesPelanggaranService::class);
        $this->assertSame(
            [PengaturanBatasProsesPelanggaranService::TAHAP_PEMERIKSAAN_BK, 4],
            $mesin->tahapDanJumlahHari('diajukan', $tahun->id),
        );
        $this->assertSame(
            [PengaturanBatasProsesPelanggaranService::TAHAP_PENGESAHAN_WAKIL, 3],
            $mesin->tahapDanJumlahHari('menunggu_pengesahan_wakil', $tahun->id),
        );

        $this->withToken($token)
            ->getJson(route('api.v1.pengaturan-batas-proses-pelanggaran.index', ['status' => 'diatur']))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.sudah_diatur', 1)
            ->assertJsonPath('data.ringkasan.memakai_bawaan', 0)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.tersimpan', true)
            ->assertJsonPath('data.items.0.batas_hari_pemeriksaan_bk', 4)
            ->assertJsonPath('data.items.0.notifikasi_terlambat_aktif', false);
    }

    public function test_pengingat_harus_lebih_kecil_daripada_tenggat_terpendek(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = $this->buatTahun('2028/2029', false);

        $this->withToken($this->token($administrator))
            ->putJson(route('api.v1.pengaturan-batas-proses-pelanggaran.update', $tahun), [
                'batas_hari_pemeriksaan_bk' => 4,
                'batas_hari_persetujuan' => 2,
                'pengingat_hari_sebelum_batas' => 2,
                'notifikasi_pengingat_aktif' => true,
                'notifikasi_terlambat_aktif' => true,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pengingat_hari_sebelum_batas']);

        $this->assertSame(0, PengaturanBatasProsesPelanggaran::query()->count());
    }

    public function test_pengguna_tanpa_izin_pengaturan_poin_tidak_dapat_membuka_modul(): void
    {
        $pengguna = $this->penggunaDenganIzin('poin_siswa.lihat');

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.pengaturan-batas-proses-pelanggaran.index'))
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
            'nama' => 'Pembaca Batas Proses Mobile',
            'kode' => 'pembaca_batas_proses_mobile',
            'aktif' => true,
            'sistem' => false,
        ]);
        $peran->izin()->attach(Izin::where('kode', $kodeIzin)->firstOrFail());
        $pengguna = Pengguna::create([
            'nama' => 'Pembaca Batas Proses Mobile',
            'username' => 'pembaca.batas.proses',
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
        return $pengguna->createToken('Perangkat Batas Proses', ['mobile'])->plainTextToken;
    }
}
