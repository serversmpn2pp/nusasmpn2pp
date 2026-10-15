<?php

namespace Tests\Feature;

use App\Models\GuruMataPelajaran;
use App\Models\Kelas;
use App\Models\LampiranSupervisi;
use App\Models\MataPelajaran;
use App\Models\NotifikasiPengguna;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\PenilaianSupervisi;
use App\Models\RiwayatSupervisi;
use App\Models\SesiSupervisi;
use App\Models\TahunPelajaran;
use App\Models\VersiInstrumenSupervisi;
use App\Services\Supervisi\JadwalSupervisiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class PengisianSupervisiTest extends TestCase
{
    use RefreshDatabase;

    private array $akun;

    private SesiSupervisi $s;

    private string $url;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 20)->setTime(10, 0));
        config(['services.firebase.push_enabled' => false]);
        Storage::fake('local');
        $this->akun['admin'] = Pengguna::where('username', 'administrator')->firstOrFail();
        foreach (['guru', 'supervisor', 'lain'] as $key) {
            $p = Pegawai::create(['nama_lengkap' => 'Guru '.ucfirst($key), 'jenis_pegawai' => 'Guru', 'aktif' => true]);
            $this->akun[$key] = Pengguna::create(['nama' => $p->nama_lengkap, 'username' => 'pengisian-'.$key, 'pegawai_id' => $p->id, 'peran' => 'pegawai', 'aktif' => true, 'kata_sandi' => 'test-pass', 'wajib_ganti_kata_sandi' => false]);
        }
        $t = TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
        $k = Kelas::create(['nama' => 'VIII.A', 'tingkat' => 8, 'tahun_pelajaran_id' => $t->id, 'aktif' => true]);
        $m = MataPelajaran::create(['nama' => 'Ilmu Pengetahuan Alam', 'urutan' => 1, 'aktif' => true]);
        $g = GuruMataPelajaran::create(['tahun_pelajaran_id' => $t->id, 'kelas_id' => $k->id, 'mata_pelajaran_id' => $m->id, 'pegawai_id' => $this->akun['guru']->pegawai_id, 'aktif' => true]);
        $service = app(JadwalSupervisiService::class);
        $program = $service->program($this->akun['admin'], ['token_pembuatan' => (string) Str::uuid(), 'nama' => 'Program supervisi ganjil', 'tujuan' => 'Pendampingan guru secara bertahap.', 'tahun_pelajaran_id' => $t->id, 'semester' => 'ganjil', 'tanggal_mulai' => '2026-10-01', 'tanggal_selesai' => '2026-11-30', 'status' => 'aktif']);
        $this->s = $service->tambah($this->akun['admin'], $program, ['sesi' => [[
            'token_pembuatan' => (string) Str::uuid(), 'guru_mata_pelajaran_id' => $g->id, 'versi_instrumen_supervisi_id' => VersiInstrumenSupervisi::first()->id,
            'supervisor_pegawai_id' => $this->akun['supervisor']->pegawai_id, 'waktu_mulai' => '2026-10-19T08:00', 'waktu_selesai' => '2026-10-19T09:00',
            'tempat' => 'Ruang VIII.A', 'topik' => 'Sistem pernapasan manusia', 'alasan_di_luar_jadwal' => 'Penjadwalan khusus bersama supervisor.',
        ]]])[0];
        $service->tindakan($this->akun['admin'], $program, 'terbitkan', ['sesi' => [['id' => $this->s->id, 'versi' => 1]], 'konfirmasi' => true, 'alasan' => 'Jadwal telah disepakati bersama.']);
        $this->s = $this->s->fresh();
        $this->url = '/api/v1/supervisi/sesi/'.$this->s->id;
    }

    private function login(string $key): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($this->akun[$key]->createToken('Pengisian supervisi', ['mobile'])->plainTextToken);
    }

    private function kontrol(int $versi = 0): array
    {
        return ['versi' => $versi, 'versi_sesi' => $this->s->versi, 'konfirmasi' => true];
    }

    private function isian(): array
    {
        return ['topik' => 'Sistem pernapasan manusia', 'tujuan_pembelajaran' => 'Mengidentifikasi organ dan fungsi pernapasan.', 'jumlah_peserta_didik' => 30,
            'kegiatan_pembelajaran' => 'Mengamati model, diskusi kelompok dan presentasi.', 'rencana_asesmen' => 'Asesmen awal dan rubrik presentasi.'];
    }

    private function kirimPra(): void
    {
        $this->login('guru');
        $this->putJson($this->url.'/praobservasi', [...$this->kontrol(), 'isian' => $this->isian()])->assertOk();
        $this->postJson($this->url.'/praobservasi/kirim', $this->kontrol(1))->assertOk();
    }

    private function nilai(): array
    {
        $nilai = [];
        foreach ($this->s->instrumen->kelompok as $g => $group) {
            foreach ($group['indikator'] as $i => $label) {
                $nilai[] = ['kelompok' => $g, 'indikator' => $i, 'skor' => $group['tahap'] === 'perencanaan' ? 4 : 2, 'catatan' => 'Bukti pengamatan indikator.'];
            }
        }

        return $nilai;
    }

    private function drafNilai(array $ganti = []): void
    {
        $this->kirimPra();
        $this->login('supervisor');
        $this->putJson($this->url.'/penilaian', [...$this->kontrol(), 'nilai' => $this->nilai(), 'tanggal_observasi' => '2026-10-19', 'kekuatan' => 'Siswa aktif berdiskusi.', 'perbaikan' => 'Perkuat asesmen formatif.', ...$ganti])->assertOk();
    }

    private function finalisasi(): void
    {
        $this->drafNilai();
        $this->postJson($this->url.'/penilaian/finalisasi', $this->kontrol(1))->assertOk();
    }

    public function test_get_tidak_membuat_data_dan_draf_guru_tetap_privat(): void
    {
        $this->login('guru');
        $this->getJson($this->url.'/praobservasi')->assertOk()->assertJsonPath('data.praobservasi.versi', 0);
        $this->assertDatabaseCount('praobservasi_supervisi', 0);
        $this->putJson($this->url.'/praobservasi', [...$this->kontrol(), 'isian' => ['topik' => 'DRAF RAHASIA GURU']])->assertOk();
        foreach (['supervisor', 'admin'] as $key) {
            $this->login($key);
            $this->getJson($this->url.'/praobservasi')->assertOk()->assertJsonPath('data.praobservasi.isian', null)->assertDontSee('DRAF RAHASIA GURU');
            $this->getJson($this->url)->assertOk()->assertDontSee('DRAF RAHASIA GURU');
        }
        $this->login('lain');
        $this->getJson($this->url.'/praobservasi')->assertNotFound();
        $this->getJson($this->url.'/penilaian')->assertNotFound();
    }

    public function test_prasyarat_peran_dan_versi_diperiksa_di_server(): void
    {
        foreach (['admin', 'supervisor'] as $key) {
            $this->login($key);
            $this->putJson($this->url.'/praobservasi', [...$this->kontrol(), 'isian' => $this->isian()])->assertForbidden();
        }
        $this->login('guru');
        $this->putJson($this->url.'/penilaian', [...$this->kontrol(), 'nilai' => []])->assertForbidden();
        $this->postJson($this->url.'/praobservasi/kirim', $this->kontrol())->assertUnprocessable();
        $this->putJson($this->url.'/praobservasi', [...$this->kontrol(), 'versi_sesi' => 1, 'isian' => $this->isian()])->assertUnprocessable();
        $this->putJson($this->url.'/praobservasi', [...$this->kontrol(), 'isian' => []])->assertOk();
        $this->postJson($this->url.'/praobservasi/kirim', $this->kontrol(1))->assertUnprocessable();
        $this->putJson($this->url.'/praobservasi', [...$this->kontrol(), 'isian' => $this->isian()])->assertUnprocessable();
        $this->login('supervisor');
        $this->putJson($this->url.'/penilaian', [...$this->kontrol(), 'nilai' => []])->assertUnprocessable();
        $this->assertDatabaseCount('penilaian_supervisi', 0);
    }

    public function test_kirim_kembalikan_perbaiki_kirim_ulang_dan_notifikasi(): void
    {
        $this->kirimPra();
        $this->putJson($this->url.'/praobservasi', [...$this->kontrol(2), 'isian' => $this->isian()])->assertUnprocessable();
        $this->login('supervisor');
        $this->getJson($this->url.'/praobservasi')->assertOk()->assertJsonPath('data.praobservasi.isian.jumlah_peserta_didik', 30);
        $this->postJson($this->url.'/praobservasi/kembalikan', [...$this->kontrol(2), 'catatan' => 'Lengkapi media pembelajaran.'])->assertOk()->assertJsonPath('data.status', 'dikembalikan');
        $this->login('guru');
        $this->putJson($this->url.'/praobservasi', [...$this->kontrol(3), 'isian' => [...$this->isian(), 'media_sumber_belajar' => 'Model organ dan video pembelajaran.']])->assertOk();
        $this->postJson($this->url.'/praobservasi/kirim', $this->kontrol(4))->assertOk();
        $this->assertSame(2, NotifikasiPengguna::where('judul', 'Praobservasi siap ditinjau')->count());
        $this->assertSame(1, NotifikasiPengguna::where('judul', 'Praobservasi perlu perbaikan')->where('pengguna_id', $this->akun['guru']->id)->count());
        $this->assertDatabaseHas('praobservasi_supervisi', ['status' => 'terkirim', 'versi' => 5]);
    }

    public function test_nol_berbeda_dari_kosong_dan_hasil_belum_lengkap_bukan_nilai_nol(): void
    {
        $this->drafNilai(['nilai' => [['kelompok' => 0, 'indikator' => 0, 'skor' => 0], ['kelompok' => 0, 'indikator' => 1, 'skor' => null]]]);
        $this->getJson($this->url.'/penilaian')->assertOk()->assertJsonPath('data.penilaian.ringkasan.tahap.perencanaan.terisi', 1)
            ->assertJsonPath('data.penilaian.ringkasan.tahap.perencanaan.nilai', null)->assertJsonPath('data.penilaian.ringkasan.nilai_akhir', null);
        $this->postJson($this->url.'/penilaian/finalisasi', $this->kontrol(1))->assertUnprocessable();
        $this->login('guru');
        $this->getJson($this->url.'/penilaian')->assertOk()->assertJsonMissingPath('data.penilaian.nilai')->assertJsonMissingPath('data.penilaian.ringkasan');
    }

    public function test_nilai_akhir_adalah_rata_rata_normalisasi_bukan_total_mentah(): void
    {
        $this->drafNilai();
        $this->getJson($this->url.'/penilaian')->assertOk()
            ->assertJsonPath('data.penilaian.ringkasan.tahap.perencanaan.nilai', 100)
            ->assertJsonPath('data.penilaian.ringkasan.tahap.pelaksanaan.nilai', 50)
            ->assertJsonPath('data.penilaian.ringkasan.nilai_akhir', 75)
            ->assertJsonPath('data.penilaian.ringkasan.kategori', 'Cukup');
        $this->postJson($this->url.'/penilaian/finalisasi', $this->kontrol(1))->assertOk()->assertJsonPath('data.status', 'final');
        $this->login('guru');
        $this->getJson($this->url.'/penilaian')->assertOk()->assertJsonPath('data.penilaian.ringkasan.nilai_akhir', 75);
        $this->assertDatabaseHas('notifikasi_pengguna', ['pengguna_id' => $this->akun['guru']->id, 'judul' => 'Hasil supervisi tersedia']);
    }

    public function test_indikator_asing_duplikat_desimal_dan_skor_di_luar_skala_ditolak(): void
    {
        $this->kirimPra();
        $this->login('supervisor');
        foreach ([[['kelompok' => 99, 'indikator' => 0, 'skor' => 4]], [['kelompok' => 0, 'indikator' => 0, 'skor' => 5]], [['kelompok' => 0, 'indikator' => 0, 'skor' => 2.5]], [['kelompok' => 0, 'indikator' => 0, 'skor' => 1], ['kelompok' => 0, 'indikator' => 0, 'skor' => 2]]] as $rows) {
            $this->putJson($this->url.'/penilaian', [...$this->kontrol(), 'nilai' => $rows])->assertUnprocessable();
        }
        $this->assertDatabaseCount('penilaian_supervisi', 0);
    }

    public function test_finalisasi_memerlukan_tanggal_catatan_dan_seluruh_indikator(): void
    {
        $this->drafNilai(['tanggal_observasi' => null]);
        $this->postJson($this->url.'/penilaian/finalisasi', $this->kontrol(1))->assertUnprocessable();
        $versi = 1;
        foreach (['2026-10-18', '2026-10-21', '2026-12-01'] as $tanggal) {
            $this->putJson($this->url.'/penilaian', [...$this->kontrol($versi), 'nilai' => $this->nilai(), 'tanggal_observasi' => $tanggal, 'kekuatan' => 'Kekuatan lengkap', 'perbaikan' => 'Perbaikan lengkap'])->assertOk();
            $this->postJson($this->url.'/penilaian/finalisasi', $this->kontrol(++$versi))->assertUnprocessable();
        }
        $this->putJson($this->url.'/penilaian', [...$this->kontrol($versi), 'nilai' => $this->nilai(), 'tanggal_observasi' => '2026-10-19'])->assertOk();
        $this->postJson($this->url.'/penilaian/finalisasi', $this->kontrol(++$versi))->assertUnprocessable();
        $this->assertSame('draf', PenilaianSupervisi::first()->status);
    }

    public function test_hasil_final_dikunci_koreksi_hanya_pengelola_dan_riwayat_utuh(): void
    {
        $this->finalisasi();
        $this->putJson($this->url.'/penilaian', [...$this->kontrol(2), 'nilai' => []])->assertUnprocessable();
        $this->postJson($this->url.'/penilaian/buka-ulang', [...$this->kontrol(2), 'alasan' => 'Koreksi catatan pengamatan.'])->assertForbidden();
        $this->login('admin');
        $this->putJson($this->url, [...$this->kontrol(), 'versi' => $this->s->versi, 'alasan' => 'Ubah jadwal setelah selesai.', 'supervisor_pegawai_id' => $this->s->supervisor_pegawai_id, 'waktu_mulai' => '2026-10-19T08:00', 'waktu_selesai' => '2026-10-19T09:00', 'tempat' => 'Kelas', 'alasan_di_luar_jadwal' => 'Penjadwalan khusus supervisi.'])->assertUnprocessable();
        $this->postJson('/api/v1/supervisi/program/'.$this->s->program_supervisi_id.'/jadwal/batalkan', ['sesi' => [['id' => $this->s->id, 'versi' => $this->s->versi]], 'konfirmasi' => true, 'alasan' => 'Batalkan setelah hasil selesai.'])->assertUnprocessable();
        $this->postJson($this->url.'/penilaian/buka-ulang', [...$this->kontrol(2), 'alasan' => 'Koreksi catatan pengamatan.'])->assertOk();
        $salinan = RiwayatSupervisi::where('aksi', 'Hasil final sebelum koreksi')->firstOrFail()->salinan;
        $this->assertSame('final', $salinan['status']);
        $this->assertCount(79, $salinan['nilai']);
        $this->login('guru');
        $this->getJson($this->url.'/penilaian')->assertOk()->assertJsonMissingPath('data.penilaian.nilai');
        $this->login('supervisor');
        $this->postJson($this->url.'/penilaian/finalisasi', $this->kontrol(3))->assertOk();
        $this->assertSame(2, NotifikasiPengguna::where('judul', 'Hasil supervisi tersedia')->count());
    }

    public function test_penilaian_tidak_bisa_dialihkan_ke_supervisor_baru(): void
    {
        $this->drafNilai();
        $this->login('admin');
        $this->putJson($this->url, ['versi' => $this->s->versi, 'alasan' => 'Pengawas perlu diganti segera.', 'supervisor_pegawai_id' => $this->akun['lain']->pegawai_id, 'waktu_mulai' => '2026-10-19T08:00', 'waktu_selesai' => '2026-10-19T09:00', 'tempat' => 'Kelas', 'alasan_di_luar_jadwal' => 'Penjadwalan khusus supervisi.'])->assertUnprocessable();
        $this->assertSame($this->s->supervisor_pegawai_id, $this->s->fresh()->supervisor_pegawai_id);
    }

    public function test_perubahan_praobservasi_memerlukan_tinjauan_ulang_sebelum_finalisasi(): void
    {
        $this->drafNilai();
        $this->postJson($this->url.'/praobservasi/kembalikan', [...$this->kontrol(2), 'catatan' => 'Lengkapi strategi asesmen formatif.'])->assertOk();
        $this->login('guru');
        $this->putJson($this->url.'/praobservasi', [...$this->kontrol(3), 'isian' => [...$this->isian(), 'rencana_asesmen' => 'Asesmen formatif diperbaiki.']])->assertOk();
        $this->postJson($this->url.'/praobservasi/kirim', $this->kontrol(4))->assertOk();
        $this->login('supervisor');
        $this->postJson($this->url.'/penilaian/finalisasi', $this->kontrol(1))->assertUnprocessable();
        $this->putJson($this->url.'/penilaian', [...$this->kontrol(1), 'nilai' => $this->nilai(), 'tanggal_observasi' => '2026-10-19', 'kekuatan' => 'Siswa aktif', 'perbaikan' => 'Perbaiki asesmen'])->assertOk()->assertJsonPath('data.praobservasi_versi', 5);
        $this->postJson($this->url.'/penilaian/finalisasi', $this->kontrol(2))->assertOk();
    }

    public function test_arsip_pembatalan_dan_versi_penilaian_stale_ditolak(): void
    {
        $this->drafNilai();
        $this->putJson($this->url.'/penilaian', [...$this->kontrol(), 'nilai' => []])->assertUnprocessable();
        $this->s->program->update(['status' => 'diarsipkan']);
        $this->postJson($this->url.'/penilaian/finalisasi', $this->kontrol(1))->assertUnprocessable();
        $this->s->program->update(['status' => 'aktif']);
        $this->s->update(['status' => 'dibatalkan']);
        $this->postJson($this->url.'/penilaian/finalisasi', $this->kontrol(1))->assertUnprocessable();
    }

    public function test_lampiran_privat_dan_tidak_bisa_diubah_setelah_dikirim(): void
    {
        $this->login('guru');
        $this->post($this->url.'/lampiran', [...$this->kontrol(), 'berkas' => UploadedFile::fake()->create('Modul ajar.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();
        $f = LampiranSupervisi::firstOrFail();
        Storage::disk('local')->assertExists($f->lokasi_file);
        $this->getJson($this->url.'/praobservasi')->assertOk()->assertDontSee('lokasi_file')->assertJsonCount(1, 'data.praobservasi.lampiran');
        $this->get($this->url.'/lampiran/'.$f->id)->assertOk()->assertDownload('Modul ajar.pdf');
        foreach (['supervisor', 'admin', 'lain'] as $key) {
            $this->login($key);
            $this->getJson($this->url.'/lampiran/'.$f->id)->assertNotFound();
        }
        $this->login('guru');
        $this->putJson($this->url.'/praobservasi', [...$this->kontrol(1), 'isian' => $this->isian()])->assertOk();
        $this->postJson($this->url.'/praobservasi/kirim', $this->kontrol(2))->assertOk();
        $this->deleteJson($this->url.'/lampiran/'.$f->id, $this->kontrol(3))->assertUnprocessable();
        $this->login('supervisor');
        $this->get($this->url.'/lampiran/'.$f->id)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_lampiran_berbahaya_terlalu_besar_batas_lima_dan_stale_ditolak(): void
    {
        $this->login('guru');
        foreach ([UploadedFile::fake()->create('script.php', 1, 'application/pdf'), UploadedFile::fake()->create('besar.pdf', 10241, 'application/pdf')] as $f) {
            $this->post($this->url.'/lampiran', [...$this->kontrol(), 'berkas' => $f], ['Accept' => 'application/json'])->assertUnprocessable();
        }
        for ($i = 0; $i < 5; $i++) {
            $this->post($this->url.'/lampiran', [...$this->kontrol($i), 'berkas' => UploadedFile::fake()->create('modul.pdf', 1, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();
        }
        $this->post($this->url.'/lampiran', [...$this->kontrol(), 'berkas' => UploadedFile::fake()->create('stale.pdf', 1, 'application/pdf')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->post($this->url.'/lampiran', [...$this->kontrol(5), 'berkas' => UploadedFile::fake()->create('lebih.pdf', 1, 'application/pdf')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertCount(5, Storage::disk('local')->allFiles('supervisi'));
        $this->assertDatabaseCount('lampiran_supervisi', 5);
    }

    public function test_gagal_transaksi_membersihkan_file_baru(): void
    {
        LampiranSupervisi::creating(fn () => throw new RuntimeException('Simulasi penyimpanan metadata gagal'));
        $this->login('guru');
        $this->post($this->url.'/lampiran', [...$this->kontrol(), 'berkas' => UploadedFile::fake()->create('modul.pdf', 1, 'application/pdf')], ['Accept' => 'application/json'])->assertStatus(500);
        LampiranSupervisi::flushEventListeners();
        $this->assertSame([], Storage::disk('local')->allFiles('supervisi'));
        $this->assertDatabaseCount('lampiran_supervisi', 0);
        $this->assertDatabaseCount('praobservasi_supervisi', 0);
    }

    public function test_skala_dan_jumlah_indikator_dinamis_mengikuti_versi_jadwal(): void
    {
        $v = $this->s->instrumen->replicate();
        $v->nomor = 2;
        $v->skala = [['skor' => 0, 'label' => 'Tidak ada'], ['skor' => 1, 'label' => 'Cukup'], ['skor' => 2, 'label' => 'Baik']];
        $v->kelompok = [['tahap' => 'perencanaan', 'nama' => 'Rencana', 'indikator' => ['A']], ['tahap' => 'pelaksanaan', 'nama' => 'Praktik', 'indikator' => ['B', 'C']]];
        $v->save();
        $this->s->update(['versi_instrumen_supervisi_id' => $v->id]);
        $this->s = $this->s->fresh();
        $this->drafNilai(['nilai' => [['kelompok' => 0, 'indikator' => 0, 'skor' => 2], ['kelompok' => 1, 'indikator' => 0, 'skor' => 0], ['kelompok' => 1, 'indikator' => 1, 'skor' => 2]]]);
        $this->getJson($this->url.'/penilaian')->assertOk()->assertJsonPath('data.penilaian.ringkasan.nilai_akhir', 75)->assertJsonPath('data.penilaian.ringkasan.tahap.pelaksanaan.skor_maksimal', 4);
    }

    public function test_formulir_web_json_menyimpan_500_indikator_tanpa_terpotong(): void
    {
        $v = $this->s->instrumen->replicate();
        $v->nomor = 2;
        $v->kelompok = array_map(fn ($g) => ['tahap' => $g < 2 ? 'perencanaan' : 'pelaksanaan', 'nama' => 'Kelompok '.($g + 1), 'indikator' => array_fill(0, 125, 'Indikator dinamis')], range(0, 3));
        $v->save();
        $this->s->update(['versi_instrumen_supervisi_id' => $v->id]);
        $this->s = $this->s->fresh();
        $this->kirimPra();
        $this->actingAs($this->akun['supervisor']);
        $url = route('supervisi.penilaian.update', $this->s);
        $this->put($url, [...$this->kontrol(), 'nilai_json' => json_encode($this->nilai()), 'tanggal_observasi' => '2026-10-19', 'kekuatan' => 'Kekuatan lengkap', 'perbaikan' => 'Perbaikan lengkap'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertCount(500, PenilaianSupervisi::firstOrFail()->nilai);
        $this->put($url, [...$this->kontrol(1), 'nilai_json' => '{invalid'])->assertSessionHasErrors('nilai');
        $this->put($url, [...$this->kontrol(1), 'nilai_json' => 'null'])->assertSessionHasErrors('nilai');
        $this->assertCount(500, PenilaianSupervisi::firstOrFail()->nilai);
    }

    public function test_seluruh_skor_nol_terhitung_sebagai_hasil_sah_dan_pembulatan_dua_desimal(): void
    {
        $this->drafNilai(['nilai' => array_map(fn ($r) => [...$r, 'skor' => 0], $this->nilai())]);
        $this->getJson($this->url.'/penilaian')->assertOk()->assertJsonPath('data.penilaian.ringkasan.lengkap', true)->assertJsonPath('data.penilaian.ringkasan.nilai_akhir', 0)->assertJsonPath('data.penilaian.ringkasan.kategori', 'Perlu Pembinaan');
        $this->putJson($this->url.'/penilaian', [...$this->kontrol(1), 'nilai' => array_map(fn ($r) => [...$r, 'skor' => 1], $this->nilai())])->assertOk()->assertJsonPath('data.ringkasan.nilai_akhir', 25);
        $rows = array_map(fn ($r) => [...$r, 'skor' => 0], $this->nilai());
        $rows[0]['skor'] = 1;
        $this->putJson($this->url.'/penilaian', [...$this->kontrol(2), 'nilai' => $rows])->assertOk()->assertJsonPath('data.ringkasan.tahap.perencanaan.nilai', 0.83)->assertJsonPath('data.ringkasan.nilai_akhir', 0.42);
    }

    public function test_lampiran_sesi_lain_dan_lokasi_di_luar_folder_tidak_dapat_diunduh(): void
    {
        $this->login('guru');
        $this->post($this->url.'/lampiran', [...$this->kontrol(), 'berkas' => UploadedFile::fake()->create('modul.pdf', 1, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();
        $f = LampiranSupervisi::firstOrFail();
        $lain = $this->s->replicate();
        $lain->token_pembuatan = (string) Str::uuid();
        $lain->save();
        $this->getJson('/api/v1/supervisi/sesi/'.$lain->id.'/lampiran/'.$f->id)->assertNotFound();
        Storage::disk('local')->put('dokumen-lain/rahasia.txt', 'Isi rahasia');
        $f->update(['lokasi_file' => 'dokumen-lain/rahasia.txt']);
        $this->getJson($this->url.'/lampiran/'.$f->id)->assertNotFound();
        $this->login('lain');
        $this->deleteJson($this->url.'/lampiran/'.$f->id, $this->kontrol(1))->assertNotFound();
    }

    public function test_halaman_web_untuk_guru_supervisor_dan_hasil_final(): void
    {
        $this->actingAs($this->akun['guru']);
        $this->capture('pra-guru', route('supervisi.praobservasi.show', $this->s));
        $this->capture('nilai-tertutup', route('supervisi.penilaian.show', $this->s));
        $this->app['auth']->forgetGuards();
        $this->drafNilai();
        $this->actingAs($this->akun['supervisor']);
        $this->capture('pra-supervisor', route('supervisi.praobservasi.show', $this->s));
        $this->capture('nilai-supervisor', route('supervisi.penilaian.show', $this->s));
        $this->app['auth']->forgetGuards();
        $this->login('supervisor');
        $this->postJson($this->url.'/penilaian/finalisasi', $this->kontrol(1))->assertOk();
        $this->actingAs($this->akun['guru']);
        $this->capture('nilai-final', route('supervisi.penilaian.show', $this->s));
        $this->actingAs($this->akun['admin']);
        $this->capture('nilai-koreksi', route('supervisi.penilaian.show', $this->s));
    }

    private function capture(string $name, string $url): void
    {
        $r = $this->get($url)->assertOk();
        if (getenv('NUSA_CAPTURE_SUPERVISI') === '1') {
            File::ensureDirectoryExists(storage_path('framework/testing/supervisi'));
            File::put(storage_path('framework/testing/supervisi/'.$name.'.html'), $r->getContent());
        }
    }
}
