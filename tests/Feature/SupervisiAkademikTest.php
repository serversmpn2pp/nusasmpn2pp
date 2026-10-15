<?php

namespace Tests\Feature;

use App\Models\GuruMataPelajaran;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\NotifikasiPengguna;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\ProgramSupervisi;
use App\Models\RiwayatSupervisi;
use App\Models\SesiSupervisi;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\VersiInstrumenSupervisi;
use App\Services\Supervisi\InstrumenSupervisiAwal;
use App\Services\Supervisi\InstrumenSupervisiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;

class SupervisiAkademikTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 15)->setTime(8, 0));
        config(['services.firebase.push_enabled' => false]);
    }

    public function test_instrumen_awal_dan_izin_sesuai_template(): void
    {
        $v = VersiInstrumenSupervisi::firstOrFail();
        $this->assertSame(['perencanaan' => ['jumlah_indikator' => 30, 'skor_maksimal' => 120, 'bobot' => 50], 'pelaksanaan' => ['jumlah_indikator' => 49, 'skor_maksimal' => 196, 'bobot' => 50]], $v->ringkasan());
        $this->assertTrue(Peran::where('kode', 'wakil_pimpinan_kurikulum')->first()->memilikiIzin('supervisi.kelola'));
        $this->assertFalse(Peran::where('kode', 'guru_mapel')->first()->memilikiIzin('supervisi.kelola'));
    }

    public function test_api_memerlukan_mobile_aktif_dan_jenis_akun_yang_benar(): void
    {
        $this->getJson('/api/v1/supervisi')->assertUnauthorized();
        $d = $this->data();
        $this->login($d['admin'], ['web']);
        $this->getJson('/api/v1/supervisi')->assertForbidden();
        $s = Siswa::create(['nama_lengkap' => 'Siswa', 'aktif' => true]);
        $u = Pengguna::create(['nama' => 'Siswa', 'username' => 'siswa-supervisi', 'siswa_id' => $s->id, 'peran' => 'administrator', 'aktif' => true, 'kata_sandi' => 'test-pass']);
        $this->login($u);
        $this->getJson('/api/v1/supervisi')->assertForbidden();
        $d['guru']->update(['wajib_ganti_kata_sandi' => true]);
        $this->login($d['guru']);
        $this->getJson('/api/v1/supervisi')->assertStatus(428);
        $this->login($d['supervisor']);
        $this->getJson('/api/v1/supervisi/instrumen')->assertForbidden();
        $this->postJson('/api/v1/supervisi/program', [])->assertForbidden();
    }

    public function test_program_validasi_tanggal_idempotensi_versi_dan_arsip(): void
    {
        $d = $this->data();
        $this->login($d['admin']);
        $payload = $this->programData($d);
        $id = $this->postJson('/api/v1/supervisi/program', $payload)->assertOk()->json('data.id');
        $this->postJson('/api/v1/supervisi/program', $payload)->assertOk()->assertJsonPath('data.id', $id);
        $this->assertSame(1, ProgramSupervisi::count());
        $this->postJson('/api/v1/supervisi/program', [...$payload, 'nama' => 'Berubah'])->assertUnprocessable();
        $this->putJson('/api/v1/supervisi/program/'.$id, [...$payload, 'versi' => 9, 'alasan' => 'Perubahan periode supervisi.'])->assertUnprocessable();
        $this->putJson('/api/v1/supervisi/program/'.$id, [...$payload, 'versi' => 1, 'status' => 'diarsipkan', 'alasan' => 'Program telah diarsipkan.'])->assertOk();
        $this->postJson('/api/v1/supervisi/program/'.$id.'/jadwal', ['sesi' => [$this->sesiData($d)]])->assertUnprocessable();
        $this->postJson('/api/v1/supervisi/program', [...$payload, 'token_pembuatan' => (string) Str::uuid(), 'tanggal_mulai' => '2025-01-01'])->assertUnprocessable();
    }

    public function test_instrumen_dinamis_versi_terbit_tidak_dapat_diubah(): void
    {
        $d = $this->data();
        $this->login($d['admin']);
        $input = $this->instrumenData();
        $v = $this->postJson('/api/v1/supervisi/instrumen', $input)->assertOk()->assertJsonPath('data.ringkasan.perencanaan.skor_maksimal', 8)->json('data');
        $input['kelompok'][0]['indikator'][] = 'Indikator tambahan';
        $v = $this->putJson('/api/v1/supervisi/instrumen/'.$v['id'], [...$input, 'versi' => 1])->assertOk()->assertJsonPath('data.ringkasan.perencanaan.skor_maksimal', 12)->json('data');
        $v = $this->postJson('/api/v1/supervisi/instrumen/'.$v['id'].'/terbitkan', $this->kontrol($v['versi']))->assertOk()->json('data');
        $this->putJson('/api/v1/supervisi/instrumen/'.$v['id'], [...$input, 'versi' => $v['versi']])->assertUnprocessable();
        $revisi = $this->postJson('/api/v1/supervisi/instrumen/'.$v['id'].'/revisi', $this->kontrol($v['versi']))->assertOk()->assertJsonPath('data.nomor', 2)->json('data');
        $this->putJson('/api/v1/supervisi/instrumen/'.$revisi['id'], [...$input, 'nama' => 'Nama revisi', 'versi' => 1])->assertOk();
        $this->getJson('/api/v1/supervisi/instrumen/'.$v['id'])->assertOk()->assertJsonPath('data.nama', 'Instrumen uji');
        $this->postJson('/api/v1/supervisi/instrumen/'.$v['id'].'/revisi', $this->kontrol($v['versi']))->assertUnprocessable();
    }

    public function test_instrumen_tanpa_dua_tahap_atau_skala_tidak_valid_ditolak(): void
    {
        $d = $this->data();
        $this->login($d['admin']);
        $i = $this->instrumenData();
        $i['kelompok'][1]['tahap'] = 'perencanaan';
        $this->postJson('/api/v1/supervisi/instrumen', $i)->assertUnprocessable();
        $i = $this->instrumenData();
        $i['skala'][1]['skor'] = 6;
        $this->postJson('/api/v1/supervisi/instrumen', $i)->assertUnprocessable();
        $i = $this->instrumenData();
        $i['kelompok'][0]['indikator'][0] = '   ';
        $this->postJson('/api/v1/supervisi/instrumen', $i)->assertUnprocessable();
    }

    public function test_draf_privat_diterbitkan_hanya_ke_guru_dan_supervisor(): void
    {
        $d = $this->data();
        $s = $this->buatSesi($d);
        $this->assertSame(0, NotifikasiPengguna::where('kunci_unik', 'like', 'supervisi:%')->count());
        foreach (['guru', 'supervisor', 'lain'] as $key) {
            $this->login($d[$key]);
            $this->getJson('/api/v1/supervisi')->assertOk()->assertJsonCount(0, 'data.daftar.data');
            $this->getJson('/api/v1/supervisi/sesi/'.$s->id)->assertNotFound();
        }
        $this->terbitkan($d, $s);
        foreach (['guru', 'supervisor'] as $key) {
            $this->login($d[$key]);
            $this->getJson('/api/v1/supervisi')->assertOk()->assertJsonCount(1, 'data.daftar.data')->assertJsonMissing(['token_pembuatan' => $s->token_pembuatan]);
            $this->getJson('/api/v1/supervisi/sesi/'.$s->id)->assertOk()->assertJsonPath('data.instrumen.ringkasan.pelaksanaan.jumlah_indikator', 49);
            $this->getJson('/api/v1/supervisi/program/'.$s->program_supervisi_id)->assertOk()->assertJsonCount(1, 'data.sesi');
        }
        $this->assertSame(2, NotifikasiPengguna::where('kunci_unik', 'like', 'supervisi:%')->count());
        $this->login($d['lain']);
        $this->getJson('/api/v1/supervisi/sesi/'.$s->id)->assertNotFound();
        $this->getJson('/api/v1/supervisi/program/'.$s->program_supervisi_id)->assertNotFound();
        $this->getJson('/api/v1/supervisi/program')->assertOk()->assertJsonCount(0, 'data.daftar.data');
    }

    public function test_jadwal_mandiri_bentrok_dan_batch_atomik_ditolak(): void
    {
        $d = $this->data();
        $p = $this->buatProgram($d);
        $this->login($d['admin']);
        $b = $this->sesiData($d);
        $this->postJson('/api/v1/supervisi/program/'.$p->id.'/jadwal', ['sesi' => [[...$b, 'supervisor_pegawai_id' => $d['guru']->pegawai_id]]])->assertUnprocessable();
        $this->postJson('/api/v1/supervisi/program/'.$p->id.'/jadwal', ['sesi' => [$b, [...$b, 'token_pembuatan' => (string) Str::uuid()]]])->assertUnprocessable();
        $this->assertSame(0, SesiSupervisi::count());
        $s = $this->postJson('/api/v1/supervisi/program/'.$p->id.'/jadwal', ['sesi' => [$b]])->assertOk()->json('data.items.0.id');
        $this->postJson('/api/v1/supervisi/program/'.$p->id.'/jadwal', ['sesi' => [$b]])->assertOk()->assertJsonPath('data.items.0.id', $s);
        $this->assertSame(1, SesiSupervisi::count());
        $this->postJson('/api/v1/supervisi/program/'.$p->id.'/jadwal', ['sesi' => [[...$b, 'token_pembuatan' => (string) Str::uuid()]]])->assertUnprocessable();
    }

    public function test_jadwal_mengajar_dan_batas_waktu_diperiksa(): void
    {
        $d = $this->data();
        $p = $this->buatProgram($d);
        $this->login($d['admin']);
        $b = $this->sesiData($d);
        $b['alasan_di_luar_jadwal'] = '';
        $url = '/api/v1/supervisi/program/'.$p->id.'/jadwal';
        $this->postJson($url, ['sesi' => [$b]])->assertUnprocessable();
        $jam = JamPelajaran::create(['hari' => 'senin', 'nomor_jam' => 1, 'jam_mulai' => '08:00:00', 'jam_selesai' => '09:00:00', 'jenis' => 'pelajaran', 'aktif' => true]);
        JadwalPelajaran::create(['tahun_pelajaran_id' => $d['tahun']->id, 'kelas_id' => $d['gmp']->kelas_id, 'hari' => 'senin', 'jam_pelajaran_id' => $jam->id, 'guru_mata_pelajaran_id' => $d['gmp']->id, 'aktif' => true]);
        $this->postJson($url, ['sesi' => [$b]])->assertOk();
        $b['token_pembuatan'] = (string) Str::uuid();
        $b['waktu_mulai'] = '2026-10-19T09:00';
        $b['waktu_selesai'] = '2026-10-19T08:00';
        $this->postJson($url, ['sesi' => [$b]])->assertUnprocessable();
    }

    public function test_supervisor_sedang_mengajar_tidak_dapat_ditugaskan(): void
    {
        $d = $this->data();
        $p = $this->buatProgram($d);
        $this->login($d['admin']);
        $kelas = Kelas::create(['nama' => 'VIII.B', 'tingkat' => 8, 'tahun_pelajaran_id' => $d['tahun']->id, 'aktif' => true]);
        $g = GuruMataPelajaran::create(['tahun_pelajaran_id' => $d['tahun']->id, 'kelas_id' => $kelas->id, 'mata_pelajaran_id' => $d['gmp']->mata_pelajaran_id, 'pegawai_id' => $d['supervisor']->pegawai_id, 'aktif' => true]);
        $jam = JamPelajaran::create(['hari' => 'senin', 'nomor_jam' => 1, 'jam_mulai' => '08:00:00', 'jam_selesai' => '09:00:00', 'jenis' => 'pelajaran', 'aktif' => true]);
        JadwalPelajaran::create(['tahun_pelajaran_id' => $d['tahun']->id, 'kelas_id' => $kelas->id, 'hari' => 'senin', 'jam_pelajaran_id' => $jam->id, 'guru_mata_pelajaran_id' => $g->id, 'aktif' => true]);
        $this->postJson('/api/v1/supervisi/program/'.$p->id.'/jadwal', ['sesi' => [$this->sesiData($d)]])->assertUnprocessable();
    }

    public function test_revisi_instrumen_tidak_mengubah_sesi_lama_dan_nonaktif_mencegah_penugasan_baru(): void
    {
        $d = $this->data();
        $s = $this->buatSesi($d);
        $this->login($d['admin']);
        $v = $s->instrumen;
        $revisi = $this->postJson('/api/v1/supervisi/instrumen/'.$v->id.'/revisi', $this->kontrol($v->versi))->assertOk()->json('data');
        $i = $this->instrumenData();
        $i['kelompok'][0]['indikator'][] = 'Perubahan baru';
        $this->putJson('/api/v1/supervisi/instrumen/'.$revisi['id'], [...$i, 'versi' => 1])->assertOk();
        $this->assertSame(120, $s->fresh()->instrumen->ringkasan()['perencanaan']['skor_maksimal']);
        $this->postJson('/api/v1/supervisi/instrumen/'.$v->id.'/aktif', [...$this->kontrol($v->versi), 'aktif_sekarang' => true])->assertOk();
        $b = $this->sesiData($d);
        $b['waktu_mulai'] = '2026-10-20T08:00';
        $b['waktu_selesai'] = '2026-10-20T09:00';
        $this->postJson('/api/v1/supervisi/program/'.$s->program_supervisi_id.'/jadwal', ['sesi' => [$b]])->assertUnprocessable();
    }

    public function test_penerbitan_atomik_stale_tidak_mengirim_notifikasi(): void
    {
        $d = $this->data();
        $s = $this->buatSesi($d);
        $this->login($d['admin']);
        $b = $this->sesiData($d);
        $b['waktu_mulai'] = '2026-10-20T08:00';
        $b['waktu_selesai'] = '2026-10-20T09:00';
        $id = $this->postJson('/api/v1/supervisi/program/'.$s->program_supervisi_id.'/jadwal', ['sesi' => [$b]])->assertOk()->json('data.items.0.id');
        $this->postJson('/api/v1/supervisi/program/'.$s->program_supervisi_id.'/jadwal/terbitkan', [...$this->kontrol(1), 'sesi' => [['id' => $s->id, 'versi' => 1], ['id' => $id, 'versi' => 8]]])->assertUnprocessable();
        $this->assertSame(0, SesiSupervisi::where('status', 'terjadwal')->count());
        $this->assertSame(0, NotifikasiPengguna::where('kunci_unik', 'like', 'supervisi:%')->count());
    }

    public function test_perubahan_supervisor_dan_pembatalan_dengan_riwayat(): void
    {
        $d = $this->data();
        $s = $this->buatSesi($d);
        $this->terbitkan($d, $s);
        $s->refresh();
        $this->login($d['admin']);
        $b = $this->sesiData($d);
        $this->putJson('/api/v1/supervisi/sesi/'.$s->id, [...$b, 'supervisor_pegawai_id' => $d['lain']->pegawai_id, 'versi' => $s->versi, 'alasan' => 'Supervisor sebelumnya berhalangan hadir.'])->assertOk();
        $s->refresh();
        $this->assertSame(5, NotifikasiPengguna::where('kunci_unik', 'like', 'supervisi:%')->count());
        $this->assertDatabaseHas('notifikasi_pengguna', ['pengguna_id' => $d['supervisor']->id, 'judul' => 'Penugasan supervisor telah dialihkan', 'tautan' => '/supervisi']);
        $this->login($d['supervisor']);
        $this->getJson('/api/v1/supervisi/sesi/'.$s->id)->assertNotFound();
        $this->login($d['admin']);
        $this->postJson('/api/v1/supervisi/program/'.$s->program_supervisi_id.'/jadwal/batalkan', [...$this->kontrol($s->versi), 'sesi' => [['id' => $s->id, 'versi' => $s->versi]]])->assertOk();
        $this->login($d['guru']);
        $this->getJson('/api/v1/supervisi/sesi/'.$s->id)->assertOk()->assertJsonPath('data.sesi.status', 'dibatalkan');
        $this->assertSame(4, RiwayatSupervisi::where('sesi_supervisi_id', $s->id)->count());
    }

    public function test_hal_build_web_dan_fixture_visual(): void
    {
        $d = $this->data();
        $s = $this->buatSesi($d);
        $this->actingAs($d['admin']);
        $p = $s->program;
        $revisi = app(InstrumenSupervisiService::class)->tindakan(
            $d['admin'], $s->instrumen, 'revisi', $this->kontrol($s->instrumen->versi)
        );
        $pages = [
            'index' => route('supervisi.index'), 'program' => route('supervisi.program.index'), 'program-show' => route('supervisi.program.show', $p),
            'program-form' => route('supervisi.program.create'), 'program-edit' => route('supervisi.program.edit', $p),
            'jadwal-form' => route('supervisi.jadwal.create', $p), 'jadwal-edit' => route('supervisi.sesi.edit', $s),
            'instrumen' => route('supervisi.instrumen.index'), 'instrumen-show' => route('supervisi.instrumen.show', $s->instrumen),
            'instrumen-form' => route('supervisi.instrumen.create'), 'sesi' => route('supervisi.sesi.show', $s),
            'instrumen-revisi' => route('supervisi.instrumen.edit', $revisi),
        ];
        foreach ($pages as $name => $url) {
            $response = $this->get($url)->assertOk();
            if (getenv('NUSA_CAPTURE_SUPERVISI') === '1') {
                File::ensureDirectoryExists(storage_path('framework/testing/supervisi'));
                File::put(storage_path('framework/testing/supervisi/'.$name.'.html'), $response->getContent());
            }
        }
        $this->post(route('supervisi.program.store'), [...$this->programData($d), 'token_pembuatan' => (string) Str::uuid()])->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_waka_dapat_mengelola_pimpinan_hanya_monitor_dan_akun_nonaktif_ditolak(): void
    {
        $d = $this->data();
        $d['lain']->daftarPeran()->sync([Peran::where('kode', 'wakil_pimpinan_kurikulum')->value('id')]);
        $this->login($d['lain']);
        $id = $this->postJson('/api/v1/supervisi/program', $this->programData($d))->assertOk()->json('data.id');
        $this->getJson('/api/v1/supervisi/instrumen')->assertOk();
        $this->getJson('/api/v1/supervisi/pilihan?tahun_pelajaran_id='.$d['tahun']->id)->assertOk()->assertJsonPath('data.timezone', 'Asia/Jakarta');
        $d['supervisor']->daftarPeran()->sync([Peran::where('kode', 'pimpinan')->value('id')]);
        $this->login($d['supervisor']);
        $this->getJson('/api/v1/supervisi/program/'.$id)->assertOk();
        $this->getJson('/api/v1/supervisi/program')->assertOk()->assertJsonMissing(['token_pembuatan' => ProgramSupervisi::find($id)->token_pembuatan]);
        $this->postJson('/api/v1/supervisi/program', $this->programData($d))->assertForbidden();
        $d['supervisor']->update(['aktif' => false]);
        $this->login($d['supervisor']);
        $this->getJson('/api/v1/supervisi')->assertUnauthorized();
    }

    public function test_program_draf_dan_penugasan_berubah_tidak_dapat_diterbitkan(): void
    {
        $d = $this->data();
        $s = $this->buatSesi($d);
        $p = $s->program;
        $p->update(['status' => 'draf']);
        $this->login($d['admin']);
        $payload = [...$this->kontrol(1), 'sesi' => [['id' => $s->id, 'versi' => 1]]];
        $url = '/api/v1/supervisi/program/'.$p->id.'/jadwal/terbitkan';
        $this->postJson($url, $payload)->assertUnprocessable();
        $p->update(['status' => 'aktif']);
        $d['gmp']->update(['pegawai_id' => $d['lain']->pegawai_id]);
        $this->postJson($url, $payload)->assertUnprocessable();
        $this->assertSame('draf', $s->fresh()->status);
        $this->assertSame(0, NotifikasiPengguna::where('kunci_unik', 'like', 'supervisi:%')->count());
    }

    public function test_jadwal_asing_ditolak_dan_request_penerbitan_ulang_tidak_duplikat(): void
    {
        $d = $this->data();
        $s = $this->buatSesi($d);
        $p = $this->buatProgram($d);
        $this->login($d['admin']);
        $payload = [...$this->kontrol(1), 'sesi' => [['id' => $s->id, 'versi' => 1]]];
        $this->postJson('/api/v1/supervisi/program/'.$p->id.'/jadwal/terbitkan', $payload)->assertNotFound();
        $this->terbitkan($d, $s);
        $this->postJson('/api/v1/supervisi/program/'.$s->program_supervisi_id.'/jadwal/terbitkan', $payload)->assertUnprocessable();
        $this->assertSame(2, NotifikasiPengguna::where('kunci_unik', 'like', 'supervisi:%')->count());
    }

    public function test_jadwal_bersebelahan_boleh_dan_batal_membebaskan_slot(): void
    {
        $d = $this->data();
        $s = $this->buatSesi($d);
        $this->login($d['admin']);
        $b = $this->sesiData($d);
        $b['waktu_mulai'] = '2026-10-19T09:00';
        $b['waktu_selesai'] = '2026-10-19T10:00';
        $url = '/api/v1/supervisi/program/'.$s->program_supervisi_id.'/jadwal';
        $this->postJson($url, ['sesi' => [$b]])->assertOk();
        $this->postJson($url.'/batalkan', [...$this->kontrol(1), 'sesi' => [['id' => $s->id, 'versi' => 1]]])->assertOk();
        $this->postJson($url, ['sesi' => [$this->sesiData($d)]])->assertOk();
        $this->assertSame(2, SesiSupervisi::where('status', 'draf')->count());
    }

    public function test_halaman_guru_hanya_memiliki_menu_dan_jadwal_pribadi(): void
    {
        $d = $this->data();
        $s = $this->buatSesi($d);
        $this->terbitkan($d, $s);
        $this->actingAs($d['guru'])->get(route('supervisi.index'))->assertOk()->assertSeeText('Supervisi Saya')->assertDontSeeText('Tambah program');
        $this->get(route('supervisi.sesi.edit', $s))->assertForbidden();
        $this->get(route('supervisi.instrumen.index'))->assertForbidden();
        $this->get(route('supervisi.program.show', $s->program))->assertOk()->assertDontSee('id="sp-bulk"', false);
    }

    public function test_tahun_tanpa_tanggal_dan_status_paket_stale_ditolak(): void
    {
        $d = $this->data();
        $this->login($d['admin']);
        $d['tahun']->update(['tanggal_mulai' => null]);
        $this->postJson('/api/v1/supervisi/program', $this->programData($d))->assertUnprocessable();
        $v = VersiInstrumenSupervisi::first();
        $revisi = $this->postJson('/api/v1/supervisi/instrumen/'.$v->id.'/revisi', $this->kontrol($v->versi))->assertOk()->json('data');
        $this->postJson('/api/v1/supervisi/instrumen/'.$v->id.'/aktif', [...$this->kontrol($v->versi), 'aktif_sekarang' => true])->assertOk();
        $this->postJson('/api/v1/supervisi/instrumen/'.$revisi['id'].'/aktif', [...$this->kontrol($revisi['versi']), 'aktif_sekarang' => true])->assertUnprocessable();
        $this->assertFalse($v->instrumen->fresh()->aktif);
    }

    private function kontrol(int $versi): array
    {
        return ['versi' => $versi, 'konfirmasi' => true, 'alasan' => 'Penugasan sesuai program supervisi sekolah.'];
    }

    private function instrumenData(): array
    {
        return ['token_pembuatan' => (string) Str::uuid(), 'nama' => 'Instrumen uji', 'catatan' => 'Instrumen untuk uji supervisi.', 'skala' => InstrumenSupervisiAwal::skala(),
            'kelompok' => [['tahap' => 'perencanaan', 'nama' => 'Perencanaan', 'indikator' => ['Tujuan jelas', 'Modul tersedia']], ['tahap' => 'pelaksanaan', 'nama' => 'Pelaksanaan', 'indikator' => ['Pembelajaran aktif']]]];
    }

    private function buatProgram(array $d): ProgramSupervisi
    {
        $this->login($d['admin']);
        $id = $this->postJson('/api/v1/supervisi/program', $this->programData($d))->assertOk()->json('data.id');

        return ProgramSupervisi::findOrFail($id);
    }

    private function buatSesi(array $d): SesiSupervisi
    {
        $p = $this->buatProgram($d);
        $id = $this->postJson('/api/v1/supervisi/program/'.$p->id.'/jadwal', ['sesi' => [$this->sesiData($d)]])->assertOk()->json('data.items.0.id');

        return SesiSupervisi::findOrFail($id);
    }

    private function terbitkan(array $d, SesiSupervisi $s): void
    {
        $this->login($d['admin']);
        $this->postJson('/api/v1/supervisi/program/'.$s->program_supervisi_id.'/jadwal/terbitkan', [...$this->kontrol($s->versi), 'sesi' => [['id' => $s->id, 'versi' => $s->versi]]])->assertOk();
    }

    private function programData(array $d): array
    {
        return ['token_pembuatan' => (string) Str::uuid(), 'nama' => 'Supervisi Semester Ganjil 2026/2027', 'tujuan' => 'Pendampingan peningkatan kualitas pembelajaran.', 'tahun_pelajaran_id' => $d['tahun']->id, 'semester' => 'ganjil', 'tanggal_mulai' => '2026-10-01', 'tanggal_selesai' => '2026-11-30', 'status' => 'aktif'];
    }

    private function sesiData(array $d): array
    {
        return ['token_pembuatan' => (string) Str::uuid(), 'guru_mata_pelajaran_id' => $d['gmp']->id, 'versi_instrumen_supervisi_id' => VersiInstrumenSupervisi::first()->id,
            'supervisor_pegawai_id' => $d['supervisor']->pegawai_id, 'waktu_mulai' => '2026-10-19T08:00', 'waktu_selesai' => '2026-10-19T09:00', 'tempat' => 'Ruang VIII.A', 'topik' => 'Sistem pernapasan manusia', 'alasan_di_luar_jadwal' => 'Penjadwalan khusus sesuai kesepakatan guru.'];
    }

    private function login(Pengguna $u, array $abilities = ['mobile']): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($u->createToken('Supervisi uji', $abilities)->plainTextToken);
    }

    private function data(): array
    {
        $admin = Pengguna::where('username', 'administrator')->firstOrFail();
        $users = [];
        foreach (['guru' => 'Robi Candra, S.Pd.', 'supervisor' => 'Supervisor Akademik', 'lain' => 'Guru Lain'] as $key => $nama) {
            $pegawai = Pegawai::create(['nama_lengkap' => $nama, 'jenis_pegawai' => 'Guru', 'aktif' => true]);
            $users[$key] = Pengguna::create(['nama' => $nama, 'username' => 'supervisi-'.$key, 'pegawai_id' => $pegawai->id, 'peran' => 'pegawai', 'aktif' => true, 'kata_sandi' => 'test-pass', 'wajib_ganti_kata_sandi' => false]);
        }
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
        $kelas = Kelas::create(['nama' => 'VIII.A', 'tingkat' => 8, 'tahun_pelajaran_id' => $tahun->id, 'aktif' => true]);
        $mapel = MataPelajaran::create(['nama' => 'Ilmu Pengetahuan Alam', 'urutan' => 1, 'aktif' => true]);
        $gmp = GuruMataPelajaran::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'mata_pelajaran_id' => $mapel->id, 'pegawai_id' => $users['guru']->pegawai_id, 'aktif' => true]);

        return compact('admin', 'tahun', 'gmp') + $users;
    }
}
