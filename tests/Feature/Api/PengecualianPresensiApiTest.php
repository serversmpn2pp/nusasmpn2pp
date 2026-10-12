<?php

namespace Tests\Feature\Api;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\Izin;
use App\Models\Kelas;
use App\Models\OrangTuaWali;
use App\Models\Pegawai;
use App\Models\PengaturanAbsensi;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PengecualianPresensiApiTest extends TestCase
{
    use RefreshDatabase;

    private Pengguna $admin;

    private TahunPelajaran $tahun;

    private Kelas $kelas;

    private Kelas $kelasB;

    private array $anggota = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 13)->setTime(10, 0));
        $this->withoutMiddleware(ThrottleRequests::class);
        config(['services.firebase.push_enabled' => false]);
        $this->admin = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->tahun = TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
        $this->kelas = Kelas::create(['nama' => 'VIII.A', 'tingkat' => 8, 'tahun_pelajaran_id' => $this->tahun->id, 'aktif' => true]);
        $this->kelasB = Kelas::create(['nama' => 'VIII.B', 'tingkat' => 8, 'tahun_pelajaran_id' => $this->tahun->id, 'aktif' => true]);
        foreach (['Alya', 'Bima', 'Citra'] as $i => $nama) {
            $siswa = Siswa::create(['nisn' => '991005000'.$i, 'nama_lengkap' => $nama, 'aktif' => true]);
            $this->anggota[] = AnggotaKelas::create(['siswa_id' => $siswa->id, 'kelas_id' => $i === 2 ? $this->kelasB->id : $this->kelas->id,
                'tahun_pelajaran_id' => $this->tahun->id, 'status_keanggotaan' => 'aktif', 'nomor_absen' => $i + 1]);
        }
        foreach (['senin', 'selasa', 'rabu', 'kamis', 'jumat'] as $i => $hari) {
            PengaturanAbsensi::create(['hari' => $hari, 'urutan_hari' => $i + 1, 'jam_scan_masuk_mulai' => '06:00', 'jam_masuk' => '07:00',
                'jam_scan_masuk_selesai' => '08:00', 'jam_scan_pulang_mulai' => '13:00', 'jam_pulang' => '14:00', 'jam_scan_pulang_selesai' => '15:00', 'aktif' => true]);
        }
        foreach ([[0, '2026-09-14', 'hadir'], [1, '2026-09-15', 'alfa'], [2, '2026-09-16', 'sakit']] as [$i, $tanggal, $status]) {
            $a = $this->anggota[$i];
            AbsensiSiswa::create(['siswa_id' => $a->siswa_id, 'anggota_kelas_id' => $a->id, 'kelas_id' => $a->kelas_id,
                'tahun_pelajaran_id' => $a->tahun_pelajaran_id, 'tanggal' => $tanggal, 'status_kehadiran' => $status,
                'sumber' => $status === 'hadir' ? 'scan' : 'manual']);
        }
    }

    public function test_enam_endpoint_dilindungi_token_izin_dan_pembatasan_permintaan(): void
    {
        $routes = collect(Route::getRoutes())->filter(fn ($r) => str_starts_with($r->getName() ?? '', 'api.v1.pengecualian-presensi.'));
        $this->assertCount(6, $routes);
        foreach ($routes as $r) {
            foreach (['auth:sanctum', 'abilities:mobile', 'akun_api_aktif', 'kata_sandi_api_bukan_default',
                'izin:absensi.pengaturan_kelola', 'throttle:60,1,pengecualian-presensi-api:'] as $guard) {
                $this->assertContains($guard, $r->gatherMiddleware());
            }
            if (in_array('POST', $r->methods(), true)) {
                $this->assertCount(2, array_filter($r->gatherMiddleware(), fn ($m) => str_starts_with($m, 'throttle:')));
            }
        }
        $this->getJson($this->url())->assertUnauthorized();
        $this->token($this->admin, ['web']);
        $this->getJson($this->url())->assertForbidden();
        $this->admin->update(['wajib_ganti_kata_sandi' => true]);
        $this->token($this->admin);
        $this->getJson($this->url())->assertStatus(428);
        $this->admin->update(['aktif' => false]);
        $this->token($this->admin);
        $this->getJson($this->url())->assertUnauthorized();
    }

    public function test_menu_dan_referensi_hanya_untuk_pengelola_tanpa_cakupan_terbatas(): void
    {
        $this->token($this->admin);
        $r = $this->getJson(route('api.v1.menu'))->assertOk();
        $menus = collect($r->json('data.kelompok'))->firstWhere('kode', 'kehadiran')['items'];
        $this->assertSame('tersedia', collect($menus)->firstWhere('kode', 'pengecualian-presensi')['status']);
        $this->getJson($this->url('referensi'))->assertOk()->assertJsonPath('data.batas_pratinjau_menit', 15)
            ->assertJsonPath('data.maksimal_hari', 367)->assertJsonCount(2, 'data.kelas')->assertJsonPath('data.jenis.pjj', 'PJJ')
            ->assertDontSee('kata_sandi')->assertDontSee('username');
        $this->getJson(route('api.v1.pengaturan-presensi-siswa.index'))->assertOk()
            ->assertJsonPath('data.hak_akses.dapat_kelola_pengecualian', true);
        $guru = $this->akun('guru_mapel');
        $wali = $this->akun('wali_kelas');
        $role = Peran::where('kode', 'wali_kelas')->firstOrFail();
        $role->izin()->syncWithoutDetaching([Izin::where('kode', 'absensi.pengaturan_kelola')->value('id')]);
        $siswa = $this->akun('administrator');
        $siswa->update(['pegawai_id' => null, 'siswa_id' => $this->anggota[0]->siswa_id]);
        $ortu = $this->akun('administrator');
        $ortu->update(['pegawai_id' => null]);
        OrangTuaWali::create(['pengguna_id' => $ortu->id, 'nama_lengkap' => 'Orang tua', 'aktif' => true]);
        foreach ([$guru, $wali, $siswa, $ortu] as $u) {
            $this->token($u);
            $r = $this->getJson(route('api.v1.menu'))->assertOk();
            $this->assertNotContains('pengecualian-presensi', collect($r->json('data.kelompok'))->flatMap(fn ($g) => $g['items'])->pluck('kode'));
            foreach (['', 'referensi'] as $path) {
                $this->getJson($this->url($path))->assertForbidden();
            }
            $this->postJson($this->url('pratinjau'), $this->isian())->assertForbidden();
            $this->postJson($this->url(), ['token' => 'x', 'konfirmasi' => true])->assertForbidden();
        }
    }

    public function test_pratinjau_ringkas_tidak_membocorkan_model_atau_mengubah_data_dan_terapkan_idempoten(): void
    {
        $this->token($this->admin);
        $sebelum = AbsensiSiswa::get()->toArray();
        $r = $this->postJson($this->url('pratinjau'), $this->isian())->assertOk()
            ->assertJsonPath('data.dampak.alfa_otomatis_dibatalkan', 12)
            ->assertJsonPath('data.dampak.alfa_manual', 1)->assertJsonCount(3, 'data.siswa')
            ->assertJsonPath('data.cakupan_label', 'Seluruh sekolah')->assertJsonPath('data.siswa.0.nama', 'Alya')
            ->assertJsonMissingPath('data.sidik')->assertJsonMissingPath('data.siswa.0.anggota')
            ->assertDontSee('nisn')->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->assertDatabaseCount('pengecualian_presensi_siswa', 0);
        $token = $r->json('data.token');
        $this->postJson($this->url(), ['token' => $token])->assertUnprocessable()->assertJsonValidationErrors('konfirmasi');
        $stored = $this->postJson($this->url(), ['token' => $token, 'konfirmasi' => true])->assertCreated()
            ->assertJsonMissingPath('data.kunci_pratinjau')->assertJsonMissingPath('data.token')->assertJsonPath('data.pembuat.id', $this->admin->id);
        $this->postJson($this->url(), ['token' => $token, 'konfirmasi' => true])->assertOk()->assertJsonPath('data.id', $stored->json('data.id'));
        $this->assertDatabaseCount('pengecualian_presensi_siswa', 1);
        $this->assertSame($sebelum, AbsensiSiswa::get()->toArray());
        $this->getJson($this->url((string) $stored->json('data.id')))->assertOk()->assertJsonPath('data.hak_akses.dapat_batalkan', true);
    }

    public function test_token_bukan_milik_akun_kedaluwarsa_rusak_dan_perubahan_sumber_ditolak(): void
    {
        $this->token($this->admin);
        $token = $this->postJson($this->url('pratinjau'), $this->isian())->json('data.token');
        $lain = $this->akun('administrator');
        $this->token($lain);
        $this->postJson($this->url(), ['token' => $token, 'konfirmasi' => true])->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->token($this->admin);
        $this->postJson($this->url(), ['token' => 'rusak', 'konfirmasi' => true])->assertUnprocessable();
        AbsensiSiswa::first()->update(['status_kehadiran' => 'izin']);
        $this->postJson($this->url(), ['token' => $token, 'konfirmasi' => true])->assertUnprocessable()->assertJsonValidationErrors('token');
        $baru = $this->postJson($this->url('pratinjau'), $this->isian())->json('data.token');
        $this->travel(16)->minutes();
        $this->postJson($this->url(), ['token' => $baru, 'konfirmasi' => true])->assertUnprocessable();
        $this->assertDatabaseCount('pengecualian_presensi_siswa', 0);
    }

    public function test_validasi_tahun_kelas_rentang_jenis_alasan_dan_tumpang_tindih(): void
    {
        $this->token($this->admin);
        $lain = TahunPelajaran::create(['nama' => 'Tahun lain', 'tanggal_mulai' => '2027-07-01', 'tanggal_selesai' => '2028-06-30', 'aktif' => false]);
        $kelasLain = Kelas::create(['nama' => 'IX.A', 'tingkat' => 9, 'tahun_pelajaran_id' => $lain->id, 'aktif' => true]);
        foreach ([['kelas_id' => $kelasLain->id], ['jenis' => 'ujian'], ['alasan' => 'abc'], ['tanggal_mulai' => '14-09-2026'],
            ['tanggal_selesai' => '2026-09-10'], ['tanggal_mulai' => '2026-06-30'], ['tanggal_selesai' => '2027-07-01']] as $ubah) {
            $this->postJson($this->url('pratinjau'), $this->isian($ubah))->assertUnprocessable();
        }
        $token = $this->postJson($this->url('pratinjau'), $this->isian(['kelas_id' => $this->kelas->id]))->json('data.token');
        $this->postJson($this->url(), ['token' => $token, 'konfirmasi' => true])->assertCreated();
        $this->postJson($this->url('pratinjau'), $this->isian())->assertUnprocessable();
        $this->postJson($this->url('pratinjau'), $this->isian(['kelas_id' => $this->kelasB->id]))->assertOk()->assertJsonCount(1, 'data.siswa');
    }

    public function test_batalkan_menyimpan_riwayat_dan_mengembalikan_alfa_tanpa_menghapus_catatan(): void
    {
        $this->token($this->admin);
        $token = $this->postJson($this->url('pratinjau'), $this->isian())->json('data.token');
        $id = $this->postJson($this->url(), ['token' => $token, 'konfirmasi' => true])->json('data.id');
        $url = $this->url($id.'/batalkan');
        $this->postJson($url, ['konfirmasi' => true])->assertUnprocessable();
        $alasan = 'Periode keliru, harus disesuaikan keputusan sekolah.';
        $this->postJson($url, ['alasan_pembatalan' => $alasan])->assertUnprocessable();
        $this->postJson($url, ['alasan_pembatalan' => $alasan, 'konfirmasi' => true])->assertOk()->assertJsonPath('data.aktif', false)
            ->assertJsonPath('data.alasan_pembatalan', $alasan)->assertJsonPath('data.pembatal.id', $this->admin->id)
            ->assertJsonPath('data.hak_akses.dapat_batalkan', false);
        $this->postJson($url, ['alasan_pembatalan' => 'Tidak boleh mengganti riwayat sebelumnya.', 'konfirmasi' => true])
            ->assertOk()->assertJsonPath('data.alasan_pembatalan', $alasan);
        $this->postJson($this->url(), ['token' => $token, 'konfirmasi' => true])->assertUnprocessable();
        $this->assertDatabaseCount('pengecualian_presensi_siswa', 1);
        $this->assertDatabaseCount('absensi_siswa', 3);
        $this->getJson(route('api.v1.rekap-presensi-siswa.index', ['tanggal' => '2026-09-15', 'tahun_pelajaran_id' => $this->tahun->id]))
            ->assertOk()->assertJsonPath('data.ringkasan.alfa', 3);
        $this->getJson($this->url().'?status=dibatalkan&kata_kunci=asap&per_halaman=1')->assertOk()
            ->assertJsonPath('data.ringkasan.dibatalkan', 1)->assertJsonPath('data.paginasi.total', 1);
    }

    public function test_daftar_dipaginasi_id_invalid_dan_filter_tidak_memperluas_cakupan(): void
    {
        $this->token($this->admin);
        foreach ([$this->kelas, $this->kelasB] as $k) {
            $token = $this->postJson($this->url('pratinjau'), $this->isian(['kelas_id' => $k->id]))->json('data.token');
            $this->postJson($this->url(), ['token' => $token, 'konfirmasi' => true])->assertCreated();
        }
        $this->getJson($this->url().'?per_halaman=1')->assertOk()->assertJsonPath('data.paginasi.total', 2)
            ->assertJsonPath('data.paginasi.ada_halaman_berikutnya', true);
        $this->getJson($this->url().'?per_halaman=1&halaman=2')->assertOk()->assertJsonPath('data.paginasi.halaman', 2);
        $this->getJson($this->url().'?kelas_id='.$this->kelas->id)->assertOk()->assertJsonPath('data.paginasi.total', 1);
        foreach (['halaman=0', 'per_halaman=51', 'status=acak'] as $q) {
            $this->getJson($this->url().'?'.$q)->assertUnprocessable();
        }
        $this->getJson($this->url('abc'))->assertNotFound();
        $this->getJson($this->url('999999'))->assertNotFound();
    }

    public function test_pratinjau_dibatasi_tanpa_perubahan_data(): void
    {
        $this->withMiddleware(ThrottleRequests::class);
        $this->token($this->admin);
        for ($i = 0; $i < 10; $i++) {
            $this->postJson($this->url('pratinjau'), [])->assertUnprocessable();
        }
        $this->postJson($this->url('pratinjau'), [])->assertTooManyRequests()->assertHeader('Retry-After');
        $this->assertDatabaseCount('pengecualian_presensi_siswa', 0);
    }

    private function akun(string $role): Pengguna
    {
        $p = Pegawai::create(['nama_lengkap' => 'Petugas tes', 'aktif' => true]);
        $u = Pengguna::create(['nama' => 'Petugas tes', 'username' => 'pengecualian.'.uniqid(), 'pegawai_id' => $p->id,
            'kata_sandi' => 'PengecualianTes12345', 'aktif' => true, 'peran' => 'pegawai', 'wajib_ganti_kata_sandi' => false]);
        $u->daftarPeran()->attach(Peran::where('kode', $role)->firstOrFail());

        return $u;
    }

    private function token(Pengguna $u, array $abilities = ['mobile']): void
    {
        Auth::forgetGuards();
        $this->withToken($u->createToken('Android Pengecualian Tes', $abilities)->plainTextToken);
    }

    private function isian(array $ubah = []): array
    {
        return array_replace(['tahun_pelajaran_id' => $this->tahun->id, 'kelas_id' => null, 'tanggal_mulai' => '2026-09-14',
            'tanggal_selesai' => '2026-09-18', 'jenis' => 'pjj', 'alasan' => 'PJJ akibat bencana asap sesuai keputusan sekolah.'], $ubah);
    }

    private function url(string $path = ''): string
    {
        return url('/api/v1/pengecualian-presensi'.($path !== '' ? '/'.$path : ''));
    }
}
