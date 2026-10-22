<?php

namespace Tests\Feature\Api;

use App\Http\Resources\Api\V1\PenggunaResource;
use App\Models\AgendaHumas;
use App\Models\DokumenHumas;
use App\Models\Izin;
use App\Models\MitraHumas;
use App\Models\OrangTuaWali;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Mobile\HumasTahapDuaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class HumasNativeTahapDuaTest extends TestCase
{
    use RefreshDatabase;

    private Pengguna $humas;

    private TahunPelajaran $tahun;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        Queue::fake();
        Storage::fake('local');
        $this->travelTo(now()->setDate(2026, 10, 22)->setTime(10, 0));
        $this->tahun = TahunPelajaran::create(['nama' => '2026/2027', 'aktif' => true, 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30']);
        $this->humas = $this->akun('wakil_pimpinan_humas');
        $this->token($this->humas);
    }

    private function url(string $path): string
    {
        return '/api/v1/humas/'.$path;
    }

    private function create(string $path, array $data): array
    {
        return $this->postJson($this->url($path), $data + ['token_pembuatan' => (string) Str::uuid()])->assertCreated()->json('data');
    }

    public function test_program_laporan_final_revisi_dan_bukti_menggunakan_aturan_web(): void
    {
        $p = $this->create('program', $this->program());
        $base = 'program/'.$p['id'];
        $this->getJson($this->url($base))->assertOk()->assertJsonPath('data.realisasi', 0);
        $input = $this->laporan();
        $l = $this->create($base.'/laporan', $input);
        $path = $base.'/laporan/'.$l['id'];
        $this->postJson($this->url($path.'/bukti'), ['token_pembuatan' => (string) Str::uuid(), 'versi' => 0, 'judul' => 'Bukti pelaksanaan', 'dokumen_humas_id' => $this->dokumen()->id])->assertOk();
        $this->postJson($this->url($path.'/tindakan/finalisasi'), ['versi' => 1])->assertOk()->assertJsonPath('data.status', 'final')->assertJsonPath('data.versi', 2);
        $this->getJson($this->url($path))->assertOk()->assertJsonPath('data.status', 'final')->assertJsonPath('data.hak_akses.dapat_kelola', false);
        $this->patchJson($this->url($path), $input + ['versi' => 2, 'catatan_perubahan' => 'Jangan menimpa laporan final'])->assertUnprocessable();
        $this->postJson($this->url($path.'/tindakan/revisi'), ['versi' => 2, 'catatan_perubahan' => 'Koreksi uraian kegiatan'])->assertOk()->assertJsonPath('data.status', 'draf');
        $this->patchJson($this->url($path), $input + ['versi' => 2, 'catatan_perubahan' => 'Versi usang ditolak'])->assertUnprocessable();
        $this->getJson($this->url($path.'/riwayat'))->assertOk()->assertDontSee('snapshot')->assertDontSee('lokasi_file');
        $this->getJson($this->url($path.'/cetak'))->assertOk()->assertSee('LAPORAN');
    }

    public function test_komite_upload_sk_aktivasi_program_dan_kepemilikan_pengurus(): void
    {
        $input = $this->komite();
        $p = $this->create('komite', $input);
        $path = 'komite/'.$p['id'];
        $this->post($this->url($path), array_replace($input, ['_method' => 'PATCH', 'versi' => 0, 'catatan_perubahan' => 'Menambahkan SK resmi', 'metode' => 'unggah', 'nomor_sk' => 'SK/01/2026', 'tanggal_sk' => '2026-09-01', 'status' => 'aktif', 'pengurus' => $this->getJson($this->url($path))->json('data.pengurus'), 'berkas' => UploadedFile::fake()->createWithContent('sk.pdf', '%PDF-1.4 dokumen uji')]), ['Accept' => 'application/json'])->assertOk();
        $detail = $this->getJson($this->url($path))->assertOk()->assertJsonPath('data.status', 'aktif')->json('data');
        $this->getJson($this->url($path.'/berkas/'.$detail['berkas']['id']))->assertOk();
        $pc = $this->create($path.'/program', ['nama' => 'Program komite', 'tujuan' => 'Koordinasi program', 'target_hasil' => 'Koordinasi terlaksana', 'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-12-01', 'status' => 'rencana']);
        $this->getJson($this->url($path.'/program/'.$pc['id']))->assertOk();
        $other = $this->create('komite', array_replace($input, ['nama' => 'Periode lain']));
        $this->getJson($this->url('komite/'.$other['id'].'/program/'.$pc['id']))->assertNotFound();
        $this->getJson($this->url('komite/'.$other['id'].'/program/referensi?record_id='.$pc['id']))->assertNotFound();
        $this->patchJson($this->url($path), array_replace($input, ['versi' => 1, 'metode' => 'tetap', 'pengurus' => [], 'catatan_perubahan' => 'Pengurus tidak boleh hilang']))->assertUnprocessable();
    }

    public function test_mitra_mou_retry_idempoten_konflik_dan_aktivasi_memerlukan_dokumen(): void
    {
        $input = ['nama' => 'Mitra Sekolah', 'jenis' => 'pendidikan', 'status' => 'aktif', 'token_pembuatan' => (string) Str::uuid()];
        $p = $this->postJson($this->url('mitra'), $input)->assertCreated()->json('data');
        $this->postJson($this->url('mitra'), $input)->assertOk()->assertJsonPath('data.id', $p['id']);
        $this->assertSame(1, MitraHumas::count());
        $base = 'mitra/'.$p['id'].'/mou';
        $body = ['judul' => 'Kolaborasi sekolah', 'bidang' => 'pendidikan', 'ruang_lingkup' => 'Kegiatan bersama', 'ingatkan_hari_sebelum' => 30, 'status' => 'draf', 'token_pembuatan' => (string) Str::uuid()];
        $m = $this->postJson($this->url($base), $body)->assertCreated()->json('data');
        $this->postJson($this->url($base), $body)->assertOk()->assertJsonPath('data.id', $m['id']);
        $edit = array_replace($body, ['status' => 'aktif', 'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2027-06-01', 'versi' => 0]);
        $this->patchJson($this->url($base.'/'.$m['id']), $edit)->assertUnprocessable();
        $edit['dokumen_humas_id'] = $this->dokumen()->id;
        $this->patchJson($this->url($base.'/'.$m['id']), $edit)->assertOk();
        $this->patchJson($this->url($base.'/'.$m['id']), $edit)->assertUnprocessable();
        $this->getJson($this->url($base.'/'.$m['id']))->assertOk()->assertJsonPath('data.status', 'aktif');
        $this->token($this->akun('wakil_pimpinan_humas'));
        $this->postJson($this->url('mitra'), $input)->assertForbidden();
        $this->postJson($this->url($base), $body)->assertForbidden();
    }

    public function test_butir_bukti_akreditasi_periksa_siap_dan_kunci_revisi(): void
    {
        $p = $this->create('akreditasi', ['nama' => 'Portofolio Humas', 'instrumen' => 'Instrumen sekolah', 'tahun_pelajaran_id' => $this->tahun->id, 'penanggung_jawab' => 'Wakil Humas']);
        $path = 'akreditasi/'.$p['id'];
        $this->postJson($this->url($path.'/status'), ['versi' => 0, 'status' => 'siap', 'alasan' => 'Belum ada butir bukti'])->assertUnprocessable();
        $b = $this->postJson($this->url($path.'/butir'), ['versi' => 0, 'kode' => 'H-01', 'judul' => 'Hubungan masyarakat', 'urutan' => 1, 'target_bukti' => 1])->assertCreated()->json('data');
        $bp = $path.'/butir/'.$b['id'];
        $this->postJson($this->url($bp.'/periksa'), ['versi' => 1, 'status' => 'terpenuhi', 'catatan_pemeriksaan' => 'Belum boleh terpenuhi'])->assertUnprocessable();
        $doc = $this->dokumen();
        $v = $doc->riwayat()->firstOrFail();
        $this->postJson($this->url($bp.'/bukti'), ['versi' => 1, 'token_pembuatan' => (string) Str::uuid(), 'riwayat_dokumen_humas_id' => $v->id])->assertOk();
        $this->postJson($this->url($bp.'/periksa'), ['versi' => 2, 'status' => 'terpenuhi', 'catatan_pemeriksaan' => 'Bukti sudah diperiksa'])->assertOk();
        $this->postJson($this->url($path.'/status'), ['versi' => 3, 'status' => 'siap', 'alasan' => 'Semua butir memenuhi bukti'])->assertOk();
        $this->getJson($this->url($path))->assertOk()->assertJsonPath('data.status', 'siap')->assertJsonPath('data.hak_akses.dapat_kelola', false);
        $this->patchJson($this->url($bp), ['versi' => 4, 'kode' => 'H-01', 'judul' => 'Ditolak', 'urutan' => 1, 'target_bukti' => 1])->assertUnprocessable();
        $this->getJson($this->url($bp))->assertOk()->assertDontSee('lokasi_file')->assertDontSee('token_pembuatan');
        $this->getJson($this->url($bp.'/bukti/'.$this->getJson($this->url($bp))->json('data.bukti.0.id')))->assertOk();
    }

    public function test_menu_izin_role_dan_fixture_mobile_selaras(): void
    {
        $records = ['program' => $this->create('program', $this->program())['id'], 'komite' => $this->create('komite', $this->komite())['id'],
            'mitra' => $this->create('mitra', ['nama' => 'Mitra Uji', 'jenis' => 'pendidikan', 'status' => 'aktif'])['id'],
            'akreditasi' => $this->create('akreditasi', ['nama' => 'Portofolio Uji', 'instrumen' => 'Instrumen sekolah', 'tahun_pelajaran_id' => $this->tahun->id, 'penanggung_jawab' => 'Humas'])['id']];
        $fixture = [];
        $scopes = ['laporan' => ['program/'.$records['program'].'/laporan', $this->create('program/'.$records['program'].'/laporan', $this->laporan())['id']],
            'program-komite' => ['komite/'.$records['komite'].'/program', $this->create('komite/'.$records['komite'].'/program', ['nama' => 'Program Komite', 'tujuan' => 'Koordinasi', 'target_hasil' => 'Kegiatan terlaksana', 'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-12-01', 'status' => 'rencana'])['id']],
            'mou' => ['mitra/'.$records['mitra'].'/mou', $this->create('mitra/'.$records['mitra'].'/mou', ['judul' => 'MoU Sekolah', 'bidang' => 'pendidikan', 'ruang_lingkup' => 'Kerja sama pembelajaran', 'ingatkan_hari_sebelum' => 30, 'status' => 'draf'])['id']]];
        $b = $this->postJson($this->url('akreditasi/'.$records['akreditasi'].'/butir'), ['versi' => 0, 'kode' => 'H01', 'judul' => 'Butir Bukti', 'urutan' => 1, 'target_bukti' => 1])->assertCreated()->json('data.id');
        $scopes['butir'] = ['akreditasi/'.$records['akreditasi'].'/butir', $b];
        foreach (Peran::where('aktif', true)->pluck('kode') as $role) {
            $u = $this->akun($role);
            $this->token($u);
            $menu = $this->getJson(route('api.v1.menu'))->assertOk()->json('data');
            $c = ['user' => (new PenggunaResource($u))->resolve(request()), 'menu' => $menu, 'records' => []];
            foreach (['program' => ['program_kerja_humas', 'program-kerja-humas'], 'komite' => ['komite_humas', 'komite-humas'], 'mitra' => ['kemitraan_humas', 'kemitraan-humas'], 'akreditasi' => ['akreditasi_humas', 'akreditasi-humas']] as $path => [$permission,$code]) {
                $allowed = $u->memilikiIzin([$permission.'.lihat', $permission.'.kelola']);
                $codes = collect($menu['kelompok'])->flatMap(fn ($g) => array_column($g['items'], 'kode'))->all();
                $this->assertSame($allowed, in_array($code, $codes, true), $role.'/'.$path);
                $ref = $this->getJson($this->url($path.'/referensi'))->assertStatus($allowed ? 200 : 403);
                $list = $this->getJson($this->url($path))->assertStatus($allowed ? 200 : 403);
                $detail = $this->getJson($this->url($path.'/'.$records[$path]))->assertStatus($allowed ? 200 : 403);
                if (! $u->memilikiIzin($permission.'.kelola')) {
                    $this->postJson($this->url($path), [])->assertForbidden();
                }
                if ($allowed) {
                    $c['records'][$path] = ['references' => $ref->json('data'), 'list' => $list->json('data'), 'detail' => $detail->json('data')];
                }
            }
            if (in_array($role, ['administrator', 'wakil_pimpinan_humas', 'pimpinan', 'pegawai', 'kontributor_publikasi'])) {
                foreach ($scopes as $kind => [$base,$id]) {
                    $permission = HumasTahapDuaService::MODUL[$kind][1];
                    if ($u->memilikiIzin([$permission.'.lihat', $permission.'.kelola'])) {
                        $c['records'][$kind] = ['base' => $base, 'references' => $this->getJson($this->url($base.'/referensi'))->assertOk()->json('data'), 'list' => $this->getJson($this->url($base))->assertOk()->json('data'), 'detail' => $this->getJson($this->url($base.'/'.$id))->assertOk()->json('data')];
                    }
                }
                $fixture[$role] = $c;
            }
        }
        foreach (['siswa', 'orang_tua'] as $family) {
            $u = $this->akun('administrator');
            $u->update(['pegawai_id' => null]);
            if ($family === 'siswa') {
                $u->update(['siswa_id' => Siswa::create(['nama_lengkap' => 'Uji Siswa', 'aktif' => true])->id]);
            } else {
                OrangTuaWali::create(['pengguna_id' => $u->id, 'nama_lengkap' => 'Uji Wali']);
            }
            $this->token($u->fresh());
            foreach (array_keys($records) as $path) {
                $this->getJson($this->url($path))->assertForbidden();
                $this->postJson($this->url($path), [])->assertForbidden();
            }
        }
        $this->token($this->humas);
        $this->getJson($this->url('program?halaman=0'))->assertUnprocessable();
        $this->getJson($this->url('program?per_halaman=51'))->assertUnprocessable();
        if (getenv('NUSA_CAPTURE_HUMAS_MOBILE') === '1') {
            File::put(base_path('mobile/test/fixtures/humas_native_tahap_dua.json'), json_encode($fixture, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        }
    }

    public function test_izin_sumber_membatasi_referensi_lampiran_dan_agenda(): void
    {
        $komite = $this->create('komite', $this->komite());
        $mitra = $this->create('mitra', ['nama' => 'Mitra uji', 'jenis' => 'pendidikan', 'status' => 'aktif']);
        $mou = $this->create('mitra/'.$mitra['id'].'/mou', ['judul' => 'MoU uji', 'bidang' => 'pendidikan', 'ruang_lingkup' => 'Kerja sama', 'ingatkan_hari_sebelum' => 30, 'status' => 'draf', 'dokumen_humas_id' => $this->dokumen()->id]);
        $agenda = AgendaHumas::create(['judul' => 'Agenda privat sumber', 'jenis' => 'kemitraan', 'waktu_mulai' => now(), 'waktu_selesai' => now()->addHour(), 'tempat' => 'Aula', 'topik' => 'Koordinasi', 'status' => 'terjadwal']);
        $u = $this->akun('pegawai');
        $role = Peran::create(['kode' => 'uji_tahap2_terbatas', 'nama' => 'Uji tanpa akses sumber', 'aktif' => true]);
        $role->izin()->attach(Izin::whereIn('kode', ['komite_humas.lihat', 'kemitraan_humas.kelola'])->pluck('id'));
        $u->daftarPeran()->sync([$role->id]);
        $this->token($u->fresh());
        $this->getJson($this->url('komite/'.$komite['id']))->assertOk()->assertJsonMissingPath('data.pengurus.0.nomor_telepon');
        $this->getJson($this->url('mitra/referensi'))->assertOk()->assertJsonMissingPath('data.agenda')->assertJsonMissingPath('data.dokumen');
        $this->getJson($this->url('mitra/'.$mitra['id'].'/mou/'.$mou['id']))->assertOk()->assertJsonMissingPath('data.dokumen_humas_id')->assertJsonMissingPath('data.dokumen');
        $this->getJson($this->url('mitra/'.$mitra['id'].'/agenda'))->assertForbidden();
        $this->postJson($this->url('mitra/'.$mitra['id'].'/agenda'), ['agenda_humas_id' => $agenda->id])->assertForbidden();
        $this->getJson($this->url('kemitraan/'.$mou['id'].'/pendukung'))->assertForbidden();
    }

    public function test_agenda_terkait_mengikuti_cakupan_dan_lepas_tidak_menghapus_agenda(): void
    {
        $m = $this->create('mitra', ['nama' => 'Mitra program', 'jenis' => 'pendidikan', 'status' => 'aktif']);
        $path = 'mitra/'.$m['id'].'/agenda';
        $a = AgendaHumas::create(['judul' => 'Pertemuan mitra', 'jenis' => 'kemitraan', 'waktu_mulai' => now(), 'waktu_selesai' => now()->addHour(), 'tempat' => 'Aula', 'topik' => 'Koordinasi', 'status' => 'terjadwal']);
        $this->postJson($this->url($path), ['agenda_humas_id' => $a->id])->assertOk();
        $this->getJson($this->url($path))->assertOk()->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.id', $a->id);
        $this->deleteJson($this->url($path.'/'.$a->id))->assertOk();
        $this->getJson($this->url($path))->assertJsonCount(0, 'data.items');
        $this->assertNotNull($a->fresh());
    }

    public function test_cetak_api_identik_dengan_web_tanpa_mengubah_template(): void
    {
        $p = $this->create('program', $this->program());
        $l = $this->create('program/'.$p['id'].'/laporan', $this->laporan());
        $a = $this->create('akreditasi', ['nama' => 'Portofolio cetak', 'instrumen' => 'Instrumen sekolah', 'tahun_pelajaran_id' => $this->tahun->id, 'penanggung_jawab' => 'Humas']);
        foreach (['administrator', 'wakil_pimpinan_humas', 'pimpinan'] as $role) {
            $u = $this->akun($role);
            $this->token($u);
            foreach ([
                ['program/'.$p['id'].'/cetak', route('program-kerja-humas.cetak', $p['id'])],
                ['program/'.$p['id'].'/laporan/'.$l['id'].'/cetak', route('program-kerja-humas.laporan.cetak', [$p['id'], $l['id']])],
                ['akreditasi/'.$a['id'].'/cetak', route('akreditasi-humas.cetak', $a['id'])],
            ] as [$api,$web]) {
                $this->token($u);
                $html = $this->getJson($this->url($api))->assertOk()->getContent();
                $this->assertSame($html, $this->actingAs($u)->get($web)->assertOk()->getContent(), $role.'/'.$api);
            }
        }
    }

    public function test_paginasi_filter_dan_referensi_butir_dihapus(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->create('program', array_replace($this->program(), ['nama' => 'Program '.$i]));
        }
        $one = $this->getJson($this->url('program?per_halaman=2&halaman=1'))->assertOk()->json('data.items');
        $two = $this->getJson($this->url('program?per_halaman=2&halaman=2'))->assertOk()->json('data.items');
        $this->assertCount(2, $one);
        $this->assertCount(2, $two);
        $this->assertSame([], array_intersect(array_column($one, 'id'), array_column($two, 'id')));
        $this->getJson($this->url('program?kata_kunci=Program%204'))->assertJsonPath('data.paginasi.total', 1);
        $p = $this->create('akreditasi', ['nama' => 'Portofolio uji', 'instrumen' => 'Instrumen sekolah', 'tahun_pelajaran_id' => $this->tahun->id, 'penanggung_jawab' => 'Humas']);
        $base = 'akreditasi/'.$p['id'].'/butir';
        $b = $this->postJson($this->url($base), ['versi' => 0, 'kode' => 'H01', 'judul' => 'Butir uji', 'urutan' => 1, 'target_bukti' => 1])->assertCreated()->json('data');
        $this->deleteJson($this->url($base.'/'.$b['id']), ['versi' => 1, 'alasan' => 'Butir tidak diperlukan lagi'])->assertOk();
        $this->getJson($this->url($base.'/'.$b['id']))->assertNotFound();
        $this->getJson($this->url($base.'/referensi?record_id='.$b['id']))->assertNotFound();
    }

    private function program(): array
    {
        return ['tahun_pelajaran_id' => $this->tahun->id, 'semester' => 'ganjil', 'bidang' => 'kemitraan', 'nama' => 'Program Humas', 'tujuan' => 'Meningkatkan kemitraan', 'sasaran' => 'Masyarakat sekolah', 'target_hasil' => 'Kerja sama terlaksana', 'target_kegiatan' => 2, 'penanggung_jawab' => 'Wakil Humas', 'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2026-12-01', 'status' => 'rencana'];
    }

    private function laporan(): array
    {
        return ['judul' => 'Pelaksanaan program', 'tanggal_mulai' => '2026-10-01', 'tanggal_selesai' => '2026-10-02', 'tempat' => 'Aula sekolah', 'pelaksana' => 'Tim Humas', 'jumlah_peserta' => 30, 'uraian' => 'Pelaksanaan kegiatan bersama', 'hasil' => 'Kegiatan terlaksana dengan baik'];
    }

    private function komite(): array
    {
        return ['nama' => 'Komite 2026', 'tanggal_mulai' => '2026-09-01', 'tanggal_selesai' => '2029-08-31', 'status' => 'draf', 'metode' => 'tanpa', 'pengurus' => [['nama' => 'Ketua Komite', 'jabatan' => 'ketua', 'nomor_telepon' => '081234567890', 'aktif' => true]]];
    }

    private function dokumen(): DokumenHumas
    {
        $meta = ['lokasi_file' => 'dokumen-humas/'.Str::uuid().'.pdf', 'nama_file_asli' => 'bukti.pdf', 'tipe_file' => 'application/pdf', 'ukuran_file' => 24];
        Storage::disk('local')->put($meta['lokasi_file'], '%PDF-1.4 dokumen uji');
        $d = DokumenHumas::create($meta + ['judul' => 'Bukti program sekolah', 'kategori' => 'komite', 'status' => 'aktif']);
        $d->riwayat()->create($meta + ['versi' => 1, 'diunggah_pada' => now()]);

        return $d;
    }

    private function akun(string $role): Pengguna
    {
        $u = Pengguna::create(['nama' => 'Uji '.$role, 'username' => 'tahap2.'.Str::uuid(), 'kata_sandi' => 'Humas2026!', 'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false, 'pegawai_id' => Pegawai::create(['nama_lengkap' => 'Uji '.$role, 'aktif' => true])->id]);
        $u->daftarPeran()->sync([Peran::where('kode', $role)->value('id')]);

        return $u->fresh();
    }

    private function token(Pengguna $u): void
    {
        auth()->forgetGuards();
        $this->withToken($u->createToken('Pengujian tahap 2', ['mobile'])->plainTextToken);
    }
}
