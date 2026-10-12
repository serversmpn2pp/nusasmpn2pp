<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\JenisUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Models\LampiranPerilakuSts;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\PenugasanGuruBkTingkat;
use App\Models\Peran;
use App\Models\RaporStsKelas;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LampiranPerilakuStsApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-12 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_token_mobile_wajib(): void
    {
        $this->getJson(route('api.v1.lampiran-perilaku-sts.index'))->assertUnauthorized();
        $d = $this->fondasi();
        $this->withToken($d['bk']->createToken('Non mobile', ['web'])->plainTextToken)
            ->getJson(route('api.v1.lampiran-perilaku-sts.index'))->assertForbidden();
    }

    public function test_ringkasan_diizinkan_tanpa_identitas_lengkap_kronologi_dan_catatan_rahasia(): void
    {
        $d = $this->fondasi();
        $this->login($d['bk']);
        $response = $this->getJson($this->url($d))->assertOk()
            ->assertJsonPath('data.konteks.dapat_periksa', true)
            ->assertJsonPath('data.konteks.siswa.0.id', $d['anggota'][0]->id)
            ->assertJsonPath('data.konteks.siswa.0.siap', false)
            ->assertJsonCount(1, 'data.konteks.siswa.0.baris')
            ->assertJsonPath('data.konteks.guru_bk.0.id', $d['bk']->pegawai_id);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        foreach (['RAHASIA-KONSELING', 'KRONOLOGI-INTERNAL', '1234567890', 'kata_sandi', 'nisn', 'nip'] as $rahasia) {
            $response->assertDontSee($rahasia);
        }
    }

    public function test_bk_tidak_dapat_melihat_atau_menyimpan_kelas_di_luar_penugasan(): void
    {
        $d = $this->fondasi();
        $kelas = Kelas::create(['tahun_pelajaran_id' => $d['tahun']->id, 'nama' => 'VIII.A', 'tingkat' => 8]);
        $this->login($d['bk']);
        $this->getJson($this->url($d, ['kelas_id' => $kelas->id]))->assertNotFound();
        $this->putJson(route('api.v1.lampiran-perilaku-sts.pemeriksaan', [$d['kegiatan'], $kelas]), $this->payload($d))->assertForbidden();
        $this->assertDatabaseCount('lampiran_perilaku_sts', 0);
    }

    public function test_wali_tanpa_izin_bk_tidak_dapat_membuka_fitur(): void
    {
        $d = $this->fondasi();
        $this->login($d['bk']);
        $data = $this->payload($d);
        $this->login($d['wali']);
        $this->getJson($this->url($d))->assertForbidden();
        $this->putJson($this->urlSimpan($d), $data)->assertForbidden();
    }

    public function test_siswa_dan_orang_tua_tidak_menerima_menu_atau_akses_bahkan_jika_peran_bk_tersisip(): void
    {
        $d = $this->fondasi();
        foreach (['siswa', 'orang_tua'] as $peran) {
            $user = Pengguna::create(['nama' => 'Uji '.$peran, 'username' => 'uji-'.$peran, 'peran' => $peran,
                'siswa_id' => $peran === 'siswa' ? $d['anggota'][0]->siswa_id : null, 'aktif' => true,
                'kata_sandi' => 'safe-test-password', 'wajib_ganti_kata_sandi' => false]);
            $user->daftarPeran()->sync([Peran::where('kode', 'bk')->value('id')]);
            $this->login($user);
            $this->getJson($this->url($d))->assertForbidden();
            $this->getJson(route('api.v1.menu'))->assertOk()->assertDontSee('lampiran-perilaku-sts');
        }
    }

    public function test_menu_bk_dan_admin_memuat_rute_native(): void
    {
        $d = $this->fondasi();
        foreach ([$d['bk'], Pengguna::where('username', 'administrator')->firstOrFail()] as $user) {
            $this->login($user);
            $this->getJson(route('api.v1.menu'))->assertOk()->assertJsonFragment([
                'kode' => 'lampiran-perilaku-sts', 'label' => 'Lampiran Perilaku STS',
                'rute' => '/lampiran-perilaku-sts', 'status' => 'tersedia',
            ]);
            $this->getJson($this->url($d))->assertOk();
        }
    }

    public function test_pemeriksaan_kolektif_menyimpan_ringkasan_dan_mengabaikan_manipulasi_poin(): void
    {
        $d = $this->fondasi();
        $this->login($d['bk']);
        $data = $this->payload($d);
        $key = 'laporan-'.$d['laporan']->id;
        $data['siswa'][$d['anggota'][0]->id]['baris'][$key]['poin'] = -999;
        $this->putJson($this->urlSimpan($d), $data)->assertOk();
        $this->assertDatabaseCount('lampiran_perilaku_sts', 2);
        $this->assertSame(15, LampiranPerilakuSts::where('anggota_kelas_id', $d['anggota'][0]->id)->firstOrFail()->baris[0]['poin']);
        $this->getJson($this->url($d))->assertOk()->assertJsonPath('data.konteks.siswa.0.siap', true)
            ->assertJsonPath('data.konteks.siswa.0.versi', 1);
        $this->putJson($this->urlSimpan($d), $data)->assertUnprocessable();
    }

    public function test_konfirmasi_signer_dan_kelengkapan_baris_wajib(): void
    {
        $d = $this->fondasi();
        $this->login($d['bk']);
        $data = $this->payload($d);
        $this->putJson($this->urlSimpan($d), [...$data, 'diperiksa' => false])->assertUnprocessable();
        $this->putJson($this->urlSimpan($d), [...$data, 'guru_bk_id' => $d['wali']->pegawai_id])->assertUnprocessable();
        $data['siswa'][$d['anggota'][0]->id]['baris'] = [];
        $this->putJson($this->urlSimpan($d), $data)->assertUnprocessable();
        $data = $this->payload($d);
        $data['siswa'][$d['anggota'][0]->id]['baris']['tambahan-klien'] = ['kejadian' => 'Tambahan', 'tindakan' => 'Tidak sah'];
        $this->putJson($this->urlSimpan($d), $data)->assertUnprocessable();
        $this->assertDatabaseCount('lampiran_perilaku_sts', 0);
    }

    public function test_konflik_siswa_kedua_membatalkan_seluruh_penyimpanan(): void
    {
        $d = $this->fondasi();
        $this->login($d['bk']);
        $data = $this->payload($d);
        $data['siswa'][$d['anggota'][1]->id]['sidik_sumber'] = str_repeat('0', 64);
        $this->putJson($this->urlSimpan($d), $data)->assertUnprocessable();
        $this->assertDatabaseCount('lampiran_perilaku_sts', 0);
    }

    public function test_sumber_berubah_dan_periode_belum_berakhir_menolak_pemeriksaan(): void
    {
        $d = $this->fondasi();
        $this->login($d['bk']);
        $data = $this->payload($d);
        $d['laporan']->update(['total_poin' => 20]);
        $this->putJson($this->urlSimpan($d), $data)->assertUnprocessable();
        $d['rapor']->update(['tanggal_akhir_presensi' => '2026-10-20']);
        $this->getJson($this->url($d))->assertOk()->assertJsonPath('data.konteks.dapat_periksa', false);
        $this->putJson($this->urlSimpan($d), $this->payload($d))->assertUnprocessable();
        $this->assertDatabaseCount('lampiran_perilaku_sts', 0);
    }

    public function test_ganti_isi_memakai_versi_dan_membatalkan_kesiapan_pemeriksaan(): void
    {
        $d = $this->fondasi();
        $this->login($d['bk']);
        $this->putJson($this->urlSimpan($d), $this->payload($d))->assertOk();
        $report = $this->getJson($this->url($d))->json('data.konteks');
        $data = ['isi_lampiran_perilaku' => 'poin_final', 'versi_rapor' => $report['versi_rapor'], 'versi_isi_perilaku' => $report['versi_isi_perilaku']];
        $url = route('api.v1.lampiran-perilaku-sts.isi', [$d['kegiatan'], $d['kelas']]);
        $this->putJson($url, $data)->assertOk();
        $this->getJson($this->url($d))->assertOk()->assertJsonPath('data.konteks.isi', 'poin_final')->assertJsonPath('data.konteks.siswa.0.siap', false);
        $this->putJson($url, [...$data, 'isi_lampiran_perilaku' => 'semua_terverifikasi'])->assertUnprocessable();
    }

    private function login(Pengguna $user): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($user->createToken('Uji STS', ['mobile'])->plainTextToken);
    }

    private function url(array $d, array $filter = []): string
    {
        return route('api.v1.lampiran-perilaku-sts.index', [...['kegiatan_id' => $d['kegiatan']->id, 'kelas_id' => $d['kelas']->id], ...$filter]);
    }

    private function urlSimpan(array $d): string
    {
        return route('api.v1.lampiran-perilaku-sts.pemeriksaan', [$d['kegiatan'], $d['kelas']]);
    }

    private function payload(array $d): array
    {
        $students = $this->getJson($this->url($d))->assertOk()->json('data.konteks.siswa');

        return ['anggota_ids' => array_column($students, 'id'), 'guru_bk_id' => $d['bk']->pegawai_id,
            'wakil_kesiswaan_id' => $d['wakil']->pegawai_id, 'diperiksa' => true,
            'siswa' => collect($students)->mapWithKeys(fn ($s) => [$s['id'] => [
                'versi' => $s['versi'], 'sidik_sumber' => $s['sidik_sumber'], 'catatan' => 'Catatan umum aman.',
                'baris' => collect($s['baris'])->mapWithKeys(fn ($r) => [$r['kunci'] => ['kejadian' => $r['kejadian'], 'tindakan' => $r['tindakan']]])->all(),
            ]])->all()];
    }

    private function fondasi(): array
    {
        $bk = $this->akun('bk', 'Guru BK Contoh');
        $wakil = $this->akun('wakil_pimpinan_kesiswaan', 'Wakil Kesiswaan Contoh');
        $wali = $this->akun('wali_kelas', 'Wali Kelas Contoh');
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
        $kelas = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'VII.A', 'tingkat' => 7, 'wali_kelas_id' => $wali->pegawai_id, 'aktif' => true]);
        $kegiatan = KegiatanUjianCbt::create(['tahun_pelajaran_id' => $tahun->id, 'jenis_ujian_cbt_id' => JenisUjianCbt::where('kode', 'STS')->value('id'), 'kode' => 'STS-MOBILE', 'nama' => 'Sumatif Tengah Semester I', 'semester' => 'ganjil', 'tanggal_mulai' => '2026-09-15', 'tanggal_selesai' => '2026-09-20', 'status' => 'selesai']);
        $rapor = RaporStsKelas::create(['kegiatan_ujian_cbt_id' => $kegiatan->id, 'kelas_id' => $kelas->id, 'tanggal_awal_presensi' => '2026-08-01', 'tanggal_akhir_presensi' => '2026-10-02', 'tanggal_rapor' => '2026-10-02', 'versi' => 1]);
        PenugasanGuruBkTingkat::create(['tahun_pelajaran_id' => $tahun->id, 'pegawai_id' => $bk->pegawai_id, 'tingkat' => 7, 'tanggal_mulai' => '2026-07-01', 'aktif' => true]);
        $anggota = collect();
        foreach (['Alya Contoh', 'Bima Contoh'] as $i => $nama) {
            $s = Siswa::create(['nama_lengkap' => $nama, 'nisn' => '123456789'.$i, 'jenis_kelamin' => 'P', 'aktif' => true]);
            $anggota->push(AnggotaKelas::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'siswa_id' => $s->id, 'nomor_absen' => $i + 1, 'status_keanggotaan' => 'aktif']));
        }
        $laporan = LaporanPembinaanSiswa::create(['nomor_laporan' => 'API-PERILAKU-1', 'siswa_id' => $anggota[0]->siswa_id,
            'anggota_kelas_id' => $anggota[0]->id, 'kelas_id' => $kelas->id, 'tahun_pelajaran_id' => $tahun->id,
            'tanggal_kejadian' => '2026-09-01', 'jenis_laporan' => 'pelanggaran', 'status' => 'selesai', 'status_verifikasi' => 'disahkan',
            'total_poin' => 15, 'kronologi' => 'KRONOLOGI-INTERNAL', 'catatan_rahasia' => 'RAHASIA-KONSELING', 'tindakan_awal' => 'Teguran lisan.']);

        return compact('bk', 'wakil', 'wali', 'tahun', 'kelas', 'kegiatan', 'rapor', 'anggota', 'laporan');
    }

    private function akun(string $peran, string $nama): Pengguna
    {
        $pegawai = Pegawai::create(['nama_lengkap' => $nama, 'jenis_kelamin' => 'P', 'jenis_pegawai' => 'Guru', 'aktif' => true]);
        $user = Pengguna::create(['nama' => $nama, 'username' => 'api-perilaku-'.$pegawai->id, 'kata_sandi' => 'safe-test-password', 'peran' => 'pegawai', 'pegawai_id' => $pegawai->id, 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $user->daftarPeran()->sync([Peran::where('kode', $peran)->value('id')]);

        return $user;
    }
}
