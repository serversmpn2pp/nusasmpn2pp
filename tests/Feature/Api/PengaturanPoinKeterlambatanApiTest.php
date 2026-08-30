<?php

namespace Tests\Feature\Api;

use App\Models\Izin;
use App\Models\LaporanPembinaanSiswa;
use App\Models\PengaturanPoinKeterlambatan;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PengaturanPoinKeterlambatanApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_memerlukan_token_dan_mengirim_nilai_bawaan_ringkasan_filter_serta_menu_native(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = $this->buatTahun('2026/2027', true);

        $this->getJson(route('api.v1.pengaturan-poin-keterlambatan.index'))
            ->assertUnauthorized();

        $response = $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.pengaturan-poin-keterlambatan.index', [
                'cari' => '2026',
                'status' => 'nonaktif',
            ]))
            ->assertOk()
            ->assertJsonPath('data.filter.cari', '2026')
            ->assertJsonPath('data.filter.status', 'nonaktif')
            ->assertJsonPath('data.ringkasan.jumlah_tahun', 1)
            ->assertJsonPath('data.ringkasan.tahun_aktif_id', $tahun->id)
            ->assertJsonPath('data.ringkasan.sudah_diatur', 0)
            ->assertJsonPath('data.ringkasan.otomatis_aktif', 0)
            ->assertJsonPath('data.hak_akses.dapat_kelola', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.tahun_pelajaran.nama', '2026/2027')
            ->assertJsonPath('data.items.0.tersimpan', false)
            ->assertJsonPath('data.items.0.otomatis_aktif', false)
            ->assertJsonPath('data.items.0.rentang.0.menit_mulai', 1)
            ->assertJsonPath('data.items.0.rentang.0.menit_selesai', 10)
            ->assertJsonPath('data.items.0.rentang.0.poin', 0)
            ->assertJsonPath('data.items.0.rentang.1.menit_mulai', 11)
            ->assertJsonPath('data.items.0.rentang.1.menit_selesai', null)
            ->assertJsonPath('data.items.0.rentang.1.poin', 15);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'poin-keterlambatan',
                'status' => 'tersedia',
                'rute' => '/pengaturan-poin-keterlambatan',
            ]);
    }

    public function test_administrator_dapat_menyimpan_rentang_berurutan_dan_riwayat_laporan_tetap_utuh_saat_diubah(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = $this->buatTahun('2027/2028', true);
        $token = $this->token($administrator);

        $this->withToken($token)
            ->putJson(route('api.v1.pengaturan-poin-keterlambatan.update', $tahun), [
                'aktif' => true,
                'rentang' => [
                    ['menit_mulai' => 31, 'menit_selesai' => null, 'poin' => 25],
                    ['menit_mulai' => 1, 'menit_selesai' => 10, 'poin' => 0],
                    ['menit_mulai' => 11, 'menit_selesai' => 30, 'poin' => 15],
                ],
            ])
            ->assertOk();

        $pengaturan = PengaturanPoinKeterlambatan::where('tahun_pelajaran_id', $tahun->id)->firstOrFail();
        $rentangLama = $pengaturan->rentangPoinKeterlambatan()->where('menit_mulai', 11)->firstOrFail();
        $this->assertDatabaseHas('pengaturan_poin_keterlambatan', [
            'tahun_pelajaran_id' => $tahun->id,
            'aktif' => true,
            'diperbarui_oleh_pengguna_id' => $administrator->id,
        ]);
        $this->assertDatabaseHas('rentang_poin_keterlambatan', [
            'pengaturan_poin_keterlambatan_id' => $pengaturan->id,
            'menit_mulai' => 11,
            'menit_selesai' => 30,
            'poin' => 15,
            'urutan' => 2,
        ]);

        $siswa = Siswa::create([
            'nama_lengkap' => 'Siswa Riwayat Keterlambatan API',
            'nis' => 'API-PK-001',
            'aktif' => true,
        ]);
        $laporan = LaporanPembinaanSiswa::create([
            'nomor_laporan' => 'LP-API-PK-001',
            'jenis_laporan' => 'pelanggaran',
            'sumber_laporan' => 'absensi_otomatis',
            'tanggal_kejadian' => '2027-08-01',
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'rentang_poin_keterlambatan_id' => $rentangLama->id,
            'menit_terlambat_tercatat' => 20,
            'tingkat' => 'ringan',
            'total_poin' => 15,
            'kronologi' => 'Terlambat 20 menit.',
        ]);

        $this->withToken($token)
            ->putJson(route('api.v1.pengaturan-poin-keterlambatan.update', $tahun), [
                'aktif' => true,
                'rentang' => [
                    ['menit_mulai' => 1, 'menit_selesai' => 5, 'poin' => 0],
                    ['menit_mulai' => 6, 'menit_selesai' => null, 'poin' => 20],
                ],
            ])
            ->assertOk();

        $this->assertDatabaseHas('laporan_pembinaan_siswa', [
            'id' => $laporan->id,
            'rentang_poin_keterlambatan_id' => null,
            'menit_terlambat_tercatat' => 20,
            'total_poin' => 15,
        ]);
        $this->assertDatabaseHas('rentang_poin_keterlambatan', [
            'pengaturan_poin_keterlambatan_id' => $pengaturan->id,
            'menit_mulai' => 6,
            'menit_selesai' => null,
            'poin' => 20,
        ]);

        $this->withToken($token)
            ->getJson(route('api.v1.pengaturan-poin-keterlambatan.index', ['status' => 'aktif']))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.sudah_diatur', 1)
            ->assertJsonPath('data.ringkasan.otomatis_aktif', 1)
            ->assertJsonPath('data.items.0.tersimpan', true)
            ->assertJsonPath('data.items.0.rentang.1.poin', 20);
    }

    public function test_rentang_harus_dimulai_dari_satu_menyambung_dan_terakhir_tanpa_batas(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = $this->buatTahun('2028/2029', false);
        $token = $this->token($administrator);

        $this->withToken($token)
            ->putJson(route('api.v1.pengaturan-poin-keterlambatan.update', $tahun), [
                'aktif' => true,
                'rentang' => [
                    ['menit_mulai' => 2, 'menit_selesai' => 10, 'poin' => 0],
                    ['menit_mulai' => 12, 'menit_selesai' => 30, 'poin' => 15],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rentang.0.menit_mulai']);

        $this->withToken($token)
            ->putJson(route('api.v1.pengaturan-poin-keterlambatan.update', $tahun), [
                'aktif' => true,
                'rentang' => [
                    ['menit_mulai' => 1, 'menit_selesai' => 10, 'poin' => 0],
                    ['menit_mulai' => 12, 'menit_selesai' => null, 'poin' => 15],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rentang.1.menit_mulai']);

        $this->withToken($token)
            ->putJson(route('api.v1.pengaturan-poin-keterlambatan.update', $tahun), [
                'aktif' => true,
                'rentang' => [
                    ['menit_mulai' => 1, 'menit_selesai' => 10, 'poin' => 0],
                ],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['rentang.0.menit_selesai']);
    }

    public function test_pengguna_tanpa_izin_pengaturan_poin_tidak_dapat_membuka_modul(): void
    {
        $pengguna = $this->penggunaDenganIzin('poin_siswa.lihat');

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.pengaturan-poin-keterlambatan.index'))
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
            'nama' => 'Pembaca Poin Keterlambatan Mobile',
            'kode' => 'pembaca_poin_keterlambatan_mobile',
            'aktif' => true,
            'sistem' => false,
        ]);
        $peran->izin()->attach(Izin::where('kode', $kodeIzin)->firstOrFail());
        $pengguna = Pengguna::create([
            'nama' => 'Pembaca Poin Keterlambatan Mobile',
            'username' => 'pembaca.poin.keterlambatan',
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
        return $pengguna->createToken('Perangkat Poin Keterlambatan', ['mobile'])->plainTextToken;
    }
}
