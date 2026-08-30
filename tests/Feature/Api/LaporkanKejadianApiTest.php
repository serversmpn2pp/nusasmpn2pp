<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LaporkanKejadianApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_referensi_memerlukan_token_dan_menu_native_hanya_tersedia_bagi_pegawai_pelapor(): void
    {
        $data = $this->dataDasar();

        $this->getJson(route('api.v1.laporkan-kejadian.referensi'))
            ->assertUnauthorized();

        $response = $this->withToken($this->token($data['akun']))
            ->getJson(route('api.v1.laporkan-kejadian.referensi'))
            ->assertOk()
            ->assertJsonPath('data.nilai_awal.tahun_pelajaran_id', $data['tahun']->id)
            ->assertJsonPath('data.batas.maksimal_siswa', 100)
            ->assertJsonPath('data.batas.maksimal_saksi', 10)
            ->assertJsonPath('data.batas.maksimal_bukti', 5)
            ->assertJsonCount(1, 'data.tahun_pelajaran')
            ->assertJsonPath('data.kelas.0.nama', 'VII.A')
            ->assertJsonPath('data.siswa.0.nama', 'Siswa Laporan Mobile')
            ->assertJsonPath('data.siswa.0.penempatan.0.kelas_id', $data['kelas']->id);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->withToken($this->token($data['akun']))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'laporkan-kejadian',
                'status' => 'tersedia',
                'rute' => '/laporkan-kejadian',
            ]);
    }

    public function test_pegawai_dapat_melaporkan_beberapa_siswa_dengan_saksi_bukti_tenggat_dan_notifikasi(): void
    {
        Storage::fake('local');
        $data = $this->dataDasar();
        $siswaKedua = Siswa::create([
            'nama_lengkap' => 'Siswa Kedua Laporan Mobile',
            'nis' => 'MOB-002',
            'nisn' => '0099550002',
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $data['kelas']->id,
            'siswa_id' => $siswaKedua->id,
            'status_keanggotaan' => 'aktif',
        ]);

        $this->withHeader('Authorization', 'Bearer '.$this->token($data['akun']))
            ->post(route('api.v1.laporkan-kejadian.store'), [
                'tanggal_kejadian' => '2026-08-30',
                'waktu_kejadian' => '08:15',
                'tempat_kejadian' => 'Koridor sekolah',
                'tahun_pelajaran_id' => $data['tahun']->id,
                'kelas_id' => $data['kelas']->id,
                'siswa_ids' => [$data['siswa']->id, $siswaKedua->id],
                'kronologi' => 'Dua siswa terlibat dalam satu kejadian dan fakta awal telah dicatat.',
                'tindakan_awal' => 'Siswa dipisahkan dan diarahkan kembali ke kelas.',
                'daftar_saksi' => [[
                    'jenis_saksi' => 'pegawai',
                    'nama_saksi' => 'Saksi Guru',
                    'pernyataan' => 'Melihat kejadian dari awal sampai selesai.',
                ]],
                'bukti_laporan' => [UploadedFile::fake()->create('bukti-koridor.pdf', 200, 'application/pdf')],
                'keterangan_bukti' => 'Foto kondisi koridor setelah kejadian.',
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.jumlah_laporan', 2)
            ->assertJsonCount(2, 'data.laporan')
            ->assertJsonPath('data.laporan.0.status_verifikasi', 'diajukan');

        $laporan = LaporanPembinaanSiswa::query()->orderBy('id')->get();
        $this->assertCount(2, $laporan);
        $this->assertSame([$data['siswa']->id, $siswaKedua->id], $laporan->pluck('siswa_id')->all());
        $this->assertTrue($laporan->every(fn ($item) => $item->jenis_laporan === 'kejadian'));
        $this->assertTrue($laporan->every(fn ($item) => $item->sumber_laporan === 'manual'));
        $this->assertTrue($laporan->every(fn ($item) => $item->tahap_batas_proses === 'pemeriksaan_bk'));
        $this->assertTrue($laporan->every(fn ($item) => $item->batas_proses_pada !== null));
        $this->assertDatabaseCount('saksi_laporan_pembinaan_siswa', 2);
        $this->assertDatabaseCount('bukti_laporan_pembinaan_siswa', 2);
        $this->assertDatabaseHas('notifikasi_pengguna', [
            'judul' => 'Laporan kolektif menunggu pemeriksaan BK',
            'kunci_unik' => "laporan-kolektif-baru:{$laporan->first()->id}:{$laporan->last()->id}",
        ]);
    }

    public function test_validasi_mewajibkan_siswa_kronologi_dan_saksi_yang_lengkap(): void
    {
        $data = $this->dataDasar();
        $token = $this->token($data['akun']);

        $this->withToken($token)
            ->postJson(route('api.v1.laporkan-kejadian.store'), [
                'tanggal_kejadian' => '2026-08-30',
                'siswa_ids' => [],
                'kronologi' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['siswa_ids', 'kronologi']);

        $this->withToken($token)
            ->postJson(route('api.v1.laporkan-kejadian.store'), [
                'tanggal_kejadian' => '2026-08-30',
                'siswa_ids' => [$data['siswa']->id],
                'kronologi' => 'Kronologi sudah terisi lengkap.',
                'daftar_saksi' => [[
                    'jenis_saksi' => 'siswa',
                    'nama_saksi' => 'Saksi tanpa pernyataan',
                    'pernyataan' => '',
                ]],
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['daftar_saksi.0.nama_saksi']);

        $this->assertSame(0, LaporanPembinaanSiswa::query()->count());
    }

    public function test_akun_tanpa_izin_pelapor_tidak_dapat_membuka_modul(): void
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Pegawai Tanpa Izin Laporan',
            'nip' => '198909092019091009',
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
            ->getJson(route('api.v1.laporkan-kejadian.referensi'))
            ->assertForbidden();
    }

    public function test_administrator_tanpa_relasi_pegawai_dapat_membuka_dan_mengirim_laporan(): void
    {
        $data = $this->dataDasar();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $token = $this->token($administrator);

        $this->withToken($token)
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'laporkan-kejadian',
                'status' => 'tersedia',
                'rute' => '/laporkan-kejadian',
            ]);

        $this->withToken($token)
            ->postJson(route('api.v1.laporkan-kejadian.store'), [
                'tanggal_kejadian' => '2026-08-30',
                'tahun_pelajaran_id' => $data['tahun']->id,
                'kelas_id' => $data['kelas']->id,
                'siswa_ids' => [$data['siswa']->id],
                'kronologi' => 'Administrator mencatat kejadian untuk diteruskan kepada BK.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.jumlah_laporan', 1);

        $this->assertDatabaseHas('laporan_pembinaan_siswa', [
            'siswa_id' => $data['siswa']->id,
            'pelapor_pegawai_id' => null,
            'dibuat_oleh_pengguna_id' => $administrator->id,
            'jenis_laporan' => 'kejadian',
        ]);
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
            'nama' => 'VII.A',
            'tingkat' => 7,
            'aktif' => true,
        ]);
        $siswa = Siswa::create([
            'nama_lengkap' => 'Siswa Laporan Mobile',
            'nis' => 'MOB-001',
            'nisn' => '0099550001',
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'status_keanggotaan' => 'aktif',
        ]);
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Pegawai Pelapor Mobile',
            'nip' => '198808082018081008',
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
        $akun->daftarPeran()->attach(Peran::where('kode', 'pegawai')->firstOrFail());

        return compact('tahun', 'kelas', 'siswa', 'pegawai', 'akun');
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Perangkat Laporkan Kejadian', ['mobile'])->plainTextToken;
    }
}
