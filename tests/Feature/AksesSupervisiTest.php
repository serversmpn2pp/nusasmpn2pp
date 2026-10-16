<?php

namespace Tests\Feature;

use App\Models\GuruMataPelajaran;
use App\Models\InstrumenSupervisi;
use App\Models\Izin;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\OrangTuaWali;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\PenilaianSupervisi;
use App\Models\Peran;
use App\Models\PraobservasiSupervisi;
use App\Models\ProgramSupervisi;
use App\Models\SesiSupervisi;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\VersiInstrumenSupervisi;
use App\Services\Supervisi\AksesSupervisi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class AksesSupervisiTest extends TestCase
{
    use RefreshDatabase;

    private array $akun;

    private SesiSupervisi $s;

    private SesiSupervisi $asing;

    private GuruMataPelajaran $gmp;

    private string $api = '/api/v1/supervisi';

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 25)->setTime(10, 0));
        config(['services.firebase.push_enabled' => false]);
        $this->akun['admin'] = Pengguna::where('username', 'administrator')->firstOrFail();
        foreach (['guru' => 'guru_mapel', 'supervisor' => 'pegawai', 'lain' => 'guru_mapel', 'kurikulum' => 'wakil_pimpinan_kurikulum', 'pimpinan' => 'pimpinan', 'tu' => 'tata_usaha'] as $key => $role) {
            $p = Pegawai::create(['nama_lengkap' => 'Akun '.ucfirst($key), 'jenis_pegawai' => 'Guru', 'aktif' => true]);
            $u = Pengguna::create(['nama' => $p->nama_lengkap, 'username' => 'akses-supervisi-'.$key, 'pegawai_id' => $p->id, 'peran' => 'pegawai', 'aktif' => true, 'kata_sandi' => 'uji-supervisi', 'wajib_ganti_kata_sandi' => false]);
            $u->daftarPeran()->attach(Peran::where('kode', $role)->firstOrFail());
            $this->akun[$key] = $u;
        }
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
        $kelas = Kelas::create(['nama' => 'VIII.A', 'tingkat' => 8, 'tahun_pelajaran_id' => $tahun->id, 'aktif' => true]);
        $mapel = MataPelajaran::create(['nama' => 'Informatika', 'urutan' => 1, 'aktif' => true]);
        $this->gmp = GuruMataPelajaran::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'mata_pelajaran_id' => $mapel->id, 'pegawai_id' => $this->akun['guru']->pegawai_id, 'aktif' => true]);
        $p = ProgramSupervisi::create(['token_pembuatan' => (string) Str::uuid(), 'sidik_pembuatan' => str_repeat('a', 64), 'nama' => 'Program Ganjil', 'tujuan' => 'Pendampingan pembelajaran.', 'tahun_pelajaran_id' => $tahun->id, 'semester' => 'ganjil', 'tanggal_mulai' => '2026-10-01', 'tanggal_selesai' => '2026-11-30', 'status' => 'aktif']);
        $v = VersiInstrumenSupervisi::firstOrFail();
        $this->s = SesiSupervisi::create(['token_pembuatan' => (string) Str::uuid(), 'sidik_pembuatan' => str_repeat('b', 64), 'program_supervisi_id' => $p->id, 'versi_instrumen_supervisi_id' => $v->id, 'guru_mata_pelajaran_id' => $this->gmp->id,
            'guru_pegawai_id' => $this->akun['guru']->pegawai_id, 'supervisor_pegawai_id' => $this->akun['supervisor']->pegawai_id, 'kelas_id' => $kelas->id, 'waktu_mulai' => '2026-10-19 08:00', 'waktu_selesai' => '2026-10-19 09:00', 'tempat' => 'Ruang VIII.A', 'status' => 'terjadwal', 'versi' => 2, 'diterbitkan_pada' => now(), 'konteks' => ['guru' => 'Guru Peserta', 'mapel' => 'Informatika', 'kelas' => 'VIII.A', 'instrumen' => $v->nama, 'instrumen_nomor' => 1]]);
        PraobservasiSupervisi::create(['sesi_supervisi_id' => $this->s->id, 'versi' => 2, 'status' => 'terkirim', 'isian' => ['topik' => 'Data dan informasi', 'tujuan_pembelajaran' => 'Mengolah data.', 'kegiatan_pembelajaran' => 'Diskusi dan praktik.', 'rencana_asesmen' => 'Rubrik praktik.'], 'dikirim_pada' => now()]);
        $nilai = [];
        foreach ($v->kelompok as $g => $group) {
            foreach ($group['indikator'] as $i => $label) {
                $nilai[] = ['kelompok' => $g, 'indikator' => $i, 'skor' => 4, 'catatan' => 'Bukti pengamatan.'];
            }
        }
        PenilaianSupervisi::create(['sesi_supervisi_id' => $this->s->id, 'supervisor_pegawai_id' => $this->s->supervisor_pegawai_id, 'praobservasi_versi' => 2, 'versi' => 2, 'status' => 'final', 'nilai' => $nilai, 'tanggal_observasi' => '2026-10-19', 'kekuatan' => 'Pembelajaran aktif.', 'perbaikan' => 'Perkuat refleksi.', 'difinalisasi_pada' => now()]);
        $this->asing = $this->s->replicate();
        $this->asing->fill(['token_pembuatan' => (string) Str::uuid(), 'guru_pegawai_id' => $this->akun['lain']->pegawai_id, 'supervisor_pegawai_id' => $this->akun['lain']->pegawai_id, 'konteks' => [...$this->s->konteks, 'guru' => 'GURU DI LUAR CAKUPAN']])->save();
    }

    private function login(string $key): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($this->akun[$key]->createToken('Audit supervisi', ['mobile'])->plainTextToken);
    }

    private function menu(string $key): array
    {
        $this->login($key);

        return collect($this->getJson('/api/v1/menu')->assertOk()->json('data.kelompok'))->pluck('items')->flatten(1)->where('subkelompok', 'Supervisi')->keyBy('kode')->all();
    }

    public function test_izin_bawaan_hanya_milik_pengelola_dan_pemantau(): void
    {
        foreach (Peran::all() as $role) {
            $izin = $role->izin()->where('kode', 'like', 'supervisi.%')->pluck('kode')->sort()->values()->all();
            $expected = match ($role->kode) {
                'administrator', 'wakil_pimpinan_kurikulum' => ['supervisi.instrumen_kelola', 'supervisi.kelola', 'supervisi.lihat'],
                'pimpinan' => ['supervisi.lihat'], default => [],
            };
            $this->assertSame($expected, $izin, $role->kode);
        }
    }

    public function test_pemulihan_izin_idempoten_tidak_mengubah_instrumen_dan_izin_kustom(): void
    {
        $role = Peran::where('kode', 'wakil_pimpinan_kurikulum')->firstOrFail();
        $role->izin()->detach(Izin::where('kode', 'supervisi.kelola')->value('id'));
        $custom = Izin::create(['nama' => 'Izin khusus', 'kode' => 'uji.khusus', 'kelompok' => 'Pengujian', 'aktif' => true]);
        $role->izin()->attach($custom);
        $before = [InstrumenSupervisi::first()->getAttributes(), VersiInstrumenSupervisi::first()->getAttributes(), $this->s->fresh()->getAttributes(), PenilaianSupervisi::first()->getAttributes()];
        $created = (string) Izin::where('kode', 'supervisi.lihat')->value('created_at');
        $migration = require database_path('migrations/2026_10_16_000101_pastikan_izin_peran_supervisi.php');
        $migration->up();
        $migration->up();
        $this->assertTrue($role->memilikiIzin('supervisi.kelola'));
        $this->assertTrue($role->memilikiIzin('uji.khusus'));
        $this->assertSame(3, Izin::where('kode', 'like', 'supervisi.%')->count());
        $this->assertSame($created, (string) Izin::where('kode', 'supervisi.lihat')->value('created_at'));
        $this->assertSame($before, [InstrumenSupervisi::first()->getAttributes(), VersiInstrumenSupervisi::first()->getAttributes(), $this->s->fresh()->getAttributes(), PenilaianSupervisi::first()->getAttributes()]);
    }

    public function test_wakil_kurikulum_mengelola_tanpa_menjadi_administrator(): void
    {
        $this->assertFalse($this->akun['kurikulum']->administrator());
        $this->login('kurikulum');
        $this->getJson($this->api.'/akses')->assertOk()->assertJsonPath('data.hak_akses.kelola', true)->assertJsonPath('data.hak_akses.instrumen', true)->assertJsonPath('data.hak_akses.lingkup', 'sekolah')->assertJsonCount(4, 'data.menu');
        foreach (['', '/program', '/instrumen', '/rekap', '/program/tambah', '/pilihan?tahun_pelajaran_id='.$this->gmp->tahun_pelajaran_id] as $path) {
            $this->getJson($this->api.$path)->assertOk();
        }
        $payload = ['token_pembuatan' => (string) Str::uuid(), 'nama' => 'Program Waka Kurikulum', 'tujuan' => 'Pendampingan oleh wakil kurikulum.', 'tahun_pelajaran_id' => $this->gmp->tahun_pelajaran_id, 'semester' => 'ganjil', 'tanggal_mulai' => '2026-10-01', 'tanggal_selesai' => '2026-11-30', 'status' => 'aktif'];
        $id = $this->postJson($this->api.'/program', $payload)->assertOk()->json('data.id');
        $this->postJson($this->api.'/program/'.$id.'/jadwal', ['sesi' => [[
            'token_pembuatan' => (string) Str::uuid(), 'guru_mata_pelajaran_id' => $this->gmp->id, 'versi_instrumen_supervisi_id' => $this->s->versi_instrumen_supervisi_id, 'supervisor_pegawai_id' => $this->akun['supervisor']->pegawai_id, 'waktu_mulai' => '2026-10-26T08:00', 'waktu_selesai' => '2026-10-26T09:00', 'tempat' => 'Ruang VIII.A', 'alasan_di_luar_jadwal' => 'Jadwal telah disepakati bersama.',
        ]]])->assertOk();
    }

    public function test_pimpinan_monitoring_dan_cetak_tanpa_hak_pengelolaan(): void
    {
        $this->login('pimpinan');
        $this->getJson($this->api.'/akses')->assertOk()->assertJsonPath('data.hak_akses.monitor', true)->assertJsonPath('data.hak_akses.kelola', false)->assertJsonPath('data.hak_akses.instrumen', false)->assertJsonCount(3, 'data.menu');
        $this->getJson($this->api)->assertOk()->assertJsonCount(2, 'data.daftar.data');
        $this->getJson($this->api.'/sesi/'.$this->s->id.'/laporan')->assertOk();
        $this->get($this->api.'/rekap/excel')->assertOk()->assertDownload();
        foreach (['/program/tambah', '/pilihan?tahun_pelajaran_id='.$this->gmp->tahun_pelajaran_id, '/instrumen', '/sesi/'.$this->s->id.'/edit'] as $path) {
            $this->getJson($this->api.$path)->assertForbidden();
        }
        $this->postJson($this->api.'/program', [])->assertForbidden();
        $this->putJson($this->api.'/program/'.$this->s->program_supervisi_id, [])->assertForbidden();
    }

    public function test_pengelola_tidak_mengisi_atau_menilai_atas_nama_peserta(): void
    {
        foreach (['admin', 'kurikulum', 'pimpinan'] as $key) {
            $this->login($key);
            $base = $this->api.'/sesi/'.$this->s->id;
            $this->getJson($base)->assertOk()->assertJsonPath('data.pengisian.hak.isi_praobservasi', false)->assertJsonPath('data.pengisian.hak.isi_penilaian', false);
            $this->putJson($base.'/praobservasi', [])->assertForbidden();
            $this->putJson($base.'/penilaian', [])->assertForbidden();
            $this->postJson($base.'/penilaian/finalisasi', [])->assertForbidden();
            $this->putJson($base.'/refleksi', [])->assertForbidden();
            $this->postJson($base.'/tindak-lanjut', [])->assertForbidden();
        }
    }

    public function test_pembukaan_koreksi_hanya_pengelola_dengan_alasan_dan_riwayat(): void
    {
        $base = $this->api.'/sesi/'.$this->s->id.'/penilaian/buka-ulang';
        $input = ['versi_sesi' => 2, 'versi' => 2, 'konfirmasi' => true, 'alasan' => 'Perlu pemeriksaan bukti observasi.'];
        $this->login('pimpinan');
        $this->postJson($base, $input)->assertForbidden();
        $this->login('supervisor');
        $this->postJson($base, $input)->assertForbidden();
        $this->login('kurikulum');
        $this->postJson($base, [...$input, 'alasan' => ''])->assertUnprocessable();
        $this->postJson($base, $input)->assertOk()->assertJsonPath('data.status', 'draf');
        $this->assertDatabaseHas('riwayat_supervisi', ['sesi_supervisi_id' => $this->s->id, 'pengguna_id' => $this->akun['kurikulum']->id, 'alasan' => $input['alasan']]);
    }

    public function test_guru_dan_supervisor_hanya_melihat_sesi_penugasannya(): void
    {
        foreach (['guru', 'supervisor'] as $key) {
            $this->login($key);
            foreach (['', '/rekap', '/program/'.$this->s->program_supervisi_id] as $path) {
                $this->getJson($this->api.$path)->assertOk()->assertDontSee('GURU DI LUAR CAKUPAN');
            }
            foreach (['', '/praobservasi', '/penilaian', '/pascaobservasi', '/laporan', '/laporan/cetak'] as $path) {
                $this->getJson($this->api.'/sesi/'.$this->asing->id.$path)->assertNotFound();
            }
            $this->postJson($this->api.'/laporan/cetak', ['sesi' => [$this->s->id, $this->asing->id]])->assertNotFound();
            $this->getJson($this->api.'/rekap?tab=belum_terjadwal')->assertForbidden();
            $this->getJson($this->api.'/akses')->assertJsonPath('data.hak_akses.lingkup', 'penugasan')->assertJsonCount(1, 'data.menu');
        }
    }

    public function test_peran_lain_tanpa_penugasan_tidak_mendapat_menu_atau_data(): void
    {
        $this->login('tu');
        $this->getJson($this->api.'/akses')->assertOk()->assertJsonCount(0, 'data.menu')->assertJsonPath('data.hak_akses.pribadi', false);
        $this->getJson($this->api)->assertOk()->assertJsonCount(0, 'data.daftar.data');
        $this->assertSame([], $this->menu('tu'));
        $this->getJson($this->api.'/sesi/'.$this->s->id)->assertNotFound();
    }

    public function test_menu_web_dan_katalog_api_memakai_cakupan_yang_sama(): void
    {
        foreach (['kurikulum' => 4, 'pimpinan' => 3, 'guru' => 1, 'supervisor' => 1, 'tu' => 0] as $key => $jumlah) {
            $menu = $this->menu($key);
            $this->assertCount($jumlah, $menu);
            foreach ($menu as $item) {
                $this->assertSame('segera_hadir', $item['status']);
                $this->assertNull($item['rute']);
                $this->assertTrue($item['api_tersedia']);
            }
            $this->app['auth']->forgetGuards();
            $this->actingAs($this->akun[$key]);
            $response = $this->get(route('supervisi.index'))->assertOk();
            foreach (app(AksesSupervisi::class)->menu($this->akun[$key]) as $item) {
                $response->assertSeeText($item['label']);
            }
            if (in_array($key, ['guru', 'supervisor', 'tu'], true)) {
                $response->assertDontSeeText('Program Supervisi')->assertDontSeeText('Instrumen Supervisi');
            }
            $this->capture('akses-'.$key, $response);
        }
    }

    public function test_peran_kustom_dengan_izin_eksplisit_tetap_didukung_di_menu_web_dan_api(): void
    {
        $role = Peran::create(['nama' => 'Tim Supervisi', 'kode' => 'tim_supervisi', 'aktif' => true]);
        $role->izin()->attach(Izin::where('kode', 'supervisi.kelola')->firstOrFail());
        $this->akun['tu']->daftarPeran()->attach($role);
        $this->assertCount(3, $this->menu('tu'));
        $this->getJson($this->api.'/akses')->assertJsonPath('data.hak_akses.kelola', true)->assertJsonPath('data.hak_akses.instrumen', false);
        $this->getJson($this->api.'/instrumen')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->akun['tu']);
        $r = $this->get(route('supervisi.index'))->assertOk()->assertSeeText('Program Supervisi')->assertSeeText('Rekap Supervisi')->assertDontSeeText('Instrumen Supervisi');
        $this->capture('akses-delegasi', $r);
    }

    public function test_izin_instrumen_saja_tidak_membuka_monitoring_sekolah(): void
    {
        $role = Peran::create(['nama' => 'Tim Instrumen', 'kode' => 'tim_instrumen', 'aktif' => true]);
        $role->izin()->attach(Izin::where('kode', 'supervisi.instrumen_kelola')->firstOrFail());
        $this->akun['tu']->daftarPeran()->attach($role);
        $menu = $this->menu('tu');
        $this->assertSame(['instrumen-supervisi'], array_keys($menu));
        $this->getJson($this->api.'/instrumen')->assertOk();
        $this->getJson($this->api)->assertJsonCount(0, 'data.daftar.data');
        $this->postJson($this->api.'/program', [])->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->akun['tu']);
        $r = $this->get(route('supervisi.instrumen.index'))->assertOk()->assertSeeText('Instrumen Supervisi');
        $this->capture('akses-instrumen', $r);
    }

    public function test_pegawai_nonaktif_ditolak_meski_punya_role_pengelola(): void
    {
        $this->akun['kurikulum']->pegawai->update(['aktif' => false]);
        $this->login('kurikulum');
        foreach (['', '/akses', '/program', '/instrumen', '/rekap', '/sesi/'.$this->s->id, '/sesi/'.$this->s->id.'/laporan'] as $path) {
            $this->getJson($this->api.$path)->assertForbidden();
        }
        $this->postJson($this->api.'/program', [])->assertForbidden();
        $this->postJson($this->api.'/sesi/'.$this->s->id.'/lampiran', [])->assertForbidden();
        $this->assertSame([], $this->menu('kurikulum'));
        $this->app['auth']->forgetGuards();
        $this->actingAs($this->akun['kurikulum']);
        $this->get(route('supervisi.index'))->assertForbidden();
    }

    public function test_role_nonaktif_dan_pencabutan_izin_segera_membatasi_akses(): void
    {
        $role = Peran::where('kode', 'wakil_pimpinan_kurikulum')->firstOrFail();
        $role->update(['aktif' => false]);
        $this->assertSame([], $this->menu('kurikulum'));
        $this->getJson($this->api.'/program/tambah')->assertForbidden();
        $role->update(['aktif' => true]);
        $role->izin()->detach(Izin::where('kode', 'like', 'supervisi.%')->pluck('id'));
        $this->assertSame([], $this->menu('kurikulum'));
        $this->getJson($this->api.'/akses')->assertJsonPath('data.hak_akses.kelola', false);
        $this->getJson($this->api.'/program/tambah')->assertForbidden();
    }

    public function test_siswa_dan_orang_tua_tidak_mendapat_akses_meski_salah_diberi_role_waka(): void
    {
        foreach (['siswa', 'orang_tua'] as $jenis) {
            $u = Pengguna::create(['nama' => 'Akun '.$jenis, 'username' => 'audit-'.$jenis, 'aktif' => true, 'peran' => $jenis, 'kata_sandi' => 'uji-supervisi', 'wajib_ganti_kata_sandi' => false]);
            if ($jenis === 'siswa') {
                $u->update(['siswa_id' => Siswa::create(['nama_lengkap' => 'Siswa uji', 'aktif' => true])->id]);
            } else {
                OrangTuaWali::create(['pengguna_id' => $u->id, 'nama_lengkap' => 'Wali uji']);
            }
            $u->daftarPeran()->attach(Peran::where('kode', 'wakil_pimpinan_kurikulum')->firstOrFail());
            $this->akun[$jenis] = $u;
            $this->assertSame([], $this->menu($jenis));
            foreach (['/akses', '', '/program', '/rekap', '/instrumen', '/sesi/'.$this->s->id.'/laporan'] as $path) {
                $this->getJson($this->api.$path)->assertForbidden();
            }
            $u->update(['peran' => 'administrator']);
            $this->login($jenis);
            $this->getJson($this->api.'/akses')->assertForbidden();
            $this->postJson($this->api.'/sesi/'.$this->s->id.'/lampiran', [])->assertForbidden();
            $this->app['auth']->forgetGuards();
            $this->actingAs($u);
            $this->get(route('supervisi.index'))->assertForbidden();
        }
    }

    public function test_draf_nilai_tidak_terbuka_bagi_waka_pimpinan_dan_guru(): void
    {
        $this->s->penilaian->update(['status' => 'draf', 'kekuatan' => 'DRAF PRIVAT SUPERVISOR']);
        foreach (['guru', 'kurikulum', 'pimpinan', 'admin'] as $key) {
            $this->login($key);
            foreach (['', '/penilaian'] as $path) {
                $this->getJson($this->api.'/sesi/'.$this->s->id.$path)->assertOk()->assertDontSee('DRAF PRIVAT SUPERVISOR');
            }
            $this->getJson($this->api.'/rekap')->assertOk()->assertJsonPath('data.statistik.hasil_final', 0);
            $this->getJson($this->api.'/sesi/'.$this->s->id.'/laporan')->assertUnprocessable();
        }
        $this->login('supervisor');
        $this->getJson($this->api.'/sesi/'.$this->s->id.'/penilaian')->assertOk()->assertSee('DRAF PRIVAT SUPERVISOR');
    }

    public function test_penugasan_baru_dan_pergantian_supervisor_mengikuti_identitas_bukan_role(): void
    {
        $this->s->penilaian->delete();
        $this->s->update(['supervisor_pegawai_id' => $this->akun['pimpinan']->pegawai_id]);
        $this->login('supervisor');
        $this->getJson($this->api.'/sesi/'.$this->s->id)->assertNotFound();
        $this->assertSame([], $this->menu('supervisor'));
        $this->login('pimpinan');
        $this->getJson($this->api.'/sesi/'.$this->s->id)->assertOk()->assertJsonPath('data.pengisian.hak.isi_penilaian', true)->assertJsonPath('data.pengisian.hak.isi_praobservasi', false);
        $this->putJson($this->api.'/sesi/'.$this->s->id.'/penilaian', [])->assertUnprocessable();
    }

    public function test_menu_penugasan_tidak_muncul_sebelum_jadwal_terbit(): void
    {
        $this->s->update(['status' => 'draf', 'diterbitkan_pada' => null]);
        foreach (['guru', 'supervisor'] as $key) {
            $this->assertSame([], $this->menu($key));
            $this->getJson($this->api.'/sesi/'.$this->s->id)->assertNotFound();
        }
        $this->s->update(['status' => 'terjadwal', 'diterbitkan_pada' => now()]);
        foreach (['guru', 'supervisor'] as $key) {
            $this->assertArrayHasKey('supervisi-saya', $this->menu($key));
        }
    }

    public function test_endpoint_akses_mengikuti_gerbang_autentikasi_mobile(): void
    {
        $this->getJson($this->api.'/akses')->assertUnauthorized();
        $u = $this->akun['kurikulum'];
        $this->app['auth']->forgetGuards();
        $this->withToken($u->createToken('Web saja', ['web'])->plainTextToken)->getJson($this->api.'/akses')->assertForbidden();
        $u->update(['wajib_ganti_kata_sandi' => true]);
        $this->login('kurikulum');
        $this->getJson($this->api.'/akses')->assertStatus(428);
        $u->update(['aktif' => false]);
        $this->login('kurikulum');
        $this->getJson($this->api.'/akses')->assertUnauthorized();
    }

    public function test_peran_ganda_tetap_memisahkan_pengelolaan_dan_tugas_penilaian(): void
    {
        $this->akun['guru']->daftarPeran()->attach(Peran::where('kode', 'wakil_pimpinan_kurikulum')->firstOrFail());
        $this->s->penilaian->delete();
        $this->s->praobservasi->update(['status' => 'draf']);
        $menu = $this->menu('guru');
        $this->assertCount(4, $menu);
        $this->assertArrayNotHasKey('supervisi-saya', $menu);
        $this->getJson($this->api.'/sesi/'.$this->s->id)->assertOk()->assertJsonPath('data.hak_akses.kelola', true)->assertJsonPath('data.pengisian.hak.isi_praobservasi', true)->assertJsonPath('data.pengisian.hak.isi_penilaian', false);
        $this->putJson($this->api.'/sesi/'.$this->asing->id.'/penilaian', [])->assertForbidden();
    }

    private function capture(string $name, $response): void
    {
        if (getenv('NUSA_CAPTURE_SUPERVISI') !== '1') {
            return;
        }
        $dir = storage_path('framework/testing/supervisi');
        File::ensureDirectoryExists($dir);
        File::put($dir.'/'.$name.'.html', $response->getContent());
    }
}
