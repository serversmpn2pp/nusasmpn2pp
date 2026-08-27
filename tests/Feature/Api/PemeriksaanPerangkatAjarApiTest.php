<?php

namespace Tests\Feature\Api;

use App\Models\GuruMataPelajaran;
use App\Models\Izin;
use App\Models\JenisPerangkatAjar;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PerangkatAjar;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PemeriksaanPerangkatAjarApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_memerlukan_token_dan_izin_yang_sesuai(): void
    {
        $this->getJson(route('api.v1.pemeriksaan-perangkat-ajar.index'))->assertUnauthorized();

        $pengguna = Pengguna::create([
            'nama' => 'Tanpa Izin Pemeriksaan',
            'username' => 'tanpa-izin-pemeriksaan',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.pemeriksaan-perangkat-ajar.index'))
            ->assertForbidden();
    }

    public function test_daftar_monitoring_dapat_dicari_dan_difilter(): void
    {
        $data = $this->dataDasar();

        $this->withToken($this->token($data['administrator']))
            ->getJson(route('api.v1.pemeriksaan-perangkat-ajar.index', [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'semester' => 1,
            ]))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.jumlah_guru', 1)
            ->assertJsonPath('data.ringkasan.belum_lengkap', 1)
            ->assertJsonPath('data.ringkasan.menunggu_pemeriksaan', 1)
            ->assertJsonPath('data.items.0.pegawai.nama', 'Guru Pemeriksaan Mobile')
            ->assertJsonPath('data.items.0.jumlah_wajib', 2)
            ->assertJsonPath('data.items.0.jumlah_terunggah_wajib', 1)
            ->assertJsonPath('data.items.0.persentase', 50)
            ->assertJsonPath('data.hak_akses.dapat_memeriksa', true);

        $this->withToken($this->token($data['administrator']))
            ->getJson(route('api.v1.pemeriksaan-perangkat-ajar.index', [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'semester' => 1,
                'status_dokumen' => 'menunggu_pemeriksaan',
                'kata_kunci' => 'informatika',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items');

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($data['administrator']))
            ->getJson(route('api.v1.pemeriksaan-perangkat-ajar.index', [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'semester' => 1,
                'kelengkapan' => 'lengkap',
            ]))
            ->assertOk()
            ->assertJsonCount(0, 'data.items');
    }

    public function test_rincian_guru_menyusun_matriks_dan_rincian_dokumen(): void
    {
        $data = $this->dataDasar();
        $token = $this->token($data['administrator']);

        $this->withToken($token)
            ->getJson(route('api.v1.pemeriksaan-perangkat-ajar.guru', [
                'pegawai' => $data['guru'],
                'tahun_pelajaran_id' => $data['tahun']->id,
                'semester' => 1,
            ]))
            ->assertOk()
            ->assertJsonPath('data.pegawai.nama', 'Guru Pemeriksaan Mobile')
            ->assertJsonPath('data.ringkasan.wajib', 2)
            ->assertJsonPath('data.ringkasan.terunggah', 1)
            ->assertJsonCount(2, 'data.penugasan')
            ->assertJsonPath('data.penugasan.0.dokumen.0.perangkat_ajar.id', $data['dokumen']->id)
            ->assertJsonPath('data.penugasan.1.dokumen.0.perangkat_ajar', null);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($data['administrator']))
            ->getJson(route('api.v1.pemeriksaan-perangkat-ajar.dokumen', $data['dokumen']))
            ->assertOk()
            ->assertJsonPath('data.perangkat_ajar.pegawai.nama', 'Guru Pemeriksaan Mobile')
            ->assertJsonPath('data.perangkat_ajar.jenis.nama', 'Modul Ajar')
            ->assertJsonPath('data.perangkat_ajar.status', 'menunggu_pemeriksaan')
            ->assertJsonCount(1, 'data.riwayat');
    }

    public function test_pdf_dapat_diunduh_secara_terautentikasi(): void
    {
        Storage::fake('local');
        $data = $this->dataDasar();
        Storage::disk('local')->put($data['dokumen']->lokasi_file, '%PDF-1.4 dokumen uji');

        $response = $this->withToken($this->token($data['administrator']))
            ->get(route('api.v1.pemeriksaan-perangkat-ajar.file', $data['dokumen']))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');

        $this->assertStringContainsString('private', (string) $response->headers->get('cache-control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));
    }

    public function test_pemeriksa_dapat_meminta_perbaikan_dan_guru_menerima_notifikasi(): void
    {
        $data = $this->dataDasar();
        $token = $this->token($data['administrator']);

        $this->withToken($token)
            ->patchJson(route('api.v1.pemeriksaan-perangkat-ajar.update', $data['dokumen']), [
                'status' => 'perlu_perbaikan',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('catatan_pemeriksa');

        $this->withToken($token)
            ->patchJson(route('api.v1.pemeriksaan-perangkat-ajar.update', $data['dokumen']), [
                'status' => 'perlu_perbaikan',
                'catatan_pemeriksa' => 'Lengkapi langkah pembelajaran dan asesmen.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('perangkat_ajar', [
            'id' => $data['dokumen']->id,
            'status' => 'perlu_perbaikan',
            'catatan_pemeriksa' => 'Lengkapi langkah pembelajaran dan asesmen.',
        ]);
        $this->assertDatabaseHas('notifikasi_pengguna', [
            'pengguna_id' => $data['akun_guru']->id,
            'judul' => 'Perangkat ajar perlu diperbaiki',
        ]);
    }

    public function test_izin_lihat_tidak_dapat_menyimpan_pemeriksaan(): void
    {
        $data = $this->dataDasar();
        $peran = Peran::create([
            'nama' => 'Pembaca Perangkat Ajar Mobile',
            'kode' => 'pembaca_perangkat_ajar_mobile',
            'aktif' => true,
            'sistem' => false,
        ]);
        $peran->izin()->attach(Izin::where('kode', 'perangkat_ajar.lihat')->firstOrFail());
        $pengguna = Pengguna::create([
            'nama' => 'Pembaca Pemeriksaan',
            'username' => 'pembaca-pemeriksaan-mobile',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $pengguna->daftarPeran()->attach($peran);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.pemeriksaan-perangkat-ajar.dokumen', $data['dokumen']))
            ->assertOk()
            ->assertJsonPath('data.hak_akses.dapat_memeriksa', false);

        $this->withToken($this->token($pengguna))
            ->patchJson(route('api.v1.pemeriksaan-perangkat-ajar.update', $data['dokumen']), [
                'status' => 'sudah_diperiksa',
            ])
            ->assertForbidden();
    }

    private function dataDasar(): array
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $guru = Pegawai::create([
            'nama_lengkap' => 'Guru Pemeriksaan Mobile',
            'nip' => '198001012010011991',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $akunGuru = Pengguna::create([
            'pegawai_id' => $guru->id,
            'nama' => $guru->nama_lengkap,
            'username' => 'guru-pemeriksaan-mobile',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $tahun = TahunPelajaran::create(['nama' => '2040/2041', 'aktif' => true]);
        $mapel = MataPelajaran::create([
            'kode' => 'INF-REVIEW-MOBILE',
            'nama' => 'Informatika Review',
            'aktif' => true,
        ]);
        foreach ([7, 8] as $tingkat) {
            $kelas = Kelas::create([
                'tahun_pelajaran_id' => $tahun->id,
                'nama' => $tingkat === 7 ? 'VII.R Mobile' : 'VIII.R Mobile',
                'tingkat' => $tingkat,
                'aktif' => true,
            ]);
            GuruMataPelajaran::create([
                'tahun_pelajaran_id' => $tahun->id,
                'kelas_id' => $kelas->id,
                'mata_pelajaran_id' => $mapel->id,
                'pegawai_id' => $guru->id,
                'aktif' => true,
            ]);
        }
        $jenis = JenisPerangkatAjar::create([
            'kode' => 'MODUL-REVIEW-MOBILE',
            'nama' => 'Modul Ajar',
            'wajib' => true,
            'urutan' => 1,
            'aktif' => true,
        ]);
        $dokumen = PerangkatAjar::create([
            'pegawai_id' => $guru->id,
            'tahun_pelajaran_id' => $tahun->id,
            'semester' => 1,
            'mata_pelajaran_id' => $mapel->id,
            'tingkat' => 7,
            'jenis_perangkat_ajar_id' => $jenis->id,
            'judul' => 'Modul Informatika Review VII',
            'catatan_guru' => 'Mohon diperiksa.',
            'lokasi_file' => 'perangkat-ajar/review/modul-vii.pdf',
            'nama_file_asli' => 'modul-vii.pdf',
            'tipe_file' => 'application/pdf',
            'ukuran_file' => 2048,
            'status' => 'menunggu_pemeriksaan',
            'diunggah_pada' => now(),
        ]);
        $dokumen->riwayatFile()->create([
            'diunggah_oleh_pengguna_id' => $akunGuru->id,
            'lokasi_file' => $dokumen->lokasi_file,
            'nama_file_asli' => $dokumen->nama_file_asli,
            'tipe_file' => 'application/pdf',
            'ukuran_file' => 2048,
            'catatan' => 'Mohon diperiksa.',
            'diunggah_pada' => now(),
        ]);

        return [
            'administrator' => $administrator,
            'guru' => $guru,
            'akun_guru' => $akunGuru,
            'tahun' => $tahun,
            'mapel' => $mapel,
            'jenis' => $jenis,
            'dokumen' => $dokumen,
        ];
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
