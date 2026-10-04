<?php

namespace Tests\Feature\Api;

use App\Models\AgendaHumas;
use App\Models\AnggotaKelas;
use App\Models\Izin;
use App\Models\Kelas;
use App\Models\OrangTuaWali;
use App\Models\Pegawai;
use App\Models\PengaduanHumas;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\UmpanBalikHumas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class HumasApiTest extends TestCase
{
    use RefreshDatabase;

    private Pengguna $humas;

    private Pengguna $ortu;

    private Pengguna $lain;

    private TahunPelajaran $tahun;

    private Kelas $kelas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(10, 0));
        $this->withoutMiddleware(ThrottleRequests::class);
        Storage::fake('local');
        $this->tahun = TahunPelajaran::create(['nama' => '2026/2027', 'aktif' => true, 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30']);
        $this->kelas = Kelas::create(['nama' => 'VII.A', 'tingkat' => 7, 'tahun_pelajaran_id' => $this->tahun->id, 'aktif' => true]);
        $this->humas = $this->akun('wakil_pimpinan_humas');
        $this->ortu = $this->orangTua();
        $this->lain = $this->orangTua();
    }

    public function test_semua_rute_memerlukan_token_mobile_aktif_dan_sandi_baru(): void
    {
        $routes = collect(Route::getRoutes())->filter(fn ($r) => str_starts_with($r->getName() ?? '', 'api.v1.humas.'));
        $this->assertCount(70, $routes);
        foreach ($routes as $r) {
            foreach (['auth:sanctum', 'abilities:mobile', 'akun_api_aktif', 'kata_sandi_api_bukan_default'] as $guard) {
                $this->assertContains($guard, $r->gatherMiddleware(), $r->uri());
            }
        }
        $this->getJson($this->url('agenda'))->assertUnauthorized();
        $this->token($this->humas, ['other']);
        $this->getJson($this->url('agenda'))->assertForbidden();
        $this->humas->update(['wajib_ganti_kata_sandi' => true]);
        $this->token($this->humas);
        $this->getJson($this->url('agenda'))->assertStatus(428);
        $this->humas->update(['wajib_ganti_kata_sandi' => false, 'aktif' => false]);
        $this->token($this->humas);
        $this->getJson($this->url('agenda'))->assertUnauthorized();
    }

    public function test_referensi_dan_rute_anak_memberikan_json_bukan_tertangkap_rute_detail(): void
    {
        $this->token($this->humas);
        $a = $this->agenda();
        foreach (['agenda', "agenda/{$a->id}/peserta", "agenda/{$a->id}/tindak-lanjut", 'pengaduan', 'pengaduan/referensi', 'umpan-balik', 'umpan-balik/referensi'] as $path) {
            $r = $this->getJson($this->url($path))->assertOk()->assertJsonStructure(['data'])->assertHeader('Content-Type', 'application/json');
            $this->assertIsArray($r->json('data'));
            $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        }
        $this->token($this->ortu);
        $this->getJson($this->url('pengaduan-saya/referensi'))->assertOk()->assertJsonStructure(['data' => ['token_pembuatan', 'jenis']]);
    }

    public function test_akses_pegawai_pimpinan_siswa_dan_orang_tua_dipisahkan(): void
    {
        $a = $this->agenda();
        $this->token($this->akun('pimpinan'));
        $this->getJson($this->url("agenda/{$a->id}"))->assertOk()->assertJsonMissingPath('data.qr');
        $this->postJson($this->url('agenda'), $this->agendaData())->assertForbidden();
        $this->getJson($this->url('umpan-balik'))->assertOk();
        $this->ortu->daftarPeran()->attach(Peran::where('kode', 'wakil_pimpinan_humas')->firstOrFail());
        $student = $this->akun('wakil_pimpinan_humas');
        $student->update(['siswa_id' => $this->ortu->orangTuaWali->siswa()->first()->id]);
        foreach ([$this->ortu, $student] as $u) {
            $this->token($u);
            foreach (['agenda', 'pengaduan', 'umpan-balik'] as $path) {
                $this->getJson($this->url($path))->assertForbidden();
            }
        }
        $this->token($this->akun('guru_mapel'));
        $this->getJson($this->url('agenda'))->assertForbidden();
        $this->getJson($this->url('pengaduan'))->assertOk()->assertJsonPath('data.paginasi.total', 0);
        $this->token($this->akun('orang_tua'));
        foreach (['pertemuan-saya', 'pengaduan-saya', 'umpan-balik-saya'] as $path) {
            $this->getJson($this->url($path))->assertForbidden();
        }
    }

    public function test_agenda_crud_notulen_status_dan_validasi(): void
    {
        $this->token($this->humas);
        $id = $this->postJson($this->url('agenda'), $this->agendaData())->assertCreated()->json('data.id');
        $this->patchJson($this->url("agenda/$id"), $this->agendaData() + ['status' => 'selesai'])->assertOk();
        $this->patchJson($this->url("agenda/$id/status"), ['status' => 'selesai'])->assertUnprocessable();
        $this->putJson($this->url("agenda/$id/notulen"), ['pemimpin' => 'Waka Humas', 'notulis' => 'Sekretaris', 'pembahasan' => 'Pembahasan rapat', 'keputusan' => 'Keputusan rapat'])->assertOk();
        $this->patchJson($this->url("agenda/$id/status"), ['status' => 'selesai'])->assertOk()->assertJsonPath('data.status', 'selesai');
        $this->postJson($this->url("agenda/$id/peserta"), ['nama' => 'Tamu baru'])->assertUnprocessable();
        $this->postJson($this->url('agenda'), array_replace($this->agendaData(), ['waktu_selesai' => '2026-10-05T08:00']))->assertUnprocessable();
        $this->getJson($this->url('agenda').'?per_halaman=51')->assertUnprocessable();
        $this->getJson($this->url('agenda').'?halaman[]=1')->assertUnprocessable();
    }

    public function test_presensi_versi_lama_dan_peserta_asing_tidak_menimpa_batch(): void
    {
        $this->token($this->humas);
        $a = $this->agenda();
        $b = $this->agenda();
        $this->postJson($this->url("agenda/{$a->id}/peserta"), ['peserta' => [['nama' => 'Tamu A']]])->assertCreated();
        $p = $a->peserta()->firstOrFail();
        $q = $a->peserta()->create(['nama' => 'Tamu B', 'versi_presensi' => 1]);
        $path = $this->url("agenda/{$a->id}/presensi");
        $data = ['jumlah_baris' => 2, 'kehadiran' => [['id' => $p->id, 'versi_presensi' => 0, 'status_kehadiran' => 'hadir'], ['id' => $q->id, 'versi_presensi' => 0, 'status_kehadiran' => 'hadir']]];
        $this->putJson($path, $data)->assertUnprocessable();
        $this->assertSame('belum_dicatat', $p->fresh()->status_kehadiran);
        $data['kehadiran'][1]['versi_presensi'] = 1;
        $this->putJson($path, $data)->assertOk()->assertJsonPath('data.rekap_presensi.hadir', 2);
        $this->deleteJson($this->url("agenda/{$b->id}/peserta/{$p->id}"))->assertNotFound();
        $this->deleteJson($this->url("agenda/{$a->id}/peserta/{$p->id}"))->assertUnprocessable();
        $foreign = $b->peserta()->create(['nama' => 'Tamu asing']);
        $data['kehadiran'][1]['id'] = $foreign->id;
        $this->putJson($path, $data)->assertUnprocessable();
        $this->assertSame('belum_dicatat', $foreign->fresh()->status_kehadiran);
    }

    public function test_undangan_qr_tidak_mencatat_get_dan_konfirmasi_idempoten(): void
    {
        $a = $this->undangan();
        $this->token($this->ortu);
        $this->getJson($this->url('pertemuan-saya'))->assertOk()->assertJsonPath('data.paginasi.total', 1)->assertJsonMissingPath('data.items.0.agenda.token_presensi');
        $scan = $this->url("pertemuan-saya/scan/{$a->token_presensi}");
        $this->getJson($scan)->assertOk()->assertJsonPath('data.kehadiran.status_kehadiran', 'belum_dicatat')->assertJsonMissingPath('data.kehadiran.catatan');
        $this->getJson($this->url("pertemuan-saya/{$a->id}"))->assertOk()->assertJsonMissingPath('data.agenda.pembahasan');
        $this->assertSame(0, $a->peserta()->where('status_kehadiran', 'hadir')->count());
        $this->postJson($scan.'/hadir', ['peserta_id' => 999])->assertOk()->assertJsonPath('data.kehadiran.status_kehadiran', 'hadir');
        $p = $a->peserta()->where('orang_tua_wali_id', $this->ortu->orangTuaWali->id)->firstOrFail();
        $waktu = $p->hadir_pada;
        $this->travel(5)->minutes();
        $this->postJson($scan.'/hadir')->assertOk();
        $this->assertTrue($p->fresh()->hadir_pada->equalTo($waktu));
        $this->assertSame(1, $p->fresh()->versi_presensi);
        $this->assertSame($this->ortu->id, $p->fresh()->dicatat_oleh_pengguna_id);
    }

    public function test_qr_tertutup_asing_dan_hilang_relasi_anak_ditolak(): void
    {
        $a = $this->undangan(false);
        $scan = $this->url("pertemuan-saya/scan/{$a->token_presensi}");
        $this->token($this->ortu);
        $this->postJson($scan.'/hadir')->assertUnprocessable();
        $this->token($this->lain);
        $this->getJson($scan)->assertNotFound();
        $this->postJson($scan.'/hadir')->assertNotFound();
        $this->getJson($this->url('pertemuan-saya'))->assertJsonPath('data.paginasi.total', 0);
        $this->token($this->ortu);
        $this->ortu->orangTuaWali->siswa()->detach();
        $this->getJson($scan)->assertNotFound();
        $this->getJson($this->url('pertemuan-saya'))->assertJsonPath('data.paginasi.total', 0);
        $this->assertSame(0, $a->peserta()->where('status_kehadiran', 'hadir')->count());
    }

    public function test_qr_tidak_menimpa_izin_manual_dan_riwayat_agenda_selesai(): void
    {
        $a = $this->undangan();
        $p = $a->peserta()->firstOrFail();
        $p->update(['status_kehadiran' => 'izin', 'sumber_kehadiran' => 'manual']);
        $this->token($this->ortu);
        $this->postJson($this->url("pertemuan-saya/scan/{$a->token_presensi}/hadir"))->assertUnprocessable();
        $a->update(['status' => 'selesai']);
        $this->getJson($this->url('pertemuan-saya'))->assertJsonPath('data.paginasi.total', 0);
        $this->getJson($this->url('pertemuan-saya').'?tab=riwayat')->assertJsonPath('data.paginasi.total', 1);
    }

    public function test_pengaduan_identitas_otoritas_dan_retry_dari_akun_lain(): void
    {
        $this->token($this->ortu);
        $data = $this->pengaduanData() + ['pelapor_pengguna_id' => $this->lain->id, 'status' => 'selesai', 'kanal' => 'surat', 'nama_pelapor' => 'PALSU'];
        $r = $this->postJson($this->url('pengaduan-saya'), $data)->assertCreated()->assertJsonMissingPath('data.identitas_privat');
        $t = PengaduanHumas::findOrFail($r->json('data.id'));
        $this->assertSame($this->ortu->id, $t->pelapor_pengguna_id);
        $this->assertSame('baru', $t->status);
        $this->assertSame('akun_orang_tua', $t->kanal);
        $this->assertSame('IDENTITAS PRIVAT', $t->nama_pelapor);
        $this->postJson($this->url('pengaduan-saya'), $data)->assertOk()->assertJsonPath('data.id', $t->id);
        $this->assertDatabaseCount('pengaduan_humas', 1);
        $this->token($this->lain);
        $this->postJson($this->url('pengaduan-saya'), $data)->assertForbidden();
        $this->getJson($this->url("pengaduan-saya/{$t->id}"))->assertNotFound();
        $this->getJson($this->url("pengaduan-saya/{$t->id}/pesan"))->assertNotFound();
        $this->getJson($this->url('pengaduan-saya'))->assertJsonPath('data.paginasi.total', 0);
    }

    public function test_pengaduan_file_privat_hanya_milik_sendiri_dan_bukan_lampiran_internal(): void
    {
        $this->token($this->ortu);
        $data = $this->pengaduanData() + ['lampiran' => [$this->foto()]];
        $id = $this->postJson($this->url('pengaduan-saya'), $data)->assertCreated()->json('data.id');
        $this->postJson($this->url('pengaduan-saya'), $data)->assertOk();
        $t = PengaduanHumas::findOrFail($id);
        $f = $t->lampiran()->firstOrFail();
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $r = $this->getJson($this->url("pengaduan-saya/$id"))->assertOk()->assertJsonMissingPath('data.lampiran.0.lokasi_file');
        $this->get($r->json('data.lampiran.0.url'))->assertDownload('bukti.jpg')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->token($this->humas);
        $this->postJson($this->url("pengaduan/$id/lampiran"), ['versi' => $t->versi, 'catatan_perubahan' => 'Bukti internal', 'lampiran' => [$this->foto('internal.jpg')]])->assertOk();
        $internal = $t->lampiran()->where('asal', 'internal')->firstOrFail();
        $this->token($this->ortu);
        $this->getJson($this->url("pengaduan-saya/$id"))->assertJsonCount(1, 'data.lampiran');
        $this->getJson($this->url("pengaduan-saya/$id/lampiran/{$internal->id}"))->assertNotFound();
        $this->token($this->lain);
        $this->getJson($this->url("pengaduan-saya/$id/lampiran/{$f->id}"))->assertNotFound();
        $this->token($this->ortu);
        $f->update(['lokasi_file' => 'outside/private.txt']);
        Storage::disk('local')->put('outside/private.txt', 'private');
        $this->getJson($this->url("pengaduan-saya/$id/lampiran/{$f->id}"))->assertNotFound();
    }

    public function test_pengaduan_informasi_balasan_internal_versi_dan_penutupan(): void
    {
        $t = $this->buatPengaduan();
        $this->token($this->humas);
        $this->postJson($this->url("pengaduan/{$t->id}/tindakan/selesaikan"), ['versi' => 0, 'catatan' => 'Penyelesaian internal'])->assertJsonValidationErrors('balasan');
        $reply = ['token_pengiriman' => (string) Str::uuid(), 'versi' => 0, 'isi_pesan' => 'BALASAN RESMI'];
        $this->postJson($this->url("pengaduan/{$t->id}/balasan"), $reply)->assertOk();
        $this->postJson($this->url("pengaduan/{$t->id}/balasan"), $reply)->assertOk();
        $this->token($this->ortu);
        $extra = ['token_pengiriman' => (string) Str::uuid(), 'versi' => $t->fresh()->versi, 'isi_pesan' => 'INFORMASI ORANG TUA', 'asal' => 'humas'];
        $this->postJson($this->url("pengaduan-saya/{$t->id}/informasi"), array_replace($extra, ['versi' => 0]))->assertJsonValidationErrors('versi');
        $this->postJson($this->url("pengaduan-saya/{$t->id}/informasi"), $extra)->assertOk();
        $this->postJson($this->url("pengaduan-saya/{$t->id}/informasi"), $extra)->assertOk();
        $this->assertSame('orang_tua', $t->pesan()->where('isi', 'INFORMASI ORANG TUA')->firstOrFail()->asal);
        $this->assertSame(2, $t->pesan()->count());
        $this->token($this->humas);
        $this->postJson($this->url("pengaduan/{$t->id}/tindakan/selesaikan"), ['versi' => $t->fresh()->versi, 'catatan' => 'CATATAN INTERNAL RAHASIA'])->assertOk();
        $this->token($this->ortu);
        $r = $this->getJson($this->url("pengaduan-saya/{$t->id}"))->assertOk()->assertJsonMissingPath('data.hasil_penanganan');
        $this->assertStringNotContainsString('CATATAN INTERNAL RAHASIA', $r->getContent());
        $this->getJson($this->url("pengaduan-saya/{$t->id}/pesan"))->assertOk()->assertJsonCount(2, 'data.items');
        $this->postJson($this->url("pengaduan-saya/{$t->id}/informasi"), array_replace($extra, ['token_pengiriman' => (string) Str::uuid(), 'versi' => $t->fresh()->versi]))->assertJsonValidationErrors('status');
    }

    public function test_petugas_hanya_tiket_penugasan_tanpa_identitas_dan_berkas(): void
    {
        $t = $this->buatPengaduan();
        $petugas = $this->akun('pegawai', true);
        $this->token($this->humas);
        $this->postJson($this->url("pengaduan/{$t->id}/tindakan/disposisi"), ['versi' => 0, 'catatan' => 'Tugas tindak lanjut', 'petugas_pengguna_id' => $petugas->id, 'batas_tanggal' => '2026-10-06'])->assertOk();
        $this->getJson($this->url("pengaduan/{$t->id}"))->assertJsonPath('data.identitas_privat.nama', 'IDENTITAS PRIVAT');
        $this->token($petugas);
        $this->getJson($this->url('pengaduan'))->assertJsonPath('data.paginasi.total', 1);
        $r = $this->getJson($this->url("pengaduan/{$t->id}"))->assertOk()->assertJsonMissingPath('data.identitas_privat')->assertJsonMissingPath('data.lampiran');
        $this->assertStringNotContainsString('IDENTITAS PRIVAT', $r->getContent());
        $this->getJson($this->url("pengaduan/{$t->id}/riwayat"))->assertOk()->assertJsonMissingPath('data.items.0.snapshot')->assertJsonMissingPath('data.items.0.pengguna');
        $this->postJson($this->url("pengaduan/{$t->id}/balasan"), [])->assertForbidden();
        $this->postJson($this->url("pengaduan/{$t->id}/tindakan/proses"), ['versi' => $t->fresh()->versi, 'catatan' => 'Mulai mengerjakan tugas'])->assertOk();
        $this->token($this->akun('pegawai', true));
        $this->getJson($this->url("pengaduan/{$t->id}"))->assertNotFound();
        $this->getJson($this->url('pengaduan'))->assertJsonPath('data.paginasi.total', 0);
    }

    public function test_umpan_balik_draf_aktivasi_target_dan_instrumen_terkunci(): void
    {
        $this->token($this->humas);
        $data = $this->formData();
        $id = $this->postJson($this->url('umpan-balik'), $data)->assertCreated()->json('data.id');
        $this->postJson($this->url('umpan-balik'), $data)->assertSuccessful()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('umpan_balik_humas', 1);
        $f = UmpanBalikHumas::findOrFail($id);
        $this->token($this->ortu);
        $this->getJson($this->url("umpan-balik-saya/$id"))->assertNotFound();
        $this->token($this->humas);
        $this->patchJson($this->url("umpan-balik/$id/status"), ['versi' => $f->versi, 'status' => 'aktif', 'alasan' => 'Periode evaluasi dibuka'])->assertOk();
        $this->assertSame(2, $f->sasaran()->count());
        $this->patchJson($this->url("umpan-balik/$id"), $data + ['versi' => $f->fresh()->versi, 'alasan' => 'Ubah setelah dibuka'])->assertUnprocessable();
        $this->patchJson($this->url("umpan-balik/$id/status"), ['versi' => 1, 'status' => 'ditutup', 'alasan' => 'Versi lama harus ditolak'])->assertJsonValidationErrors('formulir');
    }

    public function test_umpan_balik_jawaban_sendiri_retry_skala_rekap_dan_privasi(): void
    {
        $f = $this->bukaForm();
        $this->token($this->ortu);
        $r = $this->getJson($this->url("umpan-balik-saya/{$f->id}"))->assertOk()->assertJsonPath('data.dapat_mengirim', true);
        $data = $this->jawaban($f, 4, 'MASUKAN AKUN A') + ['token_pengiriman' => $r->json('data.token_pengiriman'), 'orang_tua_wali_id' => $this->lain->orangTuaWali->id];
        $this->postJson($this->url("umpan-balik-saya/{$f->id}"), $data)->assertOk();
        $this->postJson($this->url("umpan-balik-saya/{$f->id}"), $data)->assertOk();
        $this->assertDatabaseCount('jawaban_umpan_balik_humas', 2);
        $this->postJson($this->url("umpan-balik-saya/{$f->id}"), array_replace($data, ['token_pengiriman' => (string) Str::uuid()]))->assertUnprocessable();
        $this->getJson($this->url('umpan-balik-saya'))->assertJsonPath('data.paginasi.total', 0);
        $this->getJson($this->url('umpan-balik-saya').'?tab=riwayat')->assertJsonPath('data.paginasi.total', 1);
        $this->token($this->lain);
        $this->postJson($this->url("umpan-balik-saya/{$f->id}"), $data)->assertForbidden();
        $this->postJson($this->url("umpan-balik-saya/{$f->id}"), $this->jawaban($f, 2, 'MASUKAN AKUN B') + ['token_pengiriman' => (string) Str::uuid()])->assertOk();
        $r = $this->getJson($this->url("umpan-balik-saya/{$f->id}"))->assertOk()->assertJsonMissingPath('data.rekap')->assertJsonMissingPath('data.sasaran');
        $this->assertStringNotContainsString('MASUKAN AKUN A', $r->getContent());
        $this->token($this->humas);
        $scale = $f->pertanyaan()->where('jenis', 'skala')->firstOrFail();
        $text = $f->pertanyaan()->where('jenis', 'teks')->firstOrFail();
        $r = $this->getJson($this->url("umpan-balik/{$f->id}"))->assertOk()->assertJsonPath('data.rekap.respons', 2);
        $this->assertSame(3, $r->json("data.rekap.pertanyaan.{$scale->id}.rata"));
        $this->getJson($this->url("umpan-balik/{$f->id}/jawaban-teks/{$text->id}"))->assertOk()->assertJsonCount(2, 'data.items')->assertJsonMissingPath('data.items.0.sasaran_umpan_balik_humas_id');
    }

    public function test_umpan_balik_batas_waktu_skala_asing_dan_relasi_anak(): void
    {
        $f = $this->bukaForm();
        $g = $this->bukaForm();
        $scale = $f->pertanyaan()->first()->id;
        $foreign = $g->pertanyaan()->first()->id;
        $this->token($this->ortu);
        foreach ([[], [$scale => 5], [$scale => ['4']], [$scale => 3.5], [$scale => 4, $foreign => 2]] as $answers) {
            $this->postJson($this->url("umpan-balik-saya/{$f->id}"), ['token_pengiriman' => (string) Str::uuid(), 'jawaban' => $answers])->assertUnprocessable();
        }
        $this->travelTo($f->selesai_pada);
        $this->postJson($this->url("umpan-balik-saya/{$f->id}"), $this->jawaban($f) + ['token_pengiriman' => (string) Str::uuid()])->assertUnprocessable();
        $this->ortu->orangTuaWali->siswa()->detach();
        $this->getJson($this->url("umpan-balik-saya/{$f->id}"))->assertForbidden();
        $this->assertDatabaseCount('jawaban_umpan_balik_humas', 0);
    }

    public function test_tindak_lanjut_umpan_balik_hanya_ringkasan_publik_dan_izin_sumber(): void
    {
        $f = $this->bukaForm();
        $this->token($this->humas);
        $data = ['versi' => $f->fresh()->versi, 'token_pembuatan' => (string) Str::uuid(), 'uraian' => 'TINDAKAN INTERNAL', 'penanggung_jawab' => 'Waka Humas', 'batas_tanggal' => '2026-10-10', 'status' => 'diproses', 'hasil' => 'HASIL INTERNAL RAHASIA', 'bagikan_ringkasan' => false];
        $this->postJson($this->url("umpan-balik/{$f->id}/tindak-lanjut"), $data)->assertCreated();
        $t = $f->tindakLanjut()->firstOrFail();
        $this->getJson($this->url("umpan-balik/{$f->id}/tindak-lanjut"))->assertJsonPath('data.paginasi.total', 1);
        $this->token($this->ortu);
        $r = $this->getJson($this->url("umpan-balik-saya/{$f->id}"))->assertOk()->assertJsonCount(0, 'data.ringkasan_publik');
        $this->assertStringNotContainsString('HASIL INTERNAL RAHASIA', $r->getContent());
        $this->token($this->humas);
        $this->patchJson($this->url("umpan-balik/{$f->id}/tindak-lanjut/{$t->id}"), array_replace($data, ['versi' => $f->fresh()->versi, 'alasan' => 'Mencatat penyelesaian', 'status' => 'selesai', 'bagikan_ringkasan' => true, 'ringkasan_publik' => 'LAYANAN DIPERBAIKI']))->assertOk();
        $this->token($this->ortu);
        $this->getJson($this->url("umpan-balik-saya/{$f->id}"))->assertJsonPath('data.ringkasan_publik.0', 'LAYANAN DIPERBAIKI');
        $a = $this->agenda();
        $f->update(['cakupan' => 'agenda', 'agenda_humas_id' => $a->id]);
        $u = $this->akun('pegawai');
        $role = Peran::create(['kode' => 'humas_terbatas', 'nama' => 'Umpan saja', 'aktif' => true]);
        $role->izin()->attach(Izin::where('kode', 'umpan_balik_humas.lihat')->firstOrFail());
        $u->daftarPeran()->sync([$role->id]);
        $this->token($u);
        $this->getJson($this->url('umpan-balik'))->assertJsonPath('data.paginasi.total', 0);
        $this->getJson($this->url("umpan-balik/{$f->id}"))->assertForbidden();
    }

    public function test_agenda_lama_qr_kosong_dan_tindak_lanjut_asing(): void
    {
        $this->token($this->humas);
        $a = $this->agenda();
        $b = $this->agenda();
        $a->forceFill(['token_presensi' => null])->save();
        $this->getJson($this->url("agenda/{$a->id}"))->assertOk()->assertJsonPath('data.qr.url', null);
        $data = ['uraian' => 'Memperbaiki komunikasi sekolah', 'penanggung_jawab' => 'Waka Humas', 'batas_tanggal' => '2026-10-10'];
        $this->postJson($this->url("agenda/{$a->id}/tindak-lanjut"), $data)->assertCreated();
        $t = $a->tindakLanjut()->firstOrFail();
        $this->patchJson($this->url("agenda/{$a->id}/tindak-lanjut/{$t->id}"), $data + ['status' => 'selesai', 'catatan' => 'Tindak lanjut telah dilaksanakan'])->assertOk();
        $this->assertNotNull($t->fresh()->selesai_pada);
        $this->patchJson($this->url("agenda/{$b->id}/tindak-lanjut/{$t->id}"), $data + ['status' => 'diproses'])->assertNotFound();
        $this->patchJson($this->url("agenda/{$a->id}/status"), ['status' => 'dibatalkan', 'alasan_pembatalan' => 'Jadwal diganti'])->assertOk();
        $this->postJson($this->url("agenda/{$a->id}/tindak-lanjut"), $data)->assertUnprocessable();
        $this->patchJson($this->url("agenda/{$a->id}/akses-presensi"), ['dibuka' => true])->assertUnprocessable();
    }

    public function test_tiket_internal_koreksi_versi_retry_dan_unduh_pengelola(): void
    {
        $this->token($this->humas);
        $data = $this->pengaduanData() + ['kanal' => 'tatap_muka', 'tanggal_diterima' => '2026-10-05', 'prioritas' => 'normal', 'anonim' => false, 'nama_pelapor' => 'PELAPOR MANUAL', 'lampiran' => [$this->foto()]];
        $id = $this->postJson($this->url('pengaduan'), $data)->assertCreated()->json('data.id');
        $this->postJson($this->url('pengaduan'), $data)->assertOk()->assertJsonPath('data.id', $id);
        $t = PengaduanHumas::findOrFail($id);
        $f = $t->lampiran()->firstOrFail();
        $this->get($this->url("pengaduan/$id/lampiran/{$f->id}"))->assertDownload('bukti.jpg');
        unset($data['lampiran']);
        $edit = array_replace($data, ['judul' => 'Judul laporan diperbaiki', 'versi' => 0, 'catatan_perubahan' => 'Koreksi ejaan judul laporan']);
        $this->patchJson($this->url("pengaduan/$id"), $edit)->assertOk()->assertJsonPath('data.versi', 1);
        $this->patchJson($this->url("pengaduan/$id"), array_replace($edit, ['judul' => 'Tidak boleh tertimpa']))->assertJsonValidationErrors('versi');
        $this->token($this->akun('pimpinan'));
        $this->getJson($this->url("pengaduan/$id/lampiran/{$f->id}"))->assertForbidden();
        $this->getJson($this->url("pengaduan/$id"))->assertOk()->assertJsonMissingPath('data.identitas_privat');
        $this->token($this->akun('wakil_pimpinan_humas'));
        $this->postJson($this->url('pengaduan'), $data)->assertForbidden();
        $this->assertDatabaseCount('pengaduan_humas', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_file_invalid_dan_unggahan_gagal_tidak_menyisakan_berkas(): void
    {
        $this->token($this->ortu);
        foreach ([UploadedFile::fake()->create('bahaya.html', 2, 'text/html'), $this->foto()->size(10241)] as $file) {
            $this->postJson($this->url('pengaduan-saya'), $this->pengaduanData() + ['lampiran' => [$file]])->assertJsonValidationErrors('lampiran.0');
        }
        $t = $this->buatPengaduan();
        $this->token($this->humas);
        $t->forceFill(['versi' => 1])->save();
        $this->postJson($this->url("pengaduan/{$t->id}/lampiran"), ['versi' => 0, 'catatan_perubahan' => 'Lampiran dengan versi lama', 'lampiran' => [$this->foto()]])->assertUnprocessable()->assertJsonValidationErrors('versi');
        $this->assertCount(0, Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('lampiran_pengaduan_humas', 0);
    }

    public function test_pengiriman_dibatasi_dan_tidak_mengubah_data_setelah_limit(): void
    {
        $this->token($this->ortu);
        $this->withMiddleware(ThrottleRequests::class);
        $data = $this->pengaduanData();
        for ($i = 0; $i < 15; $i++) {
            $this->postJson($this->url('pengaduan-saya'), $data)->assertSuccessful();
        }
        $this->postJson($this->url('pengaduan-saya'), $data)->assertStatus(429);
        $this->assertDatabaseCount('pengaduan_humas', 1);
    }

    public function test_formulir_edit_draf_sasaran_kelas_arsip_dan_jawaban_asing(): void
    {
        $this->token($this->humas);
        $data = $this->formData();
        $id = $this->postJson($this->url('umpan-balik'), $data)->assertCreated()->json('data.id');
        $this->postJson($this->url('umpan-balik'), $data)->assertOk();
        $f = UmpanBalikHumas::findOrFail($id);
        $this->patchJson($this->url("umpan-balik/$id"), array_replace($data, ['versi' => $f->versi, 'alasan' => 'Mengoreksi judul evaluasi', 'judul' => 'Evaluasi yang dikoreksi']))->assertOk()->assertJsonPath('data.judul', 'Evaluasi yang dikoreksi');
        $this->patchJson($this->url("umpan-balik/$id/status"), ['versi' => $f->fresh()->versi, 'status' => 'aktif', 'alasan' => 'Periode evaluasi dibuka'])->assertOk();
        $this->token($this->ortu);
        $this->postJson($this->url("umpan-balik-saya/$id"), $this->jawaban($f) + ['token_pengiriman' => (string) Str::uuid()])->assertOk();
        $this->token($this->humas);
        $other = $this->bukaForm();
        $text = $other->pertanyaan()->where('jenis', 'teks')->firstOrFail();
        $this->getJson($this->url("umpan-balik/$id/jawaban-teks/{$text->id}"))->assertNotFound();
        $this->patchJson($this->url("umpan-balik/$id/status"), ['versi' => $f->fresh()->versi, 'status' => 'arsip', 'alasan' => 'Evaluasi sudah ditutup'])->assertOk();
        $this->token($this->lain);
        $this->getJson($this->url("umpan-balik-saya/$id"))->assertNotFound();
        $this->token($this->ortu);
        $this->getJson($this->url("umpan-balik-saya/$id"))->assertOk()->assertJsonPath('data.dapat_mengirim', false);
        $this->getJson($this->url('umpan-balik-saya').'?tab=riwayat')->assertJsonPath('data.paginasi.total', 1);
    }

    public function test_web_dan_api_tidak_menggandakan_pengaduan_dan_evaluasi(): void
    {
        $data = $this->pengaduanData();
        $this->actingAs($this->ortu)->postJson(route('pengaduan-saya.store'), $data)->assertOk();
        $id = PengaduanHumas::firstOrFail()->id;
        $this->token($this->ortu);
        $this->postJson($this->url('pengaduan-saya'), $data)->assertOk()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('pengaduan_humas', 1);
        $this->assertDatabaseCount('riwayat_pengaduan_humas', 1);
        $f = $this->bukaForm();
        $answer = $this->jawaban($f) + ['token_pengiriman' => (string) Str::uuid()];
        $this->token($this->ortu);
        $this->postJson($this->url("umpan-balik-saya/{$f->id}"), $answer)->assertOk();
        $this->actingAs($this->ortu)->postJson(route('umpan-balik-saya.store', $f), $answer)->assertRedirect();
        $this->assertDatabaseCount('jawaban_umpan_balik_humas', 2);
    }

    public function test_id_dan_token_malformed_tidak_menimbulkan_error_server(): void
    {
        $this->token($this->ortu);
        foreach (['pengaduan-saya/bukan-id', 'pengaduan-saya/9999999999999999999999999999', 'pengaduan-saya/1/lampiran/bukan-id', 'pertemuan-saya/scan/token-pendek', 'umpan-balik-saya/bukan-id'] as $path) {
            $this->getJson($this->url($path))->assertNotFound();
        }
    }

    private function token(Pengguna $u, array $abilities = ['mobile']): void
    {
        auth()->forgetGuards();
        $this->withToken($u->createToken('Uji Android Humas', $abilities)->plainTextToken);
    }

    private function url(string $path): string
    {
        return '/api/v1/humas/'.$path;
    }

    private function akun(string $role, bool $pegawai = false): Pengguna
    {
        $u = Pengguna::create(['nama' => 'Petugas '.$role, 'username' => 'api.humas.'.Str::uuid(), 'kata_sandi' => 'TesHumas123!', 'aktif' => true, 'wajib_ganti_kata_sandi' => false, 'peran' => 'pegawai', 'pegawai_id' => $pegawai ? Pegawai::create(['nama_lengkap' => 'Petugas '.$role, 'aktif' => true])->id : null]);
        $u->daftarPeran()->attach(Peran::where('kode', $role)->firstOrFail());

        return $u;
    }

    private function orangTua(): Pengguna
    {
        $u = $this->akun('orang_tua');
        $u->update(['nama' => 'IDENTITAS PRIVAT']);
        $w = OrangTuaWali::create(['pengguna_id' => $u->id, 'nama_lengkap' => $u->nama, 'nomor_wa' => '081299999999']);
        $s = Siswa::create(['nama_lengkap' => 'ANAK PRIVAT', 'nisn' => (string) random_int(1000000000, 9999999999), 'aktif' => true]);
        $w->siswa()->attach($s->id, ['hubungan' => 'ibu', 'utama' => true]);
        AnggotaKelas::create(['siswa_id' => $s->id, 'kelas_id' => $this->kelas->id, 'tahun_pelajaran_id' => $this->tahun->id, 'status_keanggotaan' => 'aktif', 'nomor_absen' => AnggotaKelas::count() + 1]);

        return $u->fresh('orangTuaWali');
    }

    private function agendaData(): array
    {
        return ['judul' => 'Pertemuan Orang Tua', 'jenis' => 'orang_tua', 'waktu_mulai' => '2026-10-05T09:00', 'waktu_selesai' => '2026-10-05T12:00', 'tempat' => 'Aula sekolah', 'topik' => 'TOPIK INTERNAL'];
    }

    private function agenda(): AgendaHumas
    {
        return AgendaHumas::create($this->agendaData());
    }

    private function undangan(bool $dibuka = true): AgendaHumas
    {
        $a = $this->agenda();
        $this->token($this->humas);
        $anak = $this->lain->orangTuaWali->siswa()->first();
        AnggotaKelas::where('siswa_id', $anak->id)->update(['status_keanggotaan' => 'keluar']);
        $this->postJson($this->url("agenda/{$a->id}/undangan"), ['tahun_pelajaran_id' => $this->tahun->id, 'cakupan' => 'kelas', 'kelas_ids' => [$this->kelas->id]])->assertOk()->assertJsonPath('data.baru', 1);
        if ($dibuka) {
            $this->patchJson($this->url("agenda/{$a->id}/akses-presensi"), ['dibuka' => true])->assertOk();
        }

        return $a->fresh();
    }

    private function pengaduanData(): array
    {
        return ['token_pembuatan' => (string) Str::uuid(), 'judul' => 'Masukan layanan sekolah', 'jenis' => 'pengaduan', 'kategori' => 'layanan', 'isi' => 'Mohon tindak lanjut perbaikan fasilitas sekolah.', 'rahasiakan_identitas' => true];
    }

    private function buatPengaduan(): PengaduanHumas
    {
        $this->token($this->ortu);
        $id = $this->postJson($this->url('pengaduan-saya'), $this->pengaduanData())->assertCreated()->json('data.id');

        return PengaduanHumas::findOrFail($id);
    }

    private function foto(string $name = 'bukti.jpg'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, file_get_contents(public_path('images/login-sekolah.jpg')));
    }

    private function formData(): array
    {
        return ['token_pembuatan' => (string) Str::uuid(), 'tahun_pelajaran_id' => $this->tahun->id, 'judul' => 'Evaluasi layanan sekolah', 'pengantar' => 'Masukan untuk meningkatkan layanan sekolah.', 'penanggung_jawab' => 'Waka Humas', 'cakupan' => 'seluruh', 'mulai_pada' => '2026-10-05T09:00', 'selesai_pada' => '2026-10-12T12:00', 'pertanyaan' => [['jenis' => 'skala', 'teks' => 'Kejelasan informasi sekolah.', 'wajib' => true], ['jenis' => 'teks', 'teks' => 'Saran perbaikan sekolah.', 'wajib' => false]]];
    }

    private function bukaForm(): UmpanBalikHumas
    {
        $this->token($this->humas);
        $id = $this->postJson($this->url('umpan-balik'), $this->formData())->assertCreated()->json('data.id');
        $f = UmpanBalikHumas::findOrFail($id);
        $this->patchJson($this->url("umpan-balik/$id/status"), ['versi' => $f->versi, 'status' => 'aktif', 'alasan' => 'Periode evaluasi dibuka'])->assertOk();

        return $f->fresh();
    }

    private function jawaban(UmpanBalikHumas $f, int $nilai = 4, string $teks = 'Saran layanan'): array
    {
        return ['jawaban' => $f->pertanyaan()->get()->mapWithKeys(fn ($p) => [$p->id => $p->jenis === 'skala' ? $nilai : $teks])->all()];
    }
}
