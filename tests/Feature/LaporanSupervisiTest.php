<?php

namespace Tests\Feature;

use App\Models\GuruMataPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\NotifikasiPengguna;
use App\Models\PascaobservasiSupervisi;
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
use App\Services\Supervisi\RekapSupervisiService;
use App\Support\PenulisExcelSupervisi;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Tests\TestCase;
use ZipArchive;

class LaporanSupervisiTest extends TestCase
{
    use RefreshDatabase;

    private array $akun;

    private SesiSupervisi $s;

    private string $url;

    private TahunPelajaran $tahun;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 25)->setTime(10, 0));
        config(['services.firebase.push_enabled' => false]);
        $this->akun['admin'] = Pengguna::where('username', 'administrator')->firstOrFail();
        foreach (['guru', 'supervisor', 'lain'] as $key) {
            $p = Pegawai::create(['nama_lengkap' => 'Guru '.ucfirst($key), 'jenis_pegawai' => 'Guru', 'aktif' => true]);
            $this->akun[$key] = Pengguna::create(['nama' => $p->nama_lengkap, 'username' => 'rekap-'.$key, 'pegawai_id' => $p->id, 'peran' => 'pegawai', 'aktif' => true, 'kata_sandi' => 'test-pass', 'wajib_ganti_kata_sandi' => false]);
        }
        $this->tahun = TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
        $k = Kelas::create(['nama' => 'VIII.A', 'tingkat' => 8, 'tahun_pelajaran_id' => $this->tahun->id, 'aktif' => true]);
        $m = MataPelajaran::create(['nama' => 'Ilmu Pengetahuan Alam', 'urutan' => 1, 'aktif' => true]);
        $g = GuruMataPelajaran::create(['tahun_pelajaran_id' => $this->tahun->id, 'kelas_id' => $k->id, 'mata_pelajaran_id' => $m->id, 'pegawai_id' => $this->akun['guru']->pegawai_id, 'aktif' => true]);
        $p = ProgramSupervisi::create(['token_pembuatan' => (string) Str::uuid(), 'sidik_pembuatan' => str_repeat('a', 64), 'nama' => 'Pendampingan Semester Ganjil', 'tujuan' => 'Meningkatkan kualitas pembelajaran.', 'tahun_pelajaran_id' => $this->tahun->id, 'semester' => 'ganjil', 'tanggal_mulai' => '2026-10-01', 'tanggal_selesai' => '2026-11-30', 'status' => 'aktif']);
        $v = VersiInstrumenSupervisi::firstOrFail();
        $this->s = SesiSupervisi::create(['token_pembuatan' => (string) Str::uuid(), 'sidik_pembuatan' => str_repeat('b', 64), 'program_supervisi_id' => $p->id, 'versi_instrumen_supervisi_id' => $v->id,
            'guru_mata_pelajaran_id' => $g->id, 'guru_pegawai_id' => $this->akun['guru']->pegawai_id, 'supervisor_pegawai_id' => $this->akun['supervisor']->pegawai_id, 'kelas_id' => $k->id,
            'waktu_mulai' => '2026-10-19 08:00', 'waktu_selesai' => '2026-10-19 09:00', 'tempat' => 'Ruang VIII.A', 'status' => 'terjadwal', 'versi' => 2, 'diterbitkan_pada' => now(),
            'konteks' => ['guru' => 'Robi Candra, S.Pd.', 'mapel' => $m->nama, 'kelas' => $k->nama, 'instrumen' => $v->nama, 'instrumen_nomor' => 1]]);
        PraobservasiSupervisi::create(['sesi_supervisi_id' => $this->s->id, 'versi' => 2, 'status' => 'terkirim', 'isian' => ['topik' => 'Sistem pernapasan manusia', 'tujuan_pembelajaran' => 'Mengidentifikasi organ pernapasan.', 'jumlah_peserta_didik' => 30, 'kegiatan_pembelajaran' => 'Diskusi kelompok, praktik, dan presentasi.', 'rencana_asesmen' => 'Rubrik dan exit ticket.'], 'dikirim_pada' => now()]);
        $nilai = [];
        foreach ($v->kelompok as $gi => $group) {
            foreach ($group['indikator'] as $i => $label) {
                $nilai[] = ['kelompok' => $gi, 'indikator' => $i, 'skor' => $group['tahap'] === 'perencanaan' ? 4 : 2, 'catatan' => 'Bukti pengamatan indikator.'];
            }
        }
        PenilaianSupervisi::create(['sesi_supervisi_id' => $this->s->id, 'supervisor_pegawai_id' => $this->s->supervisor_pegawai_id, 'praobservasi_versi' => 2, 'versi' => 2, 'status' => 'final', 'nilai' => $nilai, 'tanggal_observasi' => '2026-10-19', 'kekuatan' => 'Siswa aktif berdiskusi.', 'perbaikan' => 'Perkuat asesmen formatif.', 'difinalisasi_pada' => now()]);
        $this->url = '/api/v1/supervisi';
    }

    private function login(string $key): void
    {
        $this->app['auth']->forgetGuards();
        $this->withToken($this->akun[$key]->createToken('Rekap', ['mobile'])->plainTextToken);
    }

    private function salin(array $ganti = [], bool $hasil = true): SesiSupervisi
    {
        $s = $this->s->replicate();
        $s->fill(['token_pembuatan' => (string) Str::uuid(), ...$ganti])->save();
        if ($hasil) {
            $pra = $this->s->praobservasi->replicate();
            $pra->sesi_supervisi_id = $s->id;
            $pra->save();
            $nilai = $this->s->penilaian->replicate();
            $nilai->sesi_supervisi_id = $s->id;
            $nilai->save();
        }

        return $s;
    }

    private function refleksi(string $status = 'diterima', int $epoch = 2): PascaobservasiSupervisi
    {
        return PascaobservasiSupervisi::create(['sesi_supervisi_id' => $this->s->id, 'penilaian_versi' => $epoch, 'versi' => 3, 'status' => $status,
            'pertanyaan' => $this->s->instrumen->pertanyaan_refleksi, 'jawaban' => array_fill(0, 7, 'Refleksi berdasarkan bukti pembelajaran.'), 'catatan_supervisor' => 'Gunakan hasil asesmen untuk perbaikan.', 'dikirim_pada' => now(), 'diterima_pada' => now()]);
    }

    private function tugas(array $ganti = []): TindakLanjutSupervisi
    {
        return TindakLanjutSupervisi::create(['token_pembuatan' => (string) Str::uuid(), 'sidik_pembuatan' => str_repeat('c', 64), 'sesi_supervisi_id' => $this->s->id, 'penilaian_versi' => 2, 'refleksi_versi' => 3, 'status' => 'ditugaskan', 'temuan' => 'Asesmen formatif perlu dikuatkan.', 'rencana_perbaikan' => 'Menerapkan exit ticket.', 'bentuk_pendampingan' => 'Diskusi dan telaah perangkat.', 'indikator_keberhasilan' => 'Pembelajaran disesuaikan hasil exit ticket.', 'tenggat' => '2026-10-24', 'diterbitkan_pada' => now(), ...$ganti]);
    }

    public function test_rekap_terhitung_normalisasi_dan_tanpa_efek_samping(): void
    {
        $this->login('admin');
        $before = [$this->s->fresh()->getAttributes(), PenilaianSupervisi::first()->getAttributes(), RiwayatSupervisi::count(), NotifikasiPengguna::count()];
        $this->getJson($this->url.'/rekap')->assertOk()->assertJsonPath('data.statistik.hasil_final', 1)->assertJsonPath('data.statistik.rata_rata', 75)
            ->assertJsonPath('data.daftar.data.0.nilai_perencanaan', 100)->assertJsonPath('data.daftar.data.0.nilai_pelaksanaan', 50)->assertJsonPath('data.daftar.data.0.nilai_akhir', 75)->assertJsonPath('data.daftar.data.0.tahap', 'menunggu_refleksi')->assertJsonMissingPath('data.semua');
        $this->getJson($this->url.'/sesi/'.$this->s->id.'/laporan')->assertOk()->assertJsonPath('data.penilaian.ringkasan.nilai_akhir', 75)->assertHeader('Cache-Control', 'max-age=0, no-store, private');
        $this->assertSame($before, [$this->s->fresh()->getAttributes(), PenilaianSupervisi::first()->getAttributes(), RiwayatSupervisi::count(), NotifikasiPengguna::count()]);
    }

    public function test_draf_hasil_tidak_dihitung_atau_dicetak_termasuk_oleh_supervisor(): void
    {
        $this->s->penilaian->update(['status' => 'draf', 'kekuatan' => 'RAHASIA DRAF NILAI']);
        foreach (['guru', 'supervisor', 'admin'] as $key) {
            $this->login($key);
            $this->getJson($this->url.'/rekap')->assertOk()->assertJsonPath('data.statistik.hasil_final', 0)->assertJsonPath('data.statistik.rata_rata', null)->assertJsonPath('data.daftar.data.0.nilai_akhir', null)->assertDontSee('RAHASIA DRAF NILAI');
            $this->getJson($this->url.'/sesi/'.$this->s->id.'/laporan')->assertUnprocessable();
            $this->getJson($this->url.'/sesi/'.$this->s->id.'/laporan/cetak')->assertUnprocessable();
        }
    }

    public function test_kosong_bukan_nol_skor_nol_sah_dan_rerata_hanya_final(): void
    {
        $s = $this->salin();
        $s->penilaian->update(['nilai' => array_map(fn ($n) => [...$n, 'skor' => 0], $s->penilaian->nilai)]);
        $this->salin([], false);
        $d = app(RekapSupervisiService::class)->bangun($this->akun['admin'], []);
        $this->assertEquals(37.5, $d['statistik']['rata_rata']);
        $this->assertSame(2, $d['statistik']['hasil_final']);
        $this->assertEquals(0, $d['semua']->firstWhere('id', $s->id)['nilai_akhir']);
    }

    public function test_hasil_tidak_lengkap_atau_acuan_pra_lama_tidak_dapat_dicetak(): void
    {
        $this->login('admin');
        $p = $this->s->penilaian;
        $p->update(['praobservasi_versi' => 1]);
        $this->getJson($this->url.'/rekap')->assertJsonPath('data.daftar.data.0.tahap', 'perlu_periksa');
        $this->getJson($this->url.'/sesi/'.$this->s->id.'/laporan')->assertUnprocessable();
        $p->update(['praobservasi_versi' => 2, 'nilai' => []]);
        $this->getJson($this->url.'/sesi/'.$this->s->id.'/laporan')->assertUnprocessable();
        $this->s->update(['status' => 'dibatalkan']);
        $this->getJson($this->url.'/rekap')->assertJsonPath('data.daftar.data.0.tahap', 'dibatalkan')->assertJsonPath('data.statistik.hasil_final', 0);
    }

    public function test_cakupan_guru_supervisor_dan_penolakan_akun_lain(): void
    {
        $lain = $this->salin(['guru_pegawai_id' => $this->akun['lain']->pegawai_id, 'supervisor_pegawai_id' => $this->akun['lain']->pegawai_id]);
        foreach (['guru', 'supervisor'] as $key) {
            $this->login($key);
            $this->getJson($this->url.'/rekap')->assertOk()->assertJsonCount(1, 'data.daftar.data');
            $this->getJson($this->url.'/sesi/'.$lain->id.'/laporan')->assertNotFound();
            $this->getJson($this->url.'/rekap?tab=belum_terjadwal')->assertForbidden();
            $this->getJson($this->url.'/rekap?guru_id='.$this->akun['lain']->pegawai_id)->assertNotFound();
        }
        $this->login('admin');
        $this->getJson($this->url.'/rekap')->assertJsonCount(2, 'data.daftar.data');
    }

    public function test_filter_program_tahun_semester_pencarian_dan_paginasi(): void
    {
        $this->login('admin');
        $this->getJson($this->url.'/rekap?semester=genap')->assertOk()->assertJsonCount(0, 'data.daftar.data');
        $this->getJson($this->url.'/rekap?program_id=999')->assertNotFound();
        $this->getJson($this->url.'/rekap?tahun_pelajaran_id=999')->assertNotFound();
        $this->getJson($this->url.'/rekap?kata_kunci=pernapasan')->assertJsonCount(0, 'data.daftar.data');
        $this->getJson($this->url.'/rekap?kata_kunci=ROBI')->assertJsonCount(1, 'data.daftar.data');
        for ($i = 0; $i < 21; $i++) {
            $this->salin([], false);
        }
        $this->getJson($this->url.'/rekap')->assertJsonCount(20, 'data.daftar.data')->assertJsonPath('data.daftar.total', 22);
        $this->getJson($this->url.'/rekap?halaman=2')->assertJsonCount(2, 'data.daftar.data');
    }

    public function test_guru_belum_terjadwal_deduplikasi_dan_jadwal_draf_bukan_terbit(): void
    {
        $g = GuruMataPelajaran::first();
        $k = $g->kelas->replicate();
        $k->nama = 'VIII.B';
        $k->save();
        $g2 = $g->replicate();
        $g2->kelas_id = $k->id;
        $g2->save();
        $this->s->update(['status' => 'draf', 'diterbitkan_pada' => null]);
        $this->login('admin');
        $this->getJson($this->url.'/rekap?tab=belum_terjadwal')->assertOk()->assertJsonPath('data.statistik.guru_aktif', 1)->assertJsonCount(1, 'data.daftar.data')->assertJsonPath('data.daftar.data.0.kelas', 'VIII.A, VIII.B');
        $this->s->update(['status' => 'terjadwal', 'diterbitkan_pada' => now()]);
        $this->getJson($this->url.'/rekap?tab=belum_terjadwal')->assertJsonPath('data.statistik.sudah_terjadwal', 1)->assertJsonCount(0, 'data.daftar.data');
        $this->s->update(['status' => 'dibatalkan']);
        $this->getJson($this->url.'/rekap?tab=belum_terjadwal')->assertJsonCount(1, 'data.daftar.data');
        $this->akun['guru']->pegawai->update(['aktif' => false]);
        $this->getJson($this->url.'/rekap?tab=belum_terjadwal')->assertJsonCount(0, 'data.daftar.data');
        $this->getJson($this->url.'/rekap?tab=belum_terjadwal&terlambat=1')->assertUnprocessable();
    }

    public function test_progres_tenggat_dan_acuan_refleksi_lama(): void
    {
        $ref = $this->refleksi();
        $task = $this->tugas();
        $this->tugas(['status' => 'draf', 'diterbitkan_pada' => null, 'temuan' => 'RAHASIA DRAF RENCANA']);
        $this->tugas(['status' => 'ditugaskan', 'penilaian_versi' => 1]);
        $this->login('admin');
        $this->getJson($this->url.'/rekap?terlambat=1')->assertOk()->assertJsonPath('data.statistik.melewati_tenggat', 1)->assertJsonPath('data.daftar.data.0.tindak_lanjut', 1)->assertJsonPath('data.daftar.data.0.tahap', 'tindak_lanjut')->assertDontSee('RAHASIA DRAF RENCANA');
        $task->update(['status' => 'diajukan']);
        $this->getJson($this->url.'/rekap')->assertJsonPath('data.daftar.data.0.tahap', 'menunggu_verifikasi');
        $this->getJson($this->url.'/rekap?terlambat=1')->assertJsonCount(0, 'data.daftar.data');
        $ref->update(['penilaian_versi' => 1, 'status' => 'selesai']);
        $this->getJson($this->url.'/rekap')->assertJsonPath('data.daftar.data.0.tahap', 'penyesuaian_hasil')->assertJsonPath('data.statistik.selesai', 0);
    }

    public function test_laporan_menyaring_draf_dan_laporan_lama_meski_pembaca_pemilik(): void
    {
        $this->refleksi('draf')->update(['jawaban' => ['RAHASIA DRAF REFLEKSI']]);
        $this->tugas(['status' => 'draf', 'diterbitkan_pada' => null, 'temuan' => 'RAHASIA DRAF RENCANA']);
        $this->tugas(['penilaian_versi' => 1, 'temuan' => 'TEMUAN HASIL LAMA']);
        $this->tugas(['laporan_pelaksanaan' => 'RAHASIA DRAF PELAKSANAAN']);
        foreach (['guru', 'supervisor', 'admin'] as $key) {
            $this->login($key);
            $this->getJson($this->url.'/sesi/'.$this->s->id.'/laporan')->assertOk()->assertJsonPath('data.refleksi.terlihat', false)->assertJsonCount(1, 'data.tindak_lanjut')->assertDontSee('RAHASIA')->assertDontSee('TEMUAN HASIL LAMA')->assertDontSee('lokasi_file')->assertDontSee('sidik_pembuatan');
        }
    }

    public function test_cetak_kolektif_atomik_batas_dan_cakupan(): void
    {
        $this->login('guru');
        $lain = $this->salin(['guru_pegawai_id' => $this->akun['lain']->pegawai_id, 'supervisor_pegawai_id' => $this->akun['lain']->pegawai_id]);
        $path = $this->url.'/laporan/cetak';
        $this->postJson($path, ['sesi' => [$this->s->id, $lain->id]])->assertNotFound();
        $this->postJson($path, ['sesi' => [$this->s->id, $this->s->id]])->assertUnprocessable();
        $this->postJson($path, ['sesi' => range(1, 11)])->assertUnprocessable();
        $this->postJson($path, ['sesi' => []])->assertUnprocessable();
        $this->login('admin');
        $this->postJson($path, ['sesi' => [$this->s->id, $lain->id]])->assertOk()->assertSee('data-report="'.$lain->id.'"', false);
        $lain->penilaian->update(['status' => 'draf']);
        $this->postJson($path, ['sesi' => [$this->s->id, $lain->id]])->assertUnprocessable();
    }

    public function test_excel_berisi_angka_numerik_bukan_formula_dan_tanpa_nilai_draf(): void
    {
        $this->s->update(['konteks' => [...$this->s->konteks, 'guru' => '=1+1']]);
        $draf = $this->salin();
        $draf->penilaian->update(['status' => 'draf']);
        $data = app(RekapSupervisiService::class)->bangun($this->akun['admin'], []);
        $path = app(PenulisExcelSupervisi::class)->buat($data);
        try {
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path));
            $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
            $zip->close();
            $this->assertStringContainsString('<c r="J6" s="3"><v>75</v></c>', $xml);
            $this->assertStringContainsString('<c r="J7" s="3"/>', $xml);
            $this->assertStringContainsString('=1+1', $xml);
            $this->assertStringNotContainsString('<f>', $xml);
            $this->assertNotFalse(simplexml_load_string($xml));
            if (getenv('NUSA_CAPTURE_SUPERVISI') === '1') {
                $dir = storage_path('framework/testing/supervisi');
                File::ensureDirectoryExists($dir);
                File::copy($path, $dir.'/rekap.xlsx');
            }
        } finally {
            @unlink($path);
        }
        $this->login('guru');
        $this->get($this->url.'/rekap/excel')->assertOk()->assertDownload();
    }

    public function test_instrumen_versi_dinamis_dan_status_selesai(): void
    {
        $v = $this->s->instrumen->replicate();
        $v->nomor = 2;
        $v->skala = [['skor' => 0, 'label' => 'Tidak ada'], ['skor' => 1, 'label' => 'Cukup'], ['skor' => 2, 'label' => 'Baik']];
        $v->kelompok = [['tahap' => 'perencanaan', 'nama' => 'Rencana baru', 'indikator' => ['Indikator A']], ['tahap' => 'pelaksanaan', 'nama' => 'Praktik baru', 'indikator' => ['Indikator B', 'Indikator C']]];
        $v->save();
        $this->s->update(['versi_instrumen_supervisi_id' => $v->id]);
        $this->s->penilaian->update(['nilai' => [['kelompok' => 0, 'indikator' => 0, 'skor' => 2], ['kelompok' => 1, 'indikator' => 0, 'skor' => 0], ['kelompok' => 1, 'indikator' => 1, 'skor' => 2]]]);
        $this->refleksi('selesai');
        $this->login('guru');
        $this->getJson($this->url.'/rekap')->assertOk()->assertJsonPath('data.statistik.selesai', 1)->assertJsonPath('data.daftar.data.0.nilai_akhir', 75);
        $this->getJson($this->url.'/sesi/'.$this->s->id.'/laporan')->assertOk()->assertJsonPath('data.instrumen.nomor', 2)->assertJsonCount(2, 'data.instrumen.kelompok');
    }

    public function test_guru_tanpa_penugasan_mendapat_rekap_kosong_tanpa_nama_guru_lain(): void
    {
        $this->getJson($this->url.'/rekap')->assertUnauthorized();
        $this->login('lain');
        $this->getJson($this->url.'/rekap')->assertOk()->assertJsonCount(0, 'data.daftar.data')->assertJsonCount(0, 'data.pilihan.guru')->assertJsonCount(0, 'data.pilihan.supervisor')->assertJsonPath('data.statistik.rata_rata', null)->assertDontSee('Robi Candra');
        $this->getJson($this->url.'/rekap?program_id='.$this->s->program_supervisi_id)->assertNotFound();
        $this->getJson($this->url.'/rekap?semester=salah')->assertUnprocessable();
    }

    public function test_filter_guru_supervisor_progres_saling_konsisten(): void
    {
        $s2 = $this->salin(['guru_pegawai_id' => $this->akun['lain']->pegawai_id, 'supervisor_pegawai_id' => $this->akun['lain']->pegawai_id]);
        $s2->penilaian->update(['status' => 'draf']);
        $this->login('admin');
        $this->getJson($this->url.'/rekap?supervisor_id='.$this->akun['supervisor']->pegawai_id.'&tahap=menunggu_refleksi')->assertOk()->assertJsonCount(1, 'data.daftar.data')->assertJsonPath('data.statistik.hasil_final', 1);
        $this->getJson($this->url.'/rekap?guru_id='.$this->akun['lain']->pegawai_id.'&tahap=menunggu_penilaian')->assertOk()->assertJsonCount(1, 'data.daftar.data')->assertJsonPath('data.statistik.hasil_final', 0);
        $this->getJson($this->url.'/rekap?guru_id='.$this->akun['lain']->pegawai_id.'&tahap=selesai')->assertJsonCount(0, 'data.daftar.data');
    }

    public function test_laporan_refleksi_dan_bukti_hanya_untuk_hasil_terbaru(): void
    {
        $ref = $this->refleksi('terkirim');
        $task = $this->tugas(['status' => 'diajukan', 'tanggal_pelaksanaan' => '2026-10-24', 'laporan_pelaksanaan' => 'Laporan sudah diajukan.', 'hasil_pelaksanaan' => 'Hasil sudah diajukan.']);
        $task->lampiran()->create(['lokasi_file' => 'supervisi-tindak-lanjut/RAHASIA/berkas.pdf', 'nama_file_asli' => 'Bukti exit ticket.pdf', 'tipe_file' => 'application/pdf', 'ukuran_file' => 123]);
        $this->login('guru');
        $path = $this->url.'/sesi/'.$this->s->id.'/laporan';
        $this->getJson($path)->assertOk()->assertJsonPath('data.refleksi.terlihat', true)->assertJsonPath('data.tindak_lanjut.0.laporan_terlihat', true)->assertSee('Bukti exit ticket.pdf')->assertDontSee('RAHASIA')->assertDontSee('unduh');
        $ref->update(['penilaian_versi' => 1, 'jawaban' => ['REFLEKSI LAMA PRIVAT']]);
        $task->update(['penilaian_versi' => 1]);
        $this->getJson($path)->assertOk()->assertJsonPath('data.refleksi.status', 'penyesuaian_hasil')->assertJsonPath('data.refleksi.terlihat', false)->assertJsonCount(0, 'data.tindak_lanjut')->assertDontSee('REFLEKSI LAMA PRIVAT');
    }

    public function test_cakupan_program_berbeda_dan_semester_lain_tidak_menghitung_jadwal_lama(): void
    {
        $p = $this->s->program->replicate();
        $p->token_pembuatan = (string) Str::uuid();
        $p->nama = 'Program Kedua';
        $p->save();
        $this->login('admin');
        $this->getJson($this->url.'/rekap?tab=belum_terjadwal&program_id='.$p->id)->assertOk()->assertJsonPath('data.statistik.belum_terjadwal', 1);
        $this->getJson($this->url.'/rekap?tab=belum_terjadwal&semester=genap')->assertOk()->assertJsonPath('data.statistik.belum_terjadwal', 1);
        $this->getJson($this->url.'/rekap?tab=belum_terjadwal&kata_kunci=tidakada')->assertOk()->assertJsonPath('data.statistik.guru_aktif', 0)->assertJsonPath('data.statistik.belum_terjadwal', 0);
    }

    public function test_batas_ukuran_laporan_tidak_memotong_isi_diam_diam(): void
    {
        $this->s->penilaian->update(['catatan' => str_repeat('A', 2_000_001)]);
        $this->login('admin');
        $this->getJson($this->url.'/sesi/'.$this->s->id.'/laporan')->assertUnprocessable()->assertJsonValidationErrors('sesi');
    }

    public function test_pembatasan_rekap_besar_meminta_filter_lebih_sempit(): void
    {
        $rows = [];
        $data = $this->s->getAttributes();
        unset($data['id']);
        for ($i = 0; $i < 1500; $i++) {
            $rows[] = [...$data, 'token_pembuatan' => (string) Str::uuid()];
        }
        foreach (array_chunk($rows, 50) as $chunk) {
            SesiSupervisi::insert($chunk);
        }
        $this->login('admin');
        $this->getJson($this->url.'/rekap')->assertUnprocessable()->assertJsonValidationErrors('program_id');
    }

    public function test_seluruh_halaman_web_dan_fixture_cetak(): void
    {
        $this->actingAs($this->akun['admin']);
        $this->capture('rekap', route('supervisi.rekap.index'));
        $this->capture('cetak-rekap', route('supervisi.rekap.cetak'));
        $this->capture('cetak-awal', route('supervisi.laporan.cetak', $this->s));
        $ref = $this->refleksi('selesai');
        $task = $this->tugas(['status' => 'selesai', 'laporan_pelaksanaan' => 'Exit ticket telah diterapkan pada dua pertemuan.', 'hasil_pelaksanaan' => 'Pemahaman siswa dapat dipetakan.', 'tanggal_pelaksanaan' => '2026-10-24', 'selesai_pada' => now()]);
        $this->capture('cetak-laporan', route('supervisi.laporan.cetak', $this->s));
        $this->s->update(['status' => 'draf', 'diterbitkan_pada' => null]);
        $this->capture('rekap-cakupan', route('supervisi.rekap.index', ['tab' => 'belum_terjadwal']));
        $this->capture('cetak-cakupan', route('supervisi.rekap.cetak', ['tab' => 'belum_terjadwal']));
        $this->s->update(['status' => 'terjadwal', 'diterbitkan_pada' => now()]);
        $this->actingAs($this->akun['guru']);
        $this->capture('rekap-guru', route('supervisi.rekap.index'));
        $this->actingAs($this->akun['admin']);
        $s2 = $this->salin();
        $this->captureResponse('cetak-kolektif', $this->post(route('supervisi.laporan.kolektif'), ['sesi' => [$this->s->id, $s2->id]]));
        $task->update(['hasil_pelaksanaan' => str_repeat('Hasil pelaksanaan panjang: perkembangan kemampuan siswa dipantau secara berkala. ', 140)]);
        $this->s->penilaian->update(['catatan' => str_repeat('Catatan panjang untuk menguji pemenggalan halaman tanpa kehilangan isi. ', 125).' PENANDA AKHIR CATATAN']);
        $this->capture('cetak-panjang', route('supervisi.laporan.cetak', $this->s));
        $this->capture('rekap-kosong', route('supervisi.rekap.index', ['semester' => 'genap']));
        for ($i = 0; $i < 12; $i++) {
            $this->salin();
        }
        $this->capture('rekap-banyak', route('supervisi.rekap.index'));
        for ($i = 0; $i < 25; $i++) {
            $this->salin([], false);
        }
        $this->capture('cetak-rekap-panjang', route('supervisi.rekap.cetak'));
    }

    private function capture(string $name, string $url): void
    {
        $this->captureResponse($name, $this->get($url));
    }

    private function captureResponse(string $name, $response): void
    {
        $response->assertOk();
        foreach (['@endif', '@if(', '@endforeach', '@endunless'] as $literal) {
            $response->assertDontSee($literal, false);
        }
        if (getenv('NUSA_CAPTURE_SUPERVISI') !== '1') {
            return;
        }
        $dir = storage_path('framework/testing/supervisi');
        File::ensureDirectoryExists($dir);
        File::put($dir.'/'.$name.'.html', $response->getContent());
    }
}
