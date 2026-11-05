<?php

namespace Tests\Feature\Api;

use App\Jobs\KirimPushNotification;
use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\KelompokTahfidz;
use App\Models\KelulusanTahfidz;
use App\Models\NotifikasiPengguna;
use App\Models\OrangTuaWali;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PublikasiRaporTahfidz;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Mobile\TujuanNotifikasiMobileService;
use App\Services\Tahfidz\KelompokTahfidzService;
use App\Services\Tahfidz\SetoranTahfidzService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class TahfidzMobileApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 09:00:00');
        Queue::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_menu_dan_kelompok_mengikuti_penugasan_bukan_izin_palsu_siswa(): void
    {
        $d = $this->data();
        $this->login($d['guru'])->getJson('/api/v1/menu')->assertOk()->assertJsonFragment(['kode' => 'tahfidz', 'status' => 'tersedia', 'rute' => '/tahfidz']);
        $this->getJson('/api/v1/tahfidz')->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.nama', 'Kelompok Sendiri');
        $this->getJson('/api/v1/tahfidz/'.$d['lain']->id)->assertNotFound();
        $this->getJson('/api/v1/tahfidz/wisuda')->assertForbidden();
        $this->login($d['parent'])->getJson('/api/v1/menu')->assertOk()->assertJsonFragment(['kode' => 'tahfidz-anak', 'status' => 'tersedia', 'rute' => '/tahfidz-anak']);
        $this->getJson('/api/v1/tahfidz')->assertForbidden();
        $d['akunSiswa']->daftarPeran()->attach(Peran::where('kode', 'koordinator_tahfidz')->sole());
        $this->login($d['akunSiswa'])->getJson('/api/v1/tahfidz/referensi')->assertForbidden();
        $this->getJson('/api/v1/tahfidz-anak')->assertForbidden();
    }

    public function test_kelompok_dan_anggota_bisa_dikelola_native_dengan_pemeriksaan_versi(): void
    {
        $d = $this->data();
        $this->login($d['koordinator'])->postJson('/api/v1/tahfidz', ['tahun_pelajaran_id' => $d['tahun']->id, 'nama' => 'Kelompok Baru',
            'pembimbing_pegawai_id' => $d['guru']->pegawai_id, 'aktif' => true])->assertCreated();
        $g = KelompokTahfidz::where('nama', 'Kelompok Baru')->sole();
        $this->getJson('/api/v1/tahfidz/'.$g->id.'/anggota')->assertOk()->assertJsonCount(2, 'data.items');
        $payload = ['versi' => $g->versi, 'daftar_lengkap' => true, 'siswa_ids' => [$d['siswa']->id]];
        // Siswa sudah di kelompok aktif lain tidak boleh dipindahkan diam-diam.
        $this->putJson('/api/v1/tahfidz/'.$g->id.'/anggota', $payload)->assertUnprocessable();
        $payload['siswa_ids'] = [$d['siswaLain']->id];
        $this->putJson('/api/v1/tahfidz/'.$g->id.'/anggota', $payload)->assertOk();
        $this->putJson('/api/v1/tahfidz/'.$g->id.'/anggota', $payload)->assertUnprocessable()->assertJsonValidationErrors('versi');
        $this->assertSame(1, $g->anggotaAktif()->count());
    }

    public function test_setoran_retry_tidak_ganda_dan_orang_tua_hanya_melihat_pertemuan_selesai(): void
    {
        config(['services.firebase.push_enabled' => true]);
        $d = $this->data();
        $p = $this->meeting($d);
        $payload = $this->deposit($d, 114);
        $this->postJson($p.'/setoran', $payload)->assertOk();
        $this->postJson($p.'/setoran', $payload)->assertOk();
        $this->assertDatabaseCount('setoran_tahfidz', 1);
        $old = $this->postJson('/api/v1/tahfidz/'.$d['group']->id.'/pertemuan', ['tanggal' => '2026-11-04'])->assertOk()->json('data.id');
        $this->postJson('/api/v1/tahfidz/'.$d['group']->id.'/pertemuan/'.$old.'/setoran', $this->deposit($d, 113))->assertOk();
        $this->getJson('/api/v1/tahfidz/'.$d['group']->id.'/anggota/'.$d['anggota']->id)->assertOk()->assertJsonPath('data.riwayat.0.tanggal', '2026-11-05');
        $this->getJson($p)->assertOk()->assertJsonPath('data.items.0.nilai', '88,5')->assertJsonPath('data.catat', true);
        $this->login($d['parent'])->getJson('/api/v1/tahfidz-anak')->assertOk()->assertJsonPath('data.sudah', 0);
        $this->assertDatabaseCount('notifikasi_pengguna', 0);
        Queue::assertNothingPushed();
        $this->login($d['guru'])->postJson($p.'/selesaikan')->assertOk();
        $this->postJson($p.'/selesaikan')->assertOk();
        $this->postJson($p.'/setoran', $this->deposit($d, 113))->assertUnprocessable();
        $this->login($d['parent'])->getJson('/api/v1/tahfidz-anak')->assertOk()->assertJsonPath('data.sudah', 1)
            ->assertJsonPath('data.riwayat.0.nilai', '88,5')->assertDontSee('Catatan rahasia pembimbing');
        $this->assertPemberitahuan($d, 'tahfidz-pertemuan:', 'Perkembangan Tahfidz');
        Queue::assertPushed(KirimPushNotification::class, 2);
        $n = NotifikasiPengguna::where('kunci_unik', 'like', 'tahfidz-pertemuan:%')->where('pengguna_id', $d['parent']->id)->sole();
        $this->assertSame('/tahfidz-anak?siswa='.$d['siswa']->id, app(TujuanNotifikasiMobileService::class)->untuk($n));
        $s = NotifikasiPengguna::where('kunci_unik', $n->kunci_unik)->where('pengguna_id', $d['akunSiswa']->id)->sole();
        $this->login($d['akunSiswa'])->getJson('/api/v1/beranda')->assertOk()
            ->assertJsonPath('data.notifikasi.jumlah_belum_dibaca', 1)
            ->assertJsonPath('data.notifikasi.terbaru.0.tautan_mobile', '/tahfidz-saya');
        $this->getJson(route('api.v1.notifikasi.tujuan', $s))->assertOk()->assertJsonPath('data.tautan_mobile', '/tahfidz-saya');
        $this->getJson(route('api.v1.notifikasi.tujuan', $n))->assertForbidden();
        $this->login($d['parent'])->getJson('/api/v1/beranda')->assertOk()
            ->assertJsonPath('data.notifikasi.terbaru.0.tautan_mobile', '/tahfidz-anak?siswa='.$d['siswa']->id);
        $this->getJson(route('api.v1.notifikasi.tujuan', $s))->assertForbidden();
    }

    #[DataProvider('statusPenerimaSiswa')]
    public function test_notifikasi_hanya_untuk_identitas_siswa_dan_wali_aktif_yang_terkait(array $status, bool $menerima): void
    {
        config(['services.firebase.push_enabled' => true]);
        $d = $this->data();
        $d['akunSiswa']->update($status);
        foreach ([
            ['siswa_id' => $d['siswaLain']->id],
            ['siswa_id' => null],
        ] as $i => $atribut) {
            Pengguna::create([...$d['akunSiswa']->only(['kata_sandi', 'peran', 'aktif']), 'akun_sistem' => false,
                'nama' => 'Bukan penerima', 'username' => 'bukan-penerima-'.$i, ...$atribut]);
        }
        $nonaktif = $this->parentUser('wali-nonaktif', $d['siswa']);
        $nonaktif->update(['aktif' => false]);
        $p = $this->meeting($d);
        $this->postJson($p.'/setoran', $this->deposit($d, 114))->assertOk();
        $this->postJson($p.'/selesaikan')->assertOk();
        $this->assertEqualsCanonicalizing(
            $menerima ? [$d['parent']->id, $d['akunSiswa']->id] : [$d['parent']->id],
            NotifikasiPengguna::pluck('pengguna_id')->all(),
        );
        Queue::assertPushed(KirimPushNotification::class, $menerima ? 2 : 1);
    }

    public static function statusPenerimaSiswa(): array
    {
        return [
            'siswa aktif' => [['aktif' => true, 'akun_sistem' => false], true],
            'siswa nonaktif' => [['aktif' => false, 'akun_sistem' => false], false],
            'akun sistem' => [['aktif' => true, 'akun_sistem' => true], false],
        ];
    }

    public function test_push_tidak_keluar_jika_penyelesaian_pertemuan_dibatalkan_transaksi(): void
    {
        config(['services.firebase.push_enabled' => true]);
        $d = $this->data();
        $p = $this->meeting($d);
        $this->postJson($p.'/setoran', $this->deposit($d, 114))->assertOk();
        $pertemuan = $d['group']->pertemuan()->sole();
        DB::beginTransaction();
        try {
            app(SetoranTahfidzService::class)->selesaikan($d['guru'], $d['group'], $pertemuan->id);
            $this->assertDatabaseCount('notifikasi_pengguna', 2);
            Queue::assertNothingPushed();
        } finally {
            DB::rollBack();
        }
        $this->assertDatabaseCount('notifikasi_pengguna', 0);
        $this->assertNull($pertemuan->fresh()->selesai_pada);
        Queue::assertNothingPushed();
        $this->postJson($p.'/selesaikan')->assertOk();
        Queue::assertPushed(KirimPushNotification::class, 2);
    }

    public function test_parent_tidak_dapat_membaca_anak_lain_atau_pertemuan_staf(): void
    {
        $d = $this->data();
        $this->login($d['parent'])->getJson('/api/v1/tahfidz-anak?siswa='.$d['siswaLain']->id)->assertNotFound();
        $this->getJson('/api/v1/tahfidz/'.$d['group']->id)->assertForbidden();
        $this->postJson('/api/v1/tahfidz/'.$d['group']->id.'/pertemuan', ['tanggal' => '2026-11-05'])->assertForbidden();
        $this->getJson('/api/v1/tahfidz-anak')->assertOk()->assertJsonCount(1, 'data.anak')->assertHeader('Cache-Control', 'max-age=0, no-store, private');
    }

    public function test_rapor_native_menjaga_sidik_publikasi_dan_penarikan_akses(): void
    {
        config(['services.firebase.push_enabled' => true]);
        $d = $this->data();
        $p = $this->meeting($d);
        $this->postJson($p.'/setoran', $this->deposit($d, 114))->assertOk();
        $path = '/api/v1/tahfidz/'.$d['group']->id.'/anggota/'.$d['anggota']->id;
        $this->login($d['koordinator'])->postJson('/api/v1/tahfidz/target-rapor', ['tahun_pelajaran_id' => $d['tahun']->id, 'tingkat' => 7,
            'target' => 1, 'mode' => 'tetap', 'surah' => [114], 'tanggal_rapor' => '2026-11-05', 'batas_cukup' => 70, 'batas_baik' => 80, 'batas_sangat_baik' => 90])->assertOk();
        $detail = $this->getJson($path)->assertOk()->assertJsonPath('data.rapor.rata', '88,50')->json('data');
        $this->getJson('/api/v1/tahfidz/'.$d['group']->id.'/rapor')->assertOk()->assertJsonCount(1, 'data.rapor')->assertJsonPath('data.rapor.0.rata', '88,50');
        $this->assertDatabaseCount('publikasi_rapor_tahfidz', 0);
        $this->postJson($path.'/rapor/publikasi', ['bagikan' => true, 'sidik' => str_repeat('0', 64)])->assertUnprocessable();
        $this->assertDatabaseCount('notifikasi_pengguna', 0);
        Queue::assertNothingPushed();
        $this->postJson($path.'/rapor/publikasi', ['bagikan' => true, 'sidik' => $detail['sidik']])->assertOk();
        $this->postJson($path.'/rapor/publikasi', ['bagikan' => true, 'sidik' => $detail['sidik']])->assertOk();
        $this->assertPemberitahuan($d, 'tahfidz-rapor:', 'Rapor Tahfidz dibagikan');
        Queue::assertPushed(KirimPushNotification::class, 2);
        $r = PublikasiRaporTahfidz::sole();
        $this->login($d['akunSiswa'])->getJson('/api/v1/tahfidz-anak/rapor/'.$r->id)->assertForbidden();
        $this->login($d['parent'])->getJson('/api/v1/tahfidz-anak/rapor/'.$r->id)->assertOk()->assertJsonPath('data.rapor.nama', $d['siswa']->nama_lengkap);
        $this->login($d['parentLain'])->getJson('/api/v1/tahfidz-anak/rapor/'.$r->id)->assertNotFound();
        $this->login($d['guru'])->postJson($path.'/rapor/publikasi', ['bagikan' => false])->assertForbidden();
        $this->login($d['koordinator'])->postJson($path.'/rapor/publikasi', ['bagikan' => false])->assertOk();
        $this->login($d['parent'])->getJson('/api/v1/tahfidz-anak/rapor/'.$r->id)->assertNotFound();
        Queue::assertPushed(KirimPushNotification::class, 2);
    }

    public function test_wisuda_dan_sertifikat_hanya_terbit_setelah_37_surah_dan_dibagikan_secara_eksplisit(): void
    {
        config(['services.firebase.push_enabled' => true]);
        $d = $this->data();
        $p = $this->meeting($d);
        $student = '/api/v1/tahfidz/'.$d['group']->id.'/anggota/'.$d['anggota']->id;
        $this->postJson($student.'/verifikasi')->assertUnprocessable();
        foreach (range(78, 114) as $surah) {
            $this->postJson($p.'/setoran', $this->deposit($d, $surah))->assertOk();
        }
        $this->postJson($student.'/verifikasi')->assertOk();
        $l = KelulusanTahfidz::sole();
        $this->login($d['koordinator'])->postJson('/api/v1/tahfidz/wisuda', ['nama' => 'Wisuda Tahfidz', 'tanggal' => '2026-11-05', 'lokasi' => 'Sekolah', 'kepala_sekolah' => 'Kepala Sekolah Uji'])->assertOk();
        $event = $this->getJson('/api/v1/tahfidz/wisuda')->assertOk()->json('data.acara.0.id');
        $cert = '/api/v1/tahfidz/wisuda/kelulusan/'.$l->id;
        $this->postJson($cert.'/terbitkan', ['wisuda_tahfidz_id' => $event])->assertOk();
        $this->assertDatabaseCount('notifikasi_pengguna', 0);
        Queue::assertNothingPushed();
        $this->login($d['parent'])->getJson('/api/v1/tahfidz-anak/sertifikat/'.$l->id)->assertNotFound();
        $this->login($d['koordinator'])->postJson($cert.'/publikasi', ['bagikan' => true])->assertOk();
        $this->postJson($cert.'/publikasi', ['bagikan' => true])->assertOk();
        $this->assertPemberitahuan($d, 'tahfidz-sertifikat:', 'Sertifikat Tahfidz dibagikan');
        Queue::assertPushed(KirimPushNotification::class, 2);
        $this->login($d['akunSiswa'])->getJson('/api/v1/tahfidz-anak/sertifikat/'.$l->id)->assertForbidden();
        $this->login($d['parent'])->getJson('/api/v1/tahfidz-anak/sertifikat/'.$l->id)->assertOk()->assertJsonPath('data.sertifikat.nama', $d['siswa']->nama_lengkap);
        $this->login($d['parentLain'])->getJson('/api/v1/tahfidz-anak/sertifikat/'.$l->id)->assertNotFound();
        $this->login($d['koordinator'])->postJson($cert.'/publikasi', ['bagikan' => false])->assertOk();
        $this->login($d['parent'])->getJson('/api/v1/tahfidz-anak/sertifikat/'.$l->id)->assertNotFound();
        Queue::assertPushed(KirimPushNotification::class, 2);
    }

    public function test_tahfidz_saya_hanya_menampilkan_setoran_sendiri_yang_sudah_diselesaikan(): void
    {
        $d = $this->data();
        $p = $this->meeting($d);
        $this->postJson($p.'/setoran', $this->deposit($d, 114))->assertOk();
        $this->login($d['akunSiswa'])->getJson('/api/v1/tahfidz-saya')->assertOk()
            ->assertJsonPath('data.siswa.id', $d['siswa']->id)->assertJsonPath('data.sudah', 0)->assertJsonCount(0, 'data.riwayat');
        $this->postJson($p.'/setoran', $this->deposit($d, 113))->assertForbidden();
        $this->login($d['guru'])->postJson($p.'/selesaikan')->assertOk();
        $response = $this->login($d['akunSiswa'])->getJson('/api/v1/tahfidz-saya')->assertOk()
            ->assertJsonPath('data.siswa.nama', 'Anak Sendiri')->assertJsonPath('data.sudah', 1)
            ->assertJsonPath('data.target', 37)->assertJsonPath('data.riwayat.0.nilai', '88,5')
            ->assertDontSee('Catatan rahasia pembimbing')->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->assertArrayNotHasKey('rapor', $response->json('data'));
        $this->assertArrayNotHasKey('sertifikat', $response->json('data'));
        foreach (['siswa', 'siswa_id', 'anggota_tahfidz_id'] as $field) {
            $this->getJson('/api/v1/tahfidz-saya?'.$field.'='.$d['siswaLain']->id)
                ->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $other = Pengguna::create(['nama' => 'Siswa lain', 'username' => 'siswa-lain-uji', 'kata_sandi' => 'UjiTahfidz2026',
            'peran' => 'siswa', 'siswa_id' => $d['siswaLain']->id, 'aktif' => true]);
        $this->login($other)->getJson('/api/v1/tahfidz-saya')->assertOk()
            ->assertJsonPath('data.siswa.id', $d['siswaLain']->id)->assertJsonPath('data.sudah', 0)
            ->assertJsonCount(0, 'data.riwayat')->assertDontSee('Anak Sendiri');
    }

    public function test_menu_tahfidz_saya_hanya_untuk_identitas_siswa(): void
    {
        $this->getJson('/api/v1/tahfidz-saya')->assertUnauthorized();
        $d = $this->data();
        $this->login($d['akunSiswa'])->getJson('/api/v1/menu')->assertOk()
            ->assertJsonFragment(['kode' => 'tahfidz-saya', 'status' => 'tersedia', 'rute' => '/tahfidz-saya'])
            ->assertJsonMissing(['kode' => 'tahfidz-anak'])->assertJsonMissing(['kode' => 'tahfidz']);
        foreach ([$d['guru'], $d['parent'], $d['koordinator']] as $user) {
            $this->login($user)->getJson('/api/v1/tahfidz-saya')->assertForbidden();
            $this->getJson('/api/v1/menu')->assertOk()->assertJsonMissing(['kode' => 'tahfidz-saya']);
        }
        $d['guru']->update(['peran' => 'siswa']);
        $this->login($d['guru'])->getJson('/api/v1/tahfidz-saya')->assertForbidden();
    }

    private function assertPemberitahuan(array $d, string $awalan, string $judulSiswa): void
    {
        $notifikasi = NotifikasiPengguna::where('kunci_unik', 'like', $awalan.'%')->get();
        $this->assertEqualsCanonicalizing([$d['akunSiswa']->id, $d['parent']->id], $notifikasi->pluck('pengguna_id')->all());
        $siswa = $notifikasi->firstWhere('pengguna_id', $d['akunSiswa']->id);
        $this->assertSame($judulSiswa, $siswa->judul);
        $this->assertSame('/tahfidz-saya', app(TujuanNotifikasiMobileService::class)->untuk($siswa));
        foreach ($notifikasi as $n) {
            $this->assertStringNotContainsString('Catatan rahasia pembimbing', $n->pesan);
            Queue::assertPushed(KirimPushNotification::class, fn ($job) => $job->notifikasiId === $n->id);
        }
    }

    private function login(Pengguna $user): self
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($user->createToken('Tahfidz API', ['mobile'])->plainTextToken);

        return $this;
    }

    private function meeting(array $d): string
    {
        $id = $this->login($d['guru'])->postJson('/api/v1/tahfidz/'.$d['group']->id.'/pertemuan', ['tanggal' => '2026-11-05'])->assertOk()->json('data.id');

        return '/api/v1/tahfidz/'.$d['group']->id.'/pertemuan/'.$id;
    }

    private function deposit(array $d, int $surah): array
    {
        return ['anggota_tahfidz_id' => $d['anggota']->id, 'nomor_surah' => $surah, 'nilai' => '88,50', 'catatan' => 'Catatan rahasia pembimbing', 'token_setoran' => (string) Str::uuid()];
    }

    private function data(): array
    {
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'aktif' => true, 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30']);
        $kelas = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'VII.A', 'tingkat' => 7, 'aktif' => true]);
        $siswa = Siswa::create(['nama_lengkap' => 'Anak Sendiri', 'aktif' => true]);
        $siswaLain = Siswa::create(['nama_lengkap' => 'Anak Lain', 'aktif' => true]);
        foreach ([$siswa, $siswaLain] as $s) {
            AnggotaKelas::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'siswa_id' => $s->id, 'status_keanggotaan' => 'aktif']);
        }
        $guru = $this->teacher('Guru Sendiri');
        $guruLain = $this->teacher('Guru Lain');
        $koordinator = $this->teacher('Koordinator');
        $koordinator->daftarPeran()->attach(Peran::where('kode', 'koordinator_tahfidz')->sole());
        $group = KelompokTahfidz::create(['tahun_pelajaran_id' => $tahun->id, 'pembimbing_pegawai_id' => $guru->pegawai_id, 'nama' => 'Kelompok Sendiri', 'aktif' => true])->refresh();
        $lain = KelompokTahfidz::create(['tahun_pelajaran_id' => $tahun->id, 'pembimbing_pegawai_id' => $guruLain->pegawai_id, 'nama' => 'Kelompok Lain', 'aktif' => true])->refresh();
        app(KelompokTahfidzService::class)->simpanAnggota($group, [$siswa->id], $group->versi);
        $group->refresh();
        $anggota = $group->anggotaAktif()->sole();
        $parent = $this->parentUser('wali-sendiri', $siswa);
        $parentLain = $this->parentUser('wali-lain', $siswaLain);
        $akunSiswa = Pengguna::create(['nama' => 'Siswa', 'username' => 'siswa-uji', 'kata_sandi' => 'UjiTahfidz2026', 'peran' => 'siswa', 'siswa_id' => $siswa->id, 'aktif' => true]);
        $akunSiswa->daftarPeran()->attach(Peran::where('kode', 'siswa')->sole());

        return compact('tahun', 'siswa', 'siswaLain', 'guru', 'guruLain', 'koordinator', 'group', 'lain', 'anggota', 'parent', 'parentLain', 'akunSiswa');
    }

    private function teacher(string $nama): Pengguna
    {
        $p = Pegawai::create(['nama_lengkap' => $nama, 'jenis_pegawai' => 'Guru', 'aktif' => true]);
        $u = Pengguna::create(['nama' => $nama, 'username' => 'guru-'.$p->id, 'pegawai_id' => $p->id, 'kata_sandi' => 'UjiTahfidz2026', 'peran' => 'pegawai', 'aktif' => true]);
        $u->daftarPeran()->attach(Peran::where('kode', 'pegawai')->sole());

        return $u;
    }

    private function parentUser(string $name, Siswa $siswa): Pengguna
    {
        $u = Pengguna::create(['nama' => $name, 'username' => $name, 'kata_sandi' => 'UjiTahfidz2026', 'peran' => 'orang_tua', 'aktif' => true]);
        $u->daftarPeran()->attach(Peran::where('kode', 'orang_tua')->sole());
        $p = OrangTuaWali::create(['pengguna_id' => $u->id, 'nama_lengkap' => $name]);
        $p->siswa()->attach($siswa->id, ['hubungan' => 'wali', 'utama' => true]);

        return $u;
    }
}
