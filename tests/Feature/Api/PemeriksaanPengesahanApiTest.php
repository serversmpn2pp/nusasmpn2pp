<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\JenisPelanggaranSiswa;
use App\Models\Kelas;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Pembinaan\ProsesPoinSiswaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PemeriksaanPengesahanApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_bk_melihat_antrean_detail_referensi_dan_memberi_rekomendasi_poin(): void
    {
        $data = $this->dataDasar();
        $laporan = $this->buatLaporan($data, 'PV-MOB-001');
        $token = $this->token($data['bk']);

        $response = $this->withToken($token)
            ->getJson(route('api.v1.pemeriksaan-pengesahan.index', [
                'antrean' => 'bk',
                'kata_kunci' => 'Siswa Pemeriksaan Native',
            ]))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.aktif', 1)
            ->assertJsonPath('data.ringkasan.bk', 1)
            ->assertJsonPath('data.paginasi.total', 1)
            ->assertJsonPath('data.items.0.nomor_laporan', 'PV-MOB-001')
            ->assertJsonPath('data.items.0.tugas_pengguna', 'Menunggu keputusan BK')
            ->assertJsonPath('data.items.0.kelengkapan_fakta.kronologi', true)
            ->assertJsonPath('data.hak_akses.dapat_verifikasi_bk', true)
            ->assertJsonPath('data.hak_akses.dapat_sahkan_wakil', false);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->withToken($token)
            ->getJson(route('api.v1.pemeriksaan-pengesahan.show', $laporan))
            ->assertOk()
            ->assertJsonPath('data.laporan.kronologi', 'Kronologi faktual untuk pemeriksaan native.')
            ->assertJsonPath('data.proses.tahap_aktif', 1)
            ->assertJsonPath('data.hak_aksi.dapat_verifikasi_bk', true)
            ->assertJsonPath('data.hak_aksi.dapat_sahkan_wakil', false)
            ->assertJsonFragment([
                'kode' => 'sanksi_poin',
                'label' => 'Tetapkan Sanksi Poin',
            ])
            ->assertJsonFragment([
                'id' => $data['jenis']->id,
                'kode' => $data['jenis']->kode,
                'nama' => $data['jenis']->nama,
                'poin' => $data['jenis']->poin,
            ]);

        $this->withToken($token)
            ->postJson(route('api.v1.pemeriksaan-pengesahan.verifikasi-bk', $laporan), [
                'hasil' => 'sanksi_poin',
                'jenis_pelanggaran_ids' => [$data['jenis']->id],
                'catatan' => 'Bukti dan klarifikasi mendukung rekomendasi poin.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status_verifikasi', 'menunggu_pengesahan_wakil')
            ->assertJsonPath('data.total_poin', $data['jenis']->poin);

        $this->assertDatabaseHas('verifikasi_bk_pelanggaran', [
            'laporan_pembinaan_siswa_id' => $laporan->id,
            'pengguna_id' => $data['bk']->id,
            'hasil' => 'sanksi_poin',
        ]);
        $this->assertDatabaseMissing('transaksi_poin_siswa', [
            'kunci_sumber' => 'pelanggaran:'.$laporan->id,
        ]);

        $this->withToken($token)
            ->postJson(route('api.v1.pemeriksaan-pengesahan.verifikasi-bk', $laporan), [
                'hasil' => 'pembinaan',
            ])
            ->assertUnprocessable();
    }

    public function test_wakil_dapat_mengembalikan_rekomendasi_dengan_catatan(): void
    {
        $data = $this->dataDasar();
        $laporan = $this->buatLaporan($data, 'PV-MOB-002');
        $this->siapkanRekomendasiPoin($data, $laporan);
        $token = $this->token($data['wakil']);

        $this->withToken($token)
            ->getJson(route('api.v1.pemeriksaan-pengesahan.index', ['antrean' => 'wakil']))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.wakil', 1)
            ->assertJsonPath('data.items.0.id', $laporan->id)
            ->assertJsonPath('data.items.0.tahap_aktif', 2)
            ->assertJsonPath('data.hak_akses.dapat_sahkan_wakil', true);

        $this->withToken($token)
            ->getJson(route('api.v1.pemeriksaan-pengesahan.show', $laporan))
            ->assertOk()
            ->assertJsonPath('data.hak_aksi.dapat_verifikasi_bk', false)
            ->assertJsonPath('data.hak_aksi.dapat_sahkan_wakil', true)
            ->assertJsonPath('data.pemeriksaan_bk.0.hasil', 'sanksi_poin');

        $this->withToken($token)
            ->postJson(route('api.v1.pemeriksaan-pengesahan.pengesahan-wakil', $laporan), [
                'keputusan' => 'kembalikan',
                'catatan' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('catatan');

        $this->withToken($token)
            ->postJson(route('api.v1.pemeriksaan-pengesahan.pengesahan-wakil', $laporan), [
                'keputusan' => 'kembalikan',
                'catatan' => 'Bukti perlu diperjelas sebelum poin disahkan.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status_verifikasi', 'dikembalikan_bk');

        $this->assertDatabaseHas('persetujuan_pelanggaran', [
            'laporan_pembinaan_siswa_id' => $laporan->id,
            'jenis_persetujuan' => 'wakil_kesiswaan',
            'keputusan' => 'tidak_setuju',
            'catatan' => 'Bukti perlu diperjelas sebelum poin disahkan.',
        ]);
        $this->assertDatabaseMissing('transaksi_poin_siswa', [
            'kunci_sumber' => 'pelanggaran:'.$laporan->id,
        ]);
    }

    public function test_wakil_mengesahkan_rekomendasi_dan_poin_resmi_tercatat(): void
    {
        $data = $this->dataDasar();
        $laporan = $this->buatLaporan($data, 'PV-MOB-003');
        $this->siapkanRekomendasiPoin($data, $laporan);

        $this->withToken($this->token($data['wakil']))
            ->postJson(route('api.v1.pemeriksaan-pengesahan.pengesahan-wakil', $laporan), [
                'keputusan' => 'sahkan',
                'catatan' => 'Rekomendasi BK sesuai bukti pemeriksaan.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status_verifikasi', 'disahkan')
            ->assertJsonPath('data.total_poin', $data['jenis']->poin);

        $this->assertDatabaseHas('transaksi_poin_siswa', [
            'laporan_pembinaan_siswa_id' => $laporan->id,
            'kunci_sumber' => 'pelanggaran:'.$laporan->id,
            'poin' => $data['jenis']->poin,
        ]);
    }

    public function test_endpoint_memerlukan_token_pegawai_dan_izin_yang_sesuai(): void
    {
        $this->getJson(route('api.v1.pemeriksaan-pengesahan.index'))->assertUnauthorized();

        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Pegawai Tanpa Izin Pemeriksaan',
            'nip' => '197001012000011111',
            'aktif' => true,
        ]);
        $akun = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip,
            'kata_sandi' => 'KataSandi-Uji-2026',
            'wajib_ganti_kata_sandi' => false,
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);

        $this->withToken($this->token($akun))
            ->getJson(route('api.v1.pemeriksaan-pengesahan.index'))
            ->assertForbidden();
    }

    private function dataDasar(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VIII.A',
            'tingkat' => 8,
            'aktif' => true,
        ]);
        $siswa = Siswa::create([
            'nama_lengkap' => 'Siswa Pemeriksaan Native',
            'nis' => 'VER-001',
            'nisn' => '0099007788',
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'status_keanggotaan' => 'aktif',
        ]);
        $jenis = JenisPelanggaranSiswa::query()->where('aktif', true)->orderBy('urutan')->firstOrFail();
        $bk = $this->akunPegawai('BK Pemeriksaan Native', '197101012001011001', 'bk');
        $wakil = $this->akunPegawai('Wakil Pemeriksaan Native', '197202022002021002', 'wakil_pimpinan_kesiswaan');

        return compact('tahun', 'kelas', 'siswa', 'jenis', 'bk', 'wakil');
    }

    private function akunPegawai(string $nama, string $nip, string $peran): Pengguna
    {
        $pegawai = Pegawai::create(['nama_lengkap' => $nama, 'nip' => $nip, 'aktif' => true]);
        $akun = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $nama,
            'username' => $nip,
            'kata_sandi' => 'KataSandi-Uji-2026',
            'wajib_ganti_kata_sandi' => false,
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);
        $akun->daftarPeran()->attach(Peran::where('kode', $peran)->firstOrFail());

        return $akun;
    }

    private function buatLaporan(array $data, string $nomor): LaporanPembinaanSiswa
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        return LaporanPembinaanSiswa::create([
            'nomor_laporan' => $nomor,
            'jenis_laporan' => 'kejadian',
            'sumber_laporan' => 'manual',
            'tanggal_kejadian' => '2026-08-30',
            'waktu_kejadian' => '09:15',
            'tempat_kejadian' => 'Halaman sekolah',
            'siswa_id' => $data['siswa']->id,
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $data['kelas']->id,
            'tingkat' => 'ringan',
            'status' => 'baru',
            'status_verifikasi' => 'diajukan',
            'tahap_batas_proses' => 'pemeriksaan_bk',
            'batas_proses_pada' => now()->addDays(2),
            'total_poin' => 0,
            'kronologi' => 'Kronologi faktual untuk pemeriksaan native.',
            'tindakan_awal' => 'Siswa diarahkan untuk memberi klarifikasi.',
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);
    }

    private function siapkanRekomendasiPoin(array $data, LaporanPembinaanSiswa $laporan): void
    {
        app(ProsesPoinSiswaService::class)->siapkanSanksiPoin($laporan, [$data['jenis']->id]);
        $laporan->refresh()->update([
            'status_verifikasi' => 'menunggu_pengesahan_wakil',
            'tahap_batas_proses' => 'pengesahan_wakil',
            'batas_proses_pada' => now()->addDays(2),
        ]);
        $laporan->verifikasiBkPelanggaran()->create([
            'bk_pegawai_id' => $data['bk']->pegawai_id,
            'pengguna_id' => $data['bk']->id,
            'hasil' => 'sanksi_poin',
            'catatan' => 'Rekomendasi poin untuk pengujian pengesahan.',
            'diverifikasi_pada' => now(),
        ]);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Perangkat Pemeriksaan Pengesahan', ['mobile'])->plainTextToken;
    }
}
