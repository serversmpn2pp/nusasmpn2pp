<?php

namespace Tests\Feature;

use App\Models\GuruMataPelajaran;
use App\Models\InstrumenSupervisi;
use App\Models\Kelas;
use App\Models\LampiranTindakLanjutSupervisi;
use App\Models\MataPelajaran;
use App\Models\NotifikasiPengguna;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\PenilaianSupervisi;
use App\Models\PraobservasiSupervisi;
use App\Models\ProgramSupervisi;
use App\Models\RiwayatSupervisi;
use App\Models\SesiSupervisi;
use App\Models\TahunPelajaran;
use App\Models\TindakLanjutSupervisi;
use App\Models\VersiInstrumenSupervisi;
use App\Services\Supervisi\AksesSupervisi;
use App\Services\Supervisi\InstrumenSupervisiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class PascaobservasiSupervisiTest extends TestCase
{
    use RefreshDatabase;

    private array $akun;

    private SesiSupervisi $s;

    private string $url;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 25)->setTime(10, 0));
        config(['services.firebase.push_enabled' => false]);
        Storage::fake('local');
        $this->akun['admin'] = Pengguna::where('username', 'administrator')->firstOrFail();
        foreach (['guru', 'supervisor', 'lain'] as $key) {
            $pegawai = Pegawai::create(['nama_lengkap' => 'Guru '.ucfirst($key), 'jenis_pegawai' => 'Guru', 'aktif' => true]);
            $this->akun[$key] = Pengguna::create(['nama' => $pegawai->nama_lengkap, 'username' => 'pasca-'.$key, 'pegawai_id' => $pegawai->id, 'peran' => 'pegawai', 'aktif' => true, 'kata_sandi' => 'test-pass', 'wajib_ganti_kata_sandi' => false]);
        }
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
        $kelas = Kelas::create(['nama' => 'VIII.A', 'tingkat' => 8, 'tahun_pelajaran_id' => $tahun->id, 'aktif' => true]);
        $mapel = MataPelajaran::create(['nama' => 'Ilmu Pengetahuan Alam', 'urutan' => 1, 'aktif' => true]);
        $g = GuruMataPelajaran::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'mata_pelajaran_id' => $mapel->id, 'pegawai_id' => $this->akun['guru']->pegawai_id, 'aktif' => true]);
        $p = ProgramSupervisi::create(['token_pembuatan' => (string) Str::uuid(), 'sidik_pembuatan' => str_repeat('a', 64), 'nama' => 'Pendampingan Semester Ganjil', 'tujuan' => 'Meningkatkan kualitas pembelajaran', 'tahun_pelajaran_id' => $tahun->id, 'semester' => 'ganjil', 'tanggal_mulai' => '2026-10-01', 'tanggal_selesai' => '2026-11-30', 'status' => 'aktif']);
        $v = VersiInstrumenSupervisi::firstOrFail();
        $this->s = SesiSupervisi::create(['token_pembuatan' => (string) Str::uuid(), 'sidik_pembuatan' => str_repeat('b', 64), 'program_supervisi_id' => $p->id, 'versi_instrumen_supervisi_id' => $v->id,
            'guru_mata_pelajaran_id' => $g->id, 'guru_pegawai_id' => $this->akun['guru']->pegawai_id, 'supervisor_pegawai_id' => $this->akun['supervisor']->pegawai_id, 'kelas_id' => $kelas->id,
            'waktu_mulai' => '2026-10-19 08:00:00', 'waktu_selesai' => '2026-10-19 09:00:00', 'tempat' => 'VIII.A', 'status' => 'terjadwal', 'versi' => 2, 'diterbitkan_pada' => now(),
            'konteks' => ['guru' => 'Robi Candra, S.Pd.', 'mapel' => $mapel->nama, 'kelas' => $kelas->nama, 'instrumen' => $v->nama, 'instrumen_nomor' => 1]]);
        PenilaianSupervisi::create(['sesi_supervisi_id' => $this->s->id, 'supervisor_pegawai_id' => $this->s->supervisor_pegawai_id, 'praobservasi_versi' => 2, 'versi' => 2, 'status' => 'final', 'nilai' => [], 'tanggal_observasi' => '2026-10-19', 'kekuatan' => 'Siswa aktif berdiskusi', 'perbaikan' => 'Perkuat asesmen formatif', 'difinalisasi_pada' => now()]);
        PraobservasiSupervisi::create(['sesi_supervisi_id' => $this->s->id, 'versi' => 2, 'status' => 'terkirim', 'isian' => ['topik' => 'Sistem pernapasan manusia'], 'dikirim_pada' => now()]);
        $this->url = '/api/v1/supervisi/sesi/'.$this->s->id;
    }

    private function login(string $key): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($this->akun[$key]->createToken('Pascaobservasi', ['mobile'])->plainTextToken);
    }

    private function c(int $versi = 0): array
    {
        return ['versi_sesi' => 2, 'penilaian_versi' => 2, 'versi' => $versi, 'konfirmasi' => true];
    }

    private function refleksi(bool $terima = true): void
    {
        $this->login('guru');
        $this->putJson($this->url.'/refleksi', [...$this->c(), 'jawaban' => array_fill(0, 7, 'Refleksi guru dengan bukti pembelajaran yang telah diamati.')])->assertOk();
        $this->postJson($this->url.'/refleksi/kirim', $this->c(1))->assertOk();
        $this->login('supervisor');
        if ($terima) {
            $this->postJson($this->url.'/refleksi/terima', [...$this->c(2), 'catatan' => 'Refleksi sudah sesuai temuan observasi.'])->assertOk();
        }
    }

    private function rencana(array $ganti = []): array
    {
        return [...$this->c(), 'refleksi_versi' => 3, 'token_pembuatan' => (string) Str::uuid(), 'temuan' => 'Asesmen formatif perlu dikuatkan',
            'rencana_perbaikan' => 'Menyusun dan menerapkan exit ticket.', 'bentuk_pendampingan' => 'Diskusi dengan supervisor dan telaah perangkat.', 'indikator_keberhasilan' => 'Guru memakai hasil exit ticket untuk memperbaiki pembelajaran.', 'tenggat' => '2026-11-05', ...$ganti];
    }

    private function tugas(bool $terbit = true): TindakLanjutSupervisi
    {
        $this->refleksi();
        $id = $this->postJson($this->url.'/tindak-lanjut', $this->rencana())->assertOk()->json('data.id');
        if ($terbit) {
            $this->postJson($this->url.'/tindak-lanjut/terbitkan', [...$this->c(3), 'rencana' => [['id' => $id, 'versi' => 1]]])->assertOk();
        }

        return TindakLanjutSupervisi::findOrFail($id);
    }

    private function ajukan(TindakLanjutSupervisi $t): void
    {
        $this->login('guru');
        $this->putJson($this->url.'/tindak-lanjut/'.$t->id.'/pelaksanaan', [...$this->c($t->versi), 'tanggal_pelaksanaan' => '2026-10-24', 'laporan_pelaksanaan' => 'Menerapkan exit ticket pada pembelajaran.', 'hasil_pelaksanaan' => 'Kebutuhan penguatan konsep siswa teridentifikasi.'])->assertOk();
        $this->postJson($this->url.'/tindak-lanjut/'.$t->id.'/ajukan', $this->c($t->versi + 1))->assertOk();
    }

    public function test_prasyarat_hasil_final_cakupan_akun_dan_get_tanpa_efek_samping(): void
    {
        $this->getJson($this->url.'/pascaobservasi')->assertUnauthorized();
        $this->login('lain');
        $this->getJson($this->url.'/pascaobservasi')->assertNotFound();
        $this->login('guru');
        $this->getJson($this->url.'/pascaobservasi')->assertOk()->assertJsonPath('data.pascaobservasi.versi', 0)->assertJsonCount(7, 'data.pascaobservasi.pertanyaan');
        $this->assertDatabaseCount('pascaobservasi_supervisi', 0);
        $this->s->penilaian->update(['status' => 'draf']);
        $this->putJson($this->url.'/refleksi', [...$this->c(), 'jawaban' => []])->assertUnprocessable();
        $this->assertDatabaseCount('pascaobservasi_supervisi', 0);
    }

    public function test_draf_refleksi_privat_kirim_dan_pengembalian(): void
    {
        $this->login('guru');
        $this->putJson($this->url.'/refleksi', [...$this->c(), 'jawaban' => ['DRAF PRIVAT REFLEKSI']])->assertOk();
        $this->postJson($this->url.'/refleksi/kirim', $this->c(1))->assertUnprocessable();
        foreach (['supervisor', 'admin'] as $key) {
            $this->login($key);
            $this->getJson($this->url.'/pascaobservasi')->assertOk()->assertJsonPath('data.pascaobservasi.jawaban', null)->assertDontSee('DRAF PRIVAT REFLEKSI');
        }
        $this->login('guru');
        $this->putJson($this->url.'/refleksi', [...$this->c(), 'jawaban' => []])->assertUnprocessable();
        $this->putJson($this->url.'/refleksi', [...$this->c(1), 'jawaban' => array_fill(0, 7, 'Jawaban lengkap dan sesuai pembelajaran.')])->assertOk();
        $this->postJson($this->url.'/refleksi/kirim', $this->c(2))->assertOk();
        $this->putJson($this->url.'/refleksi', [...$this->c(3), 'jawaban' => []])->assertUnprocessable();
        $this->login('supervisor');
        $this->postJson($this->url.'/refleksi/kembalikan', [...$this->c(3), 'catatan' => 'Jelaskan hasil asesmen formatif secara lebih rinci.'])->assertOk();
        $this->login('guru');
        $this->putJson($this->url.'/refleksi', [...$this->c(4), 'jawaban' => array_fill(0, 7, 'Jawaban diperbaiki dengan bukti yang lebih lengkap.')])->assertOk();
        $this->postJson($this->url.'/refleksi/kirim', $this->c(5))->assertOk();
        $this->assertSame(3, NotifikasiPengguna::where('kunci_unik', 'like', 'supervisi:%:refleksi:%')->count());
    }

    public function test_peran_yang_tepat_saja_bisa_menerima_dan_menyusun_tindak_lanjut(): void
    {
        $this->refleksi(false);
        $this->postJson($this->url.'/tindak-lanjut', $this->rencana(['refleksi_versi' => 2]))->assertUnprocessable();
        foreach (['guru', 'admin'] as $key) {
            $this->login($key);
            $this->postJson($this->url.'/refleksi/terima', $this->c(2))->assertForbidden();
            $this->postJson($this->url.'/tindak-lanjut', $this->rencana())->assertForbidden();
        }
        $this->login('supervisor');
        $this->postJson($this->url.'/refleksi/terima', $this->c(2))->assertOk();
        $this->postJson($this->url.'/tindak-lanjut', $this->rencana())->assertOk();
    }

    public function test_draf_rencana_privat_idempotensi_dan_penerbitan_atomik(): void
    {
        $this->refleksi();
        $data = $this->rencana();
        $a = $this->postJson($this->url.'/tindak-lanjut', $data)->assertOk()->json('data.id');
        $this->postJson($this->url.'/tindak-lanjut', $data)->assertOk()->assertJsonPath('data.id', $a);
        $this->postJson($this->url.'/tindak-lanjut', [...$data, 'temuan' => 'Data lain'])->assertUnprocessable();
        $b = $this->postJson($this->url.'/tindak-lanjut', $this->rencana(['tenggat' => null]))->assertOk()->json('data.id');
        $this->postJson($this->url.'/tindak-lanjut/terbitkan', [...$this->c(3), 'rencana' => [['id' => $a, 'versi' => 1], ['id' => $b, 'versi' => 1]]])->assertUnprocessable();
        $this->assertSame(0, TindakLanjutSupervisi::whereNotNull('diterbitkan_pada')->count());
        $this->assertSame(0, NotifikasiPengguna::where('judul', 'Tindak lanjut supervisi ditugaskan')->count());
        foreach (['guru', 'admin'] as $key) {
            $this->login($key);
            $this->getJson($this->url.'/pascaobservasi')->assertOk()->assertJsonCount(0, 'data.pascaobservasi.rencana');
            $this->getJson($this->url.'/tindak-lanjut/'.$a)->assertNotFound();
        }
        $this->login('supervisor');
        $this->postJson($this->url.'/tindak-lanjut/terbitkan', [...$this->c(3), 'rencana' => [['id' => $a, 'versi' => 1]]])->assertOk();
        $this->assertSame(1, NotifikasiPengguna::where('judul', 'Tindak lanjut supervisi ditugaskan')->count());
    }

    public function test_alur_laporan_perbaikan_verifikasi_dan_penutupan(): void
    {
        $t = $this->tugas();
        $this->ajukan($t);
        $this->putJson($this->url.'/tindak-lanjut/'.$t->id.'/pelaksanaan', [...$this->c(4), 'laporan_pelaksanaan' => 'Ubah tanpa izin'])->assertUnprocessable();
        $this->login('supervisor');
        $this->postJson($this->url.'/pascaobservasi/selesaikan', [...$this->c(3), 'catatan' => 'Semua pendampingan sudah dilaksanakan.'])->assertUnprocessable();
        $this->postJson($this->url.'/tindak-lanjut/'.$t->id.'/kembalikan', [...$this->c(4), 'catatan' => 'Lengkapi bukti perubahan pembelajaran.'])->assertOk();
        $t = $t->fresh();
        $this->ajukan($t);
        $this->login('supervisor');
        $this->postJson($this->url.'/tindak-lanjut/'.$t->id.'/verifikasi', [...$this->c(7), 'catatan' => 'Pelaksanaan dan hasil sudah sesuai indikator keberhasilan.'])->assertOk();
        $this->postJson($this->url.'/pascaobservasi/selesaikan', [...$this->c(3), 'catatan' => 'Pendampingan selesai dengan peningkatan asesmen formatif.'])->assertOk()->assertJsonPath('data.status', 'selesai');
        $this->postJson($this->url.'/tindak-lanjut', $this->rencana(['refleksi_versi' => 4]))->assertUnprocessable();
        $this->postJson($this->url.'/pascaobservasi/buka-ulang', [...$this->c(4), 'catatan' => 'Perlu pendampingan tambahan untuk menjaga konsistensi.'])->assertOk();
        $this->postJson($this->url.'/tindak-lanjut/'.$t->id.'/buka-ulang', [...$this->c(8), 'catatan' => 'Perlu memperbaiki bukti hasil pendampingan.'])->assertOk();
        $this->assertDatabaseHas('riwayat_supervisi', ['aksi' => 'Tindak lanjut sebelum dibuka ulang']);
    }

    public function test_tanpa_tindak_lanjut_harus_dikonfirmasi_dengan_kesimpulan(): void
    {
        $this->refleksi();
        $this->postJson($this->url.'/pascaobservasi/selesaikan', [...$this->c(3), 'catatan' => 'Refleksi dan hasil menunjukkan seluruh indikator terpenuhi.'])->assertUnprocessable();
        $this->postJson($this->url.'/pascaobservasi/selesaikan', [...$this->c(3), 'catatan' => 'Refleksi dan hasil menunjukkan seluruh indikator terpenuhi.', 'tanpa_tindak_lanjut' => true])->assertOk();
    }

    public function test_koreksi_hasil_membekukan_pasca_lama_dan_tidak_menghitung_rencana_lama(): void
    {
        $t = $this->tugas();
        $this->s->penilaian->update(['status' => 'draf', 'versi' => 3]);
        $this->login('guru');
        $this->putJson($this->url.'/tindak-lanjut/'.$t->id.'/pelaksanaan', [...$this->c(2), 'laporan_pelaksanaan' => 'Laporan baru'])->assertUnprocessable();
        $this->s->penilaian->update(['status' => 'final', 'versi' => 4]);
        $this->getJson($this->url.'/pascaobservasi')->assertOk()->assertJsonPath('data.pascaobservasi.hasil_terbaru', false)->assertJsonPath('data.pascaobservasi.statistik.rencana', 0);
        $this->actingAs($this->akun['guru']);
        $this->capture('pasca-arsip', route('supervisi.pascaobservasi.show', $this->s));
        $this->app['auth']->forgetGuards();
        $this->login('guru');
        $this->postJson($this->url.'/refleksi/sesuaikan-hasil', [...$this->c(3), 'penilaian_versi' => 4, 'catatan' => 'Sudah meninjau perbaikan hasil observasi.'])->assertOk();
        $this->postJson($this->url.'/refleksi/kirim', [...$this->c(4), 'penilaian_versi' => 4])->assertOk();
        $this->login('supervisor');
        $this->postJson($this->url.'/refleksi/terima', [...$this->c(5), 'penilaian_versi' => 4])->assertOk();
        $this->postJson($this->url.'/tindak-lanjut/'.$t->id.'/batalkan', [...$this->c(2), 'penilaian_versi' => 4, 'catatan' => 'Ubah rencana berdasarkan hasil baru'])->assertUnprocessable();
        $this->postJson($this->url.'/tindak-lanjut', $this->rencana(['penilaian_versi' => 4, 'refleksi_versi' => 6]))->assertOk();
        $this->assertSame(1, RiwayatSupervisi::where('aksi', 'Refleksi sebelum penyesuaian hasil')->count());
    }

    public function test_tanggal_tenggat_laporan_dan_versi_stale_diperiksa(): void
    {
        $t = $this->tugas();
        $this->login('guru');
        $url = $this->url.'/tindak-lanjut/'.$t->id;
        $this->putJson($url.'/pelaksanaan', [...$this->c(1), 'laporan_pelaksanaan' => 'Stale'])->assertUnprocessable();
        foreach (['2026-10-18', '2026-10-26'] as $i => $date) {
            $this->putJson($url.'/pelaksanaan', [...$this->c(2 + $i), 'tanggal_pelaksanaan' => $date, 'laporan_pelaksanaan' => 'Laporan pelaksanaan lengkap', 'hasil_pelaksanaan' => 'Hasil lengkap'])->assertOk();
            $this->postJson($url.'/ajukan', $this->c(3 + $i))->assertUnprocessable();
        }
        $this->s->program->update(['status' => 'diarsipkan']);
        $this->putJson($url.'/pelaksanaan', [...$this->c(4), 'laporan_pelaksanaan' => 'Arsip'])->assertUnprocessable();
    }

    public function test_bukti_laporan_privat_dan_terkunci_setelah_diajukan(): void
    {
        $t = $this->tugas();
        $this->login('guru');
        $url = $this->url.'/tindak-lanjut/'.$t->id;
        $this->post($url.'/lampiran', [...$this->c(2), 'berkas' => UploadedFile::fake()->create('Bukti pelaksanaan.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();
        $f = LampiranTindakLanjutSupervisi::firstOrFail();
        Storage::disk('local')->assertExists($f->lokasi_file);
        $this->get($url.'/lampiran/'.$f->id)->assertOk()->assertDownload('Bukti pelaksanaan.pdf');
        foreach (['supervisor', 'admin', 'lain'] as $key) {
            $this->login($key);
            $this->getJson($url.'/lampiran/'.$f->id)->assertNotFound();
        }
        $this->ajukan($t->fresh());
        $this->deleteJson($url.'/lampiran/'.$f->id, $this->c(5))->assertUnprocessable();
        $this->login('supervisor');
        $this->get($url.'/lampiran/'.$f->id)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->getJson($url)->assertOk()->assertDontSee('lokasi_file');
    }

    public function test_rencana_dan_lampiran_sesi_lain_ditolak(): void
    {
        $t = $this->tugas();
        $lain = $this->s->replicate();
        $lain->token_pembuatan = (string) Str::uuid();
        $lain->save();
        $this->getJson('/api/v1/supervisi/sesi/'.$lain->id.'/tindak-lanjut/'.$t->id)->assertNotFound();
        $this->postJson('/api/v1/supervisi/sesi/'.$lain->id.'/tindak-lanjut/terbitkan', [...$this->c(3), 'rencana' => [['id' => $t->id, 'versi' => 2]]])->assertUnprocessable();
    }

    public function test_laporan_draf_privat_dan_bukti_berbahaya_terlalu_besar_dibatasi(): void
    {
        $t = $this->tugas();
        $this->login('guru');
        $url = $this->url.'/tindak-lanjut/'.$t->id;
        $this->putJson($url.'/pelaksanaan', [...$this->c(2), 'laporan_pelaksanaan' => 'LAPORAN PRIVAT GURU'])->assertOk();
        foreach ([UploadedFile::fake()->create('script.php', 1, 'application/pdf'), UploadedFile::fake()->create('besar.pdf', 10241, 'application/pdf')] as $f) {
            $this->post($url.'/lampiran', [...$this->c(3), 'berkas' => $f], ['Accept' => 'application/json'])->assertUnprocessable();
        }
        for ($i = 0; $i < 5; $i++) {
            $this->post($url.'/lampiran', [...$this->c(3 + $i), 'berkas' => UploadedFile::fake()->create('bukti.pdf', 1, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();
        }
        $this->post($url.'/lampiran', [...$this->c(8), 'berkas' => UploadedFile::fake()->create('lebih.pdf', 1, 'application/pdf')], ['Accept' => 'application/json'])->assertUnprocessable();
        $this->assertCount(5, Storage::disk('local')->allFiles('supervisi-tindak-lanjut'));
        foreach (['supervisor', 'admin'] as $key) {
            $this->login($key);
            $this->getJson($url)->assertOk()->assertDontSee('LAPORAN PRIVAT GURU')->assertJsonCount(0, 'data.rencana.lampiran');
        }
    }

    public function test_bukti_tidak_dapat_melintasi_tugas_atau_folder_dan_transaksi_gagal_dibersihkan(): void
    {
        $t = $this->tugas();
        $this->login('guru');
        $url = $this->url.'/tindak-lanjut/'.$t->id;
        LampiranTindakLanjutSupervisi::creating(fn () => throw new \RuntimeException('Simulasi metadata gagal'));
        $this->post($url.'/lampiran', [...$this->c(2), 'berkas' => UploadedFile::fake()->create('bukti.pdf', 1, 'application/pdf')], ['Accept' => 'application/json'])->assertStatus(500);
        LampiranTindakLanjutSupervisi::flushEventListeners();
        $this->assertSame([], Storage::disk('local')->allFiles('supervisi-tindak-lanjut'));
        $this->post($url.'/lampiran', [...$this->c(2), 'berkas' => UploadedFile::fake()->create('bukti.pdf', 1, 'application/pdf')], ['Accept' => 'application/json'])->assertOk();
        $f = LampiranTindakLanjutSupervisi::firstOrFail();
        $lain = $t->replicate();
        $lain->token_pembuatan = (string) Str::uuid();
        $lain->save();
        $this->getJson($this->url.'/tindak-lanjut/'.$lain->id.'/lampiran/'.$f->id)->assertNotFound();
        Storage::disk('local')->put('dokumen-lain/rahasia.txt', 'Rahasia');
        $f->update(['lokasi_file' => 'dokumen-lain/rahasia.txt']);
        $this->getJson($url.'/lampiran/'.$f->id)->assertNotFound();
    }

    public function test_rencana_lewat_tenggat_tetap_dapat_dilaporkan_dan_draf_menghalangi_penutupan(): void
    {
        $t = $this->tugas();
        $this->travelTo(now()->setDate(2026, 11, 6));
        $this->login('guru');
        $this->getJson($this->url.'/pascaobservasi')->assertOk()->assertJsonPath('data.pascaobservasi.statistik.melewati_tenggat', 1);
        $this->ajukan($t);
        $this->login('supervisor');
        $this->postJson($this->url.'/tindak-lanjut/'.$t->id.'/verifikasi', [...$this->c(4), 'catatan' => 'Pelaksanaan telah sesuai indikator keberhasilan.'])->assertOk();
        $draf = $this->postJson($this->url.'/tindak-lanjut', $this->rencana())->assertOk()->json('data.id');
        $this->postJson($this->url.'/pascaobservasi/selesaikan', [...$this->c(3), 'tanpa_tindak_lanjut' => true, 'catatan' => 'Kesimpulan pendampingan yang sudah diperiksa.'])->assertUnprocessable();
        $this->postJson($this->url.'/tindak-lanjut/'.$draf.'/batalkan', [...$this->c(1), 'catatan' => 'Rencana tambahan tidak diperlukan setelah ditinjau.'])->assertOk();
        $this->postJson($this->url.'/pascaobservasi/selesaikan', [...$this->c(3), 'catatan' => 'Kesimpulan pendampingan yang sudah diperiksa.'])->assertOk();
    }

    public function test_pertanyaan_refleksi_dinamis_versi_instrumen_dan_pembuatan_lama_tetap_idempoten(): void
    {
        $this->login('admin');
        $v = $this->s->instrumen;
        $ctrl = ['versi' => $v->versi, 'konfirmasi' => true, 'alasan' => 'Revisi instrumen pertanyaan refleksi.'];
        $rev = $this->postJson('/api/v1/supervisi/instrumen/'.$v->id.'/revisi', $ctrl)->assertOk()->json('data');
        $payload = ['nama' => $rev['nama'], 'catatan' => 'Refleksi sesuai kebutuhan sekolah', 'skala' => $rev['skala'], 'kelompok' => $rev['kelompok'], 'versi' => 1, 'pertanyaan_refleksi' => ['Apa bukti keberhasilan?', 'Apa dukungan yang diperlukan?']];
        $valid = app(InstrumenSupervisiService::class)->validasi($payload);
        $this->assertCount(2, $valid['pertanyaan_refleksi'], 'Validasi pertanyaan harus mempertahankan isi permintaan.');
        $edited = $this->putJson('/api/v1/supervisi/instrumen/'.$rev['id'], $payload)->assertOk();
        $this->assertCount(2, VersiInstrumenSupervisi::findOrFail($rev['id'])->pertanyaan_refleksi, 'Database revisi harus menyimpan pertanyaan yang baru.');
        $this->assertCount(2, $edited->json('data.pertanyaan_refleksi'), 'Instrumen revisi harus memuat dua pertanyaan.');
        $this->getJson('/api/v1/supervisi/instrumen/'.$v->id)->assertOk()->assertJsonCount(7, 'data.pertanyaan_refleksi');
        $this->putJson('/api/v1/supervisi/instrumen/'.$rev['id'], [...$payload, 'versi' => 2, 'pertanyaan_refleksi' => []])->assertUnprocessable();
        $this->postJson('/api/v1/supervisi/instrumen/'.$rev['id'].'/terbitkan', [...$ctrl, 'versi' => 2])->assertOk();
        $this->s->update(['versi_instrumen_supervisi_id' => $rev['id']]);
        $this->login('guru');
        $response = $this->putJson($this->url.'/refleksi', [...$this->c(), 'jawaban' => ['Pembelajaran lebih aktif.', 'Diskusi dengan rekan sejawat.']])->assertOk();
        $this->assertCount(2, $response->json('data.pertanyaan'), 'Refleksi sesi harus memuat dua pertanyaan dari revisi.');
        $this->postJson($this->url.'/refleksi/kirim', $this->c(1))->assertOk();
        $this->login('admin');
        $lama = collect($payload)->except('versi', 'pertanyaan_refleksi')->all();
        $i = InstrumenSupervisi::create(['nama' => 'Paket lama', 'token_pembuatan' => $token = (string) Str::uuid(), 'pembuat_id' => $this->akun['admin']->id, 'sidik_pembuatan' => app(AksesSupervisi::class)->sidik($lama)]);
        $copy = $v->replicate();
        $copy->instrumen_supervisi_id = $i->id;
        $copy->save();
        $this->postJson('/api/v1/supervisi/instrumen', [...$lama, 'token_pembuatan' => $token])->assertOk()->assertJsonPath('data.id', $copy->id);
    }

    public function test_semua_tampilan_peran_dapat_dirender(): void
    {
        $this->actingAs($this->akun['guru']);
        $this->capture('pasca-guru', route('supervisi.pascaobservasi.show', $this->s));
        $this->app['auth']->forgetGuards();
        $this->refleksi(false);
        $this->actingAs($this->akun['supervisor']);
        $this->capture('pasca-review', route('supervisi.pascaobservasi.show', $this->s));
        $this->app['auth']->forgetGuards();
        $this->login('supervisor');
        $this->postJson($this->url.'/refleksi/terima', $this->c(2))->assertOk();
        $t = TindakLanjutSupervisi::findOrFail($this->postJson($this->url.'/tindak-lanjut', $this->rencana())->assertOk()->json('data.id'));
        $this->actingAs($this->akun['supervisor']);
        $this->capture('pasca-rencana', route('supervisi.pascaobservasi.show', $this->s));
        $this->capture('tindak-draf', route('supervisi.tindak-lanjut.show', [$this->s, $t]));
        $this->app['auth']->forgetGuards();
        $this->login('supervisor');
        $this->postJson($this->url.'/tindak-lanjut/terbitkan', [...$this->c(3), 'rencana' => [['id' => $t->id, 'versi' => 1]]])->assertOk();
        $this->actingAs($this->akun['guru']);
        $this->capture('tindak-guru', route('supervisi.tindak-lanjut.show', [$this->s, $t]));
        $this->app['auth']->forgetGuards();
        $this->ajukan($t->fresh());
        $this->actingAs($this->akun['supervisor']);
        $this->capture('tindak-review', route('supervisi.tindak-lanjut.show', [$this->s, $t]));
        $this->app['auth']->forgetGuards();
        $this->login('supervisor');
        $this->postJson($this->url.'/tindak-lanjut/'.$t->id.'/verifikasi', [...$this->c(4), 'catatan' => 'Hasil pelaksanaan memenuhi indikator keberhasilan.'])->assertOk();
        $this->postJson($this->url.'/pascaobservasi/selesaikan', [...$this->c(3), 'catatan' => 'Pendampingan selesai dan pembelajaran semakin terarah.'])->assertOk();
        $this->actingAs($this->akun['guru']);
        $this->capture('pasca-selesai', route('supervisi.pascaobservasi.show', $this->s));
        $this->capture('tindak-selesai', route('supervisi.tindak-lanjut.show', [$this->s, $t]));
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
