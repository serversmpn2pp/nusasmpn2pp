<?php

namespace Tests\Feature\Api;

use App\Http\Resources\Api\V1\PenggunaResource;
use App\Models\FaqHumas;
use App\Models\Izin;
use App\Models\KinerjaSemesterHumas;
use App\Models\OrangTuaWali;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class HumasMobileAlignmentTest extends TestCase
{
    use RefreshDatabase;

    private Pengguna $humas;

    private TahunPelajaran $tahun;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        Queue::fake();
        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(10, 0));
        $this->tahun = TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
        $this->humas = $this->akun('wakil_pimpinan_humas');
    }

    public function test_kontrak_menu_dan_data_diuji_pada_seluruh_role_dan_dapat_dipakai_fixture_flutter(): void
    {
        foreach (['semua', 'pegawai', 'orang_tua', 'siswa'] as $s) {
            $this->faq($s);
        }
        $draf = $this->faq('semua', ['pertanyaan' => 'FAQ RAHASIA DRAF', 'status' => 'draf']);
        $this->token($this->humas);
        $laporan = $this->postJson(route('api.v1.humas.kinerja.store'), $this->inputLaporan())->assertCreated()->assertJsonPath('data.status', 'draf')->json('data');
        $fixtures = [];
        foreach (['administrator', 'wakil_pimpinan_humas', 'pimpinan', 'guru_mapel', 'tata_usaha', 'bk', 'kontributor_publikasi', 'pegawai', 'orang_tua_a', 'orang_tua_b', 'siswa'] as $role) {
            $u = $this->akun($role);
            $editor = in_array($role, ['administrator', 'wakil_pimpinan_humas']);
            $baca = $editor || $role === 'pimpinan';
            $this->token($u);
            $menu = $this->getJson(route('api.v1.menu'))->assertOk()->assertJsonFragment(['kode' => 'faq-humas', 'rute' => '/humas/faq'])->json('data');
            $menus = collect($menu['kelompok'])->firstWhere('kode', 'humas');
            $codes = array_column($menus['items'], 'kode');
            $this->assertSame($baca, in_array('kinerja-humas', $codes, true), $role);
            $r = $this->getJson(route('api.v1.humas.faq.index'))->assertOk()->assertJsonPath('data.dapat_kelola', $editor);
            $this->assertSame($editor ? 5 : 2, $r->json('data.paginasi.total'), $role);
            $this->getJson(route('api.v1.humas.faq.referensi'))->assertOk()->assertJsonPath('data.dapat_kelola', $editor);
            $this->getJson(route('api.v1.humas.faq.show', $draf))->assertStatus($editor ? 200 : 404);
            $this->getJson(route('api.v1.humas.faq.riwayat', $draf))->assertStatus($editor ? 200 : 403);
            $details = $this->getJson(route('api.v1.humas.kinerja.show', $laporan['id']))->assertStatus($baca ? 200 : 403);
            $this->getJson(route('api.v1.humas.kinerja.cetak', $laporan['id']))->assertStatus($baca ? 200 : 403);
            if (! $editor) {
                $this->postJson(route('api.v1.humas.faq.store'), [])->assertForbidden();
            }
            if (! $baca) {
                $this->getJson(route('api.v1.humas.kinerja.index'))->assertForbidden();
            }
            $menu['kelompok'] = [$menus];
            $menu['jumlah_menu'] = count($menus['items']);
            $faqRef = $this->getJson(route('api.v1.humas.faq.referensi'))->assertOk()->json('data');
            unset($faqRef['token_pembuatan']);
            $reportRef = $baca ? $this->getJson(route('api.v1.humas.kinerja.referensi'))->assertOk()->json('data') : [];
            unset($reportRef['token_pembuatan']);
            $fixtures[$role] = ['user' => (new PenggunaResource($u->fresh()))->resolve(request()), 'menu' => $menu, 'faq' => $r->json('data'),
                'faq_detail' => $this->getJson(route('api.v1.humas.faq.show', FaqHumas::first()))->assertOk()->json('data'),
                'faq_referensi' => $faqRef, 'kinerja_referensi' => $reportRef,
                'dashboard' => $u->memilikiIzin('dashboard_humas.lihat') ? $this->getJson(route('api.v1.humas.dashboard.index'))->assertOk()->json('data') : [],
                'kinerja' => $baca ? $details->json('data') : null];
        }
        if (getenv('NUSA_CAPTURE_HUMAS_MOBILE') === '1') {
            File::ensureDirectoryExists(base_path('mobile/test/fixtures'));
            File::put(base_path('mobile/test/fixtures/humas_api_contract.json'), json_encode($fixtures, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        }
    }

    public function test_paginasi_mobile_tidak_mengulang_halaman_pertama_dan_web_tetap_kompatibel(): void
    {
        for ($i = 0; $i < 25; $i++) {
            $this->faq('semua', ['pertanyaan' => sprintf('FAQ %02d', $i)]);
        }
        $this->token($this->humas);
        $first = $this->getJson(route('api.v1.humas.faq.index', ['halaman' => 1, 'per_halaman' => 10]))->assertOk()->assertJsonCount(10, 'data.items')->assertJsonPath('data.paginasi.halaman', 1)->json('data.items');
        $second = $this->getJson(route('api.v1.humas.faq.index', ['halaman' => 2, 'per_halaman' => 10]))->assertOk()->assertJsonCount(10, 'data.items')->assertJsonPath('data.paginasi.halaman', 2)->json('data.items');
        $this->assertSame([], array_intersect(array_column($first, 'id'), array_column($second, 'id')));
        $this->getJson(route('api.v1.humas.faq.index', ['page' => 2, 'per_halaman' => 10]))->assertJsonPath('data.items.0.id', $second[0]['id']);
        $this->getJson(route('api.v1.humas.faq.index', ['halaman' => 3, 'per_halaman' => 10]))->assertJsonCount(5, 'data.items')->assertJsonPath('data.paginasi.ada_halaman_berikutnya', false);
        $this->getJson(route('api.v1.humas.faq.index', ['halaman' => 0]))->assertUnprocessable();
        $this->getJson(route('api.v1.humas.faq.index', ['per_halaman' => 10000]))->assertUnprocessable();
        $this->actingAs($this->humas)->get(route('faq-humas.index', ['page' => 2]))->assertOk()->assertViewHas('daftar', fn ($p) => $p->currentPage() === 2 && $p->count() === 10);
    }

    public function test_seluruh_role_aktif_konsisten_antara_izin_menu_dan_endpoint(): void
    {
        $roles = Peran::where('aktif', true)->orderBy('kode')->pluck('kode');
        $hasil = [];
        foreach ($roles as $role) {
            $u = $this->akun($role);
            $family = $u->akunSiswa() || $u->akunOrangTua();
            $baca = ! $family && $u->memilikiIzin(['kinerja_humas.lihat', 'kinerja_humas.kelola']);
            $kelola = ! $family && $u->memilikiIzin('faq_humas.kelola');
            $this->token($u);
            $menu = $this->getJson(route('api.v1.menu'))->assertOk()->json('data.kelompok');
            $humas = collect($menu)->firstWhere('kode', 'humas');
            $this->assertNotNull($humas, $role);
            $codes = array_column($humas['items'], 'kode');
            $this->assertContains('faq-humas', $codes, $role);
            $this->assertSame($baca, in_array('kinerja-humas', $codes, true), $role);
            $this->getJson(route('api.v1.humas.kinerja.index'))->assertStatus($baca ? 200 : 403);
            $this->getJson(route('api.v1.humas.faq.referensi'))->assertOk()->assertJsonPath('data.dapat_kelola', $kelola);
            $hasil[] = ['role' => $role, 'jenis_akun' => $u->labelJenisAkun(), 'baca_kinerja' => $baca, 'kelola_faq' => $kelola];
        }
        $this->assertGreaterThan(10, count($hasil));
        if (getenv('NUSA_CAPTURE_HUMAS_MOBILE') === '1') {
            File::ensureDirectoryExists(storage_path('logs/humas-mobile-alignment'));
            File::put(storage_path('logs/humas-mobile-alignment/role-matrix.json'), json_encode($hasil, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        }
    }

    public function test_paginasi_laporan_dan_riwayat_mengikuti_parameter_mobile(): void
    {
        $this->token($this->humas);
        $l = $this->postJson(route('api.v1.humas.kinerja.store'), $this->inputLaporan())->assertCreated()->json('data');
        for ($i = 0; $i < 3; $i++) {
            $this->postJson(route('api.v1.humas.kinerja.tindakan', [$l['id'], 'perbarui-data']), ['versi' => $i, 'alasan' => 'Rekap diperiksa kembali'])->assertOk();
        }
        $this->getJson(route('api.v1.humas.kinerja.riwayat', [$l['id'], 'halaman' => 2, 'per_halaman' => 2]))->assertOk()->assertJsonPath('data.paginasi.halaman', 2)->assertJsonCount(2, 'data.items');
        $this->getJson(route('api.v1.humas.kinerja.index', ['halaman' => 2, 'per_halaman' => 1]))->assertOk()->assertJsonCount(0, 'data.items')->assertJsonPath('data.paginasi.halaman', 2);
        $this->getJson(route('api.v1.humas.kinerja.index', ['per_halaman' => 51]))->assertUnprocessable();
        $f = $this->faq('semua');
        foreach (['draf', 'terbit', 'arsip', 'draf'] as $v => $s) {
            $this->patchJson(route('api.v1.humas.faq.status', $f), ['versi' => $v, 'status' => $s, 'alasan' => 'Pemeriksaan informasi FAQ'])->assertOk();
        }
        $this->getJson(route('api.v1.humas.faq.riwayat', [$f, 'halaman' => 2, 'per_halaman' => 2]))->assertOk()->assertJsonPath('data.paginasi.halaman', 2)->assertJsonCount(2, 'data.items');
    }

    public function test_cetak_api_identik_dengan_web_dan_tetap_memakai_kop_tabel_dan_tanda_tangan(): void
    {
        $this->token($this->humas);
        $id = $this->postJson(route('api.v1.humas.kinerja.store'), $this->inputLaporan())->assertCreated()->json('data.id');
        foreach (['administrator', 'wakil_pimpinan_humas', 'pimpinan'] as $role) {
            $u = $this->akun($role);
            $this->token($u);
            $api = $this->getJson(route('api.v1.humas.kinerja.cetak', $id))->assertOk()->assertHeader('content-type', 'text/html; charset=utf-8')->getContent();
            $web = $this->actingAs($u)->get(route('kinerja-humas.cetak', $id))->assertOk()->getContent();
            $this->assertSame($web, $api, $role.' harus memakai cetakan yang sama');
            foreach (['images/logo-padang-panjang.png', 'images/kartu-pelajar/logo-smpn2pp.png', 'LAPORAN SEMENTARA', 'Realisasi program kerja', 'Kepala Sekolah', 'Wakil Kepala Sekolah Bidang Humas'] as $text) {
                $this->assertStringContainsString($text, $api);
            }
        }
    }

    public function test_izin_cetak_dan_menu_dicabut_tanpa_mengubah_snapshot_laporan(): void
    {
        $this->token($this->humas);
        $id = $this->postJson(route('api.v1.humas.kinerja.store'), $this->inputLaporan())->assertCreated()->json('data.id');
        $snapshot = KinerjaSemesterHumas::findOrFail($id)->snapshot;
        $this->humas->daftarPeran()->sync([Peran::where('kode', 'pegawai')->value('id')]);
        $this->token($this->humas->fresh());
        $this->getJson(route('api.v1.menu'))->assertOk()->assertJsonMissing(['kode' => 'kinerja-humas']);
        $this->getJson(route('api.v1.humas.kinerja.cetak', $id))->assertForbidden();
        $this->getJson(route('api.v1.humas.kinerja.show', $id))->assertForbidden();
        $this->assertSame($snapshot, KinerjaSemesterHumas::findOrFail($id)->snapshot);
    }

    public function test_siswa_dan_ortu_yang_diberi_role_administrator_tidak_boleh_mengelola_humas(): void
    {
        foreach (['siswa', 'orang_tua_a'] as $type) {
            $u = $this->akun($type);
            $u->daftarPeran()->attach(Peran::where('kode', 'administrator')->value('id'));
            $this->token($u);
            $this->getJson(route('api.v1.menu'))->assertOk()->assertJsonFragment(['kode' => 'faq-humas'])->assertJsonMissing(['kode' => 'kinerja-humas'])->assertJsonMissing(['kode' => 'agenda-humas']);
            $this->getJson(route('api.v1.humas.faq.referensi'))->assertOk()->assertJsonPath('data.dapat_kelola', false);
            $this->postJson(route('api.v1.humas.faq.store'), [])->assertForbidden();
            $this->getJson(route('api.v1.humas.kinerja.index'))->assertForbidden();
        }
    }

    public function test_menu_bidang_tidak_menyatakan_modul_native_yang_belum_dipasang(): void
    {
        $this->token($this->humas);
        $r = $this->getJson(route('api.v1.humas.dashboard.index'))->assertOk()->assertJsonCount(7, 'data.ruang_kerja');
        $native = ['agenda-humas', 'dokumen-humas', 'pengaduan-humas', 'umpan-balik-humas', 'kinerja-humas', 'kontak-humas', 'publikasi-humas', 'program-kerja-humas', 'komite-humas', 'kemitraan-humas', 'akreditasi-humas', 'aset-promosi-humas', 'media-resmi-humas', 'kliping-berita-humas', 'prestasi-sekolah', 'alumni-humas'];
        foreach ($r->json('data.ruang_kerja') as $room) {
            foreach ($room['items'] as $item) {
                if ($item['tersedia_mobile']) {
                    $this->assertContains($item['menu_mobile'], $native);
                } else {
                    $this->assertNull($item['menu_mobile']);
                }
                $this->assertArrayNotHasKey('route', $item);
            }
        }
        $role = Peran::create(['kode' => 'kinerja_tanpa_program', 'nama' => 'Kinerja tanpa sumber program', 'aktif' => true]);
        $role->izin()->attach(Izin::where('kode', 'kinerja_humas.kelola')->value('id'));
        $limited = $this->akun('pegawai');
        $limited->daftarPeran()->sync([$role->id]);
        $this->token($limited);
        $this->getJson(route('api.v1.humas.kinerja.referensi'))->assertOk()->assertJsonPath('data.dapat_kelola', true)->assertJsonPath('data.dapat_himpun', false);
    }

    private function akun(string $role): Pengguna
    {
        $family = $role === 'siswa' || str_starts_with($role, 'orang_tua');
        $u = Pengguna::create(['nama' => 'Uji '.$role, 'username' => 'align.'.$role.'.'.Pengguna::count(), 'kata_sandi' => 'UjiHumas2026!', 'peran' => 'pegawai', 'aktif' => true,
            'pegawai_id' => $family ? null : Pegawai::create(['nama_lengkap' => 'Uji '.$role, 'aktif' => true])->id, 'wajib_ganti_kata_sandi' => false]);
        $u->daftarPeran()->sync([Peran::where('kode', $family ? 'pegawai' : $role)->value('id')]);
        if ($role === 'siswa') {
            $u->update(['siswa_id' => Siswa::create(['nama_lengkap' => 'Siswa Uji', 'aktif' => true])->id]);
        } elseif ($family) {
            OrangTuaWali::create(['pengguna_id' => $u->id, 'nama_lengkap' => 'Orang Tua Uji']);
        }

        return $u->fresh();
    }

    private function faq(string $s, array $d = []): FaqHumas
    {
        $f = new FaqHumas;
        $f->forceFill(array_replace(['token_pembuatan' => Str::uuid(), 'pertanyaan' => 'FAQ '.$s, 'jawaban' => 'Hubungi operator sekolah untuk informasi lebih lanjut.', 'urusan' => 'kedinasan', 'sasaran' => $s, 'urutan' => 0, 'status' => 'terbit', 'diterbitkan_pada' => now()], $d))->save();

        return $f;
    }

    private function inputLaporan(): array
    {
        return ['token_pembuatan' => (string) Str::uuid(), 'tahun_pelajaran_id' => $this->tahun->id, 'semester' => 'ganjil', 'judul' => 'Laporan Kinerja Humas Semester Ganjil', 'tanggal_laporan' => '2026-10-22', 'nama_penyusun' => 'Wakil Humas Uji', 'nama_pimpinan' => 'Kepala Sekolah Uji', 'ringkasan' => 'Ringkasan kegiatan semester berjalan.', 'capaian' => 'Koordinasi program berjalan lancar.', 'kendala' => 'Penyesuaian jadwal beberapa kegiatan.', 'tindak_lanjut' => 'Koordinasikan jadwal kegiatan berikutnya.', 'rekomendasi' => 'Pertahankan koordinasi bersama mitra.'];
    }

    private function token(Pengguna $u): void
    {
        auth()->forgetGuards();
        $this->withToken($u->createToken('Uji keselarasan Humas', ['mobile'])->plainTextToken);
    }
}
