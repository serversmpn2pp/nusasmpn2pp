<?php

namespace Tests\Feature;

use App\Models\AgendaHumas;
use App\Models\AnggotaKelas;
use App\Models\Izin;
use App\Models\JawabanUmpanBalikHumas;
use App\Models\Kelas;
use App\Models\NotifikasiPengguna;
use App\Models\OrangTuaWali;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\UmpanBalikHumas;
use App\Services\Humas\UmpanBalikHumasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class UmpanBalikHumasTest extends TestCase
{
    use RefreshDatabase;

    private Pengguna $humas;

    private TahunPelajaran $tahun;

    private Kelas $kelas;

    private Kelas $kelas8;

    private Pengguna $ortu;

    private Pengguna $ortu8;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(10, 0));
        $this->withoutMiddleware(ThrottleRequests::class);
        $this->tahun = TahunPelajaran::create(['nama' => '2026/2027', 'aktif' => true, 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30']);
        $this->kelas = Kelas::create(['nama' => 'VII.A', 'tahun_pelajaran_id' => $this->tahun->id, 'tingkat' => 7, 'aktif' => true]);
        $this->kelas8 = Kelas::create(['nama' => 'VIII.A', 'tahun_pelajaran_id' => $this->tahun->id, 'tingkat' => 8, 'aktif' => true]);
        $this->ortu = $this->orangTua($this->kelas);
        $adik = $this->siswa($this->kelas);
        $this->ortu->orangTuaWali->siswa()->attach($adik->id, ['hubungan' => 'ibu', 'utama' => true]);
        $this->ortu8 = $this->orangTua($this->kelas8);
        $this->siswa($this->kelas);
        $this->humas = $this->akun('wakil_pimpinan_humas');
        $this->actingAs($this->humas);
    }

    public function test_role_sidebar_akses_tamu_dan_readonly(): void
    {
        foreach (['administrator', 'wakil_pimpinan_humas', 'pimpinan'] as $role) {
            $this->actingAs($this->akun($role))->get(route('umpan-balik-humas.index'))->assertOk()->assertSee('Umpan Balik Orang Tua');
            $this->get(route('umpan-balik-humas.create'))->assertStatus($role === 'pimpinan' ? 403 : 200);
        }
        foreach (['pegawai', 'guru_mapel', 'siswa', 'orang_tua', 'satpam'] as $role) {
            $this->actingAs($this->akun($role))->get(route('umpan-balik-humas.index'))->assertForbidden();
        }
        auth()->forgetGuards();
        $this->get(route('umpan-balik-humas.index'))->assertRedirect(route('login'));
        $this->get(route('umpan-balik-saya.index'))->assertRedirect(route('login'));
        $this->actingAs($this->humas);
        $f = $this->buat();
        $this->actingAs($this->akun('pimpinan'))->get(route('umpan-balik-humas.show', $f))->assertOk()->assertDontSee('Edit pertanyaan &amp; sasaran', false);
        $this->ubahStatus($f)->assertForbidden();
    }

    public function test_hanya_orang_tua_aktif_terhubung_dan_tidak_membuka_rekap(): void
    {
        $f = $this->dibuka();
        foreach (['administrator', 'guru_mapel', 'siswa', 'orang_tua'] as $role) {
            $this->actingAs($this->akun($role))->get(route('umpan-balik-saya.index'))->assertForbidden();
            $this->get(route('umpan-balik-saya.show', $f))->assertForbidden();
            $this->kirim($f)->assertForbidden();
        }
        $this->actingAs($this->ortu)->get(route('umpan-balik-saya.index'))->assertOk()->assertSee('Umpan Balik Saya');
        $this->get(route('umpan-balik-humas.show', $f))->assertForbidden();
        $this->get(route('umpan-balik-humas.cetak', $f))->assertForbidden();
        $this->ortu->update(['aktif' => false]);
        $this->actingAs($this->ortu)->get(route('umpan-balik-saya.show', $f))->assertForbidden();
        $this->ortu->update(['aktif' => true]);
        $this->ortu->orangTuaWali->siswa()->detach();
        $this->actingAs($this->ortu)->get(route('umpan-balik-saya.show', $f))->assertForbidden();
    }

    public function test_akun_humas_nonaktif_atau_orang_tua_meski_diberi_izin(): void
    {
        $f = $this->buat();
        $this->humas->update(['aktif' => false]);
        $this->actingAs($this->humas)->get(route('umpan-balik-humas.show', $f))->assertForbidden();
        $this->ortu->daftarPeran()->attach(Peran::where('kode', 'wakil_pimpinan_humas')->first());
        $this->actingAs($this->ortu)->get(route('umpan-balik-humas.index'))->assertForbidden();
        $this->ubahStatus($f)->assertForbidden();
    }

    public function test_draf_idempoten_audit_dan_validasi(): void
    {
        $data = $this->data();
        $this->post(route('umpan-balik-humas.store'), $data)->assertRedirect();
        $this->post(route('umpan-balik-humas.store'), $data)->assertRedirect();
        $this->assertSame(1, UmpanBalikHumas::count());
        $f = UmpanBalikHumas::first();
        $this->assertSame(2, $f->pertanyaan()->count());
        $this->assertSame(1, $f->riwayat()->count());
        $this->actingAs($this->akun('wakil_pimpinan_humas'))->post(route('umpan-balik-humas.store'), $data)->assertForbidden();
        foreach ([['pertanyaan' => []], ['pertanyaan' => [['jenis' => 'bad', 'teks' => 'Pertanyaan', 'wajib' => 1]]], ['selesai_pada' => '2026-10-05T08:00'], ['kelas_ids' => [$this->kelas8->id], 'cakupan' => 'kelas', 'tahun_pelajaran_id' => 999], ['judul' => str_repeat('a', 181)]] as $salah) {
            $this->postJson(route('umpan-balik-humas.store'), array_replace($this->data(), $salah))->assertUnprocessable();
        }
    }

    public function test_update_draf_dan_kunci_instrumen_setelah_dibuka(): void
    {
        $f = $this->buat();
        $idsLama = $f->pertanyaan()->pluck('id');
        $this->put(route('umpan-balik-humas.update', $f), array_replace($this->data(), ['versi' => $f->versi, 'alasan' => 'Pertanyaan diperbarui', 'judul' => 'Evaluasi baru']))->assertRedirect();
        $this->assertSame('Evaluasi baru', $f->fresh()->judul);
        $this->assertNotEquals($idsLama, $f->pertanyaan()->pluck('id'));
        $this->ubahStatus($f)->assertRedirect();
        $this->get(route('umpan-balik-humas.edit', $f))->assertForbidden();
        $this->putJson(route('umpan-balik-humas.update', $f), array_replace($this->data(), ['versi' => $f->fresh()->versi, 'alasan' => 'Coba mengubah instrumen']))->assertUnprocessable();
        $this->assertSame(2, $f->pertanyaan()->count());
        $this->assertSame(2, $f->sasaran()->count());
    }

    public function test_sasaran_snapshot_satu_akun_dua_anak_dan_notifikasi(): void
    {
        $f = $this->dibuka();
        $this->assertSame(2, $f->sasaran()->count());
        $s = $f->sasaran()->where('orang_tua_wali_id', $this->ortu->orangTuaWali->id)->first();
        $this->assertCount(2, $s->siswa_ids);
        $this->assertSame(2, NotifikasiPengguna::where('kunci_unik', 'umpan-balik-'.$f->id.'-dibuka')->count());
        $this->assertStringNotContainsString('IDENTITAS PRIVAT', $s->toJson());
        $baru = $this->orangTua($this->kelas);
        $this->actingAs($baru)->get(route('umpan-balik-saya.show', $f))->assertNotFound();
        $this->kirim($f)->assertNotFound();
        $this->actingAs($this->humas);
        $this->assertSame(2, $f->sasaran()->count());
    }

    public function test_sasaran_tingkat_kelas_dan_agenda(): void
    {
        foreach ([['cakupan' => 'tingkat', 'tingkat' => 7], ['cakupan' => 'kelas', 'kelas_ids' => [$this->kelas->id]]] as $data) {
            $f = $this->dibuka($data);
            $this->assertSame(1, $f->sasaran()->count());
            $this->actingAs($this->ortu8)->get(route('umpan-balik-saya.show', $f))->assertNotFound();
            $this->actingAs($this->humas);
        }
        $a = $this->agenda();
        $a->peserta()->create(['nama' => 'IDENTITAS PRIVAT', 'orang_tua_wali_id' => $this->ortu->orangTuaWali->id, 'anak_undangan' => [['siswa_id' => $this->ortu->orangTuaWali->siswa()->first()->id]]]);
        $f = $this->dibuka(['cakupan' => 'agenda', 'agenda_humas_id' => $a->id]);
        $this->assertSame(1, $f->sasaran()->count());
        $this->actingAs($this->ortu8)->get(route('umpan-balik-saya.show', $f))->assertNotFound();
        $this->actingAs($this->humas);
        $a->update(['status' => 'dibatalkan']);
        $f2 = $this->buat(['cakupan' => 'agenda', 'agenda_humas_id' => $a->id]);
        $this->ubahStatus($f2)->assertUnprocessable();
        $this->assertSame(0, $f2->sasaran()->count());
    }

    public function test_agenda_tetap_memerlukan_izin_sumber_dan_sasaran_kosong(): void
    {
        $a = $this->agenda();
        $this->actingAs($this->terbatas(['umpan_balik_humas.kelola']))->postJson(route('umpan-balik-humas.store'), $this->data(['cakupan' => 'agenda', 'agenda_humas_id' => $a->id]))->assertForbidden();
        $this->actingAs($this->humas);
        $f = $this->buat(['cakupan' => 'agenda', 'agenda_humas_id' => $a->id]);
        $this->ubahStatus($f)->assertUnprocessable();
        $kelas = Kelas::create(['nama' => 'IX.A', 'tingkat' => 9, 'tahun_pelajaran_id' => $this->tahun->id, 'aktif' => true]);
        $f = $this->buat(['cakupan' => 'kelas', 'kelas_ids' => [$kelas->id]]);
        $this->ubahStatus($f)->assertUnprocessable();
    }

    public function test_draf_tidak_tampil_dan_hilang_relasi_sasaran_ditolak(): void
    {
        $f = $this->buat();
        $this->actingAs($this->ortu)->get(route('umpan-balik-saya.show', $f))->assertNotFound();
        $this->get(route('umpan-balik-saya.index', ['tab' => 'semua']))->assertViewHas('daftar', fn ($daftar) => $daftar->isEmpty());
        $this->actingAs($this->humas);
        $this->ubahStatus($f)->assertRedirect();
        $anakLain = $this->siswa($this->kelas8);
        $this->ortu->orangTuaWali->siswa()->detach();
        $this->ortu->orangTuaWali->siswa()->attach($anakLain->id, ['hubungan' => 'ibu', 'utama' => true]);
        $this->actingAs($this->ortu)->get(route('umpan-balik-saya.show', $f))->assertNotFound();
        $this->get(route('umpan-balik-saya.index', ['tab' => 'semua']))->assertViewHas('daftar', fn ($daftar) => $daftar->isEmpty());
        $this->kirim($f)->assertNotFound();
    }

    public function test_waktu_mulai_batas_tepat_tutup_dan_perpanjang(): void
    {
        $f = $this->dibuka(['mulai_pada' => '2026-10-05T11:00', 'selesai_pada' => '2026-10-05T12:00']);
        $this->actingAs($this->ortu);
        $this->kirim($f)->assertUnprocessable();
        $this->travelTo(now()->setTime(11, 0));
        $this->kirim($f)->assertRedirect();
        $this->travelTo(now()->setTime(12, 0));
        $this->actingAs($this->ortu8);
        $this->kirim($f)->assertUnprocessable();
        $this->actingAs($this->humas);
        $this->ubahStatus($f, 'aktif')->assertUnprocessable();
        $this->ubahStatus($f, 'aktif', ['selesai_pada' => '2026-10-05T12:00'])->assertUnprocessable();
        $this->ubahStatus($f, 'aktif', ['selesai_pada' => '2026-10-05T13:00'])->assertRedirect();
        $this->actingAs($this->ortu8);
        $this->kirim($f)->assertRedirect();
        $this->actingAs($this->humas);
        $this->ubahStatus($f, 'ditutup')->assertRedirect();
        $this->ubahStatus($f, 'aktif', ['selesai_pada' => '2026-10-05T14:00'])->assertRedirect();
        $this->assertSame(2, $f->sasaran()->whereNotNull('dikirim_pada')->count());
    }

    public function test_pengiriman_idempoten_identitas_otoritas_immutable_dan_enkripsi(): void
    {
        $f = $this->dibuka();
        $token = (string) Str::uuid();
        $this->actingAs($this->ortu);
        $data = $this->jawaban($f, 4, 'MASUKAN PRIVAT');
        $data += ['token_pengiriman' => $token, 'status' => 'arsip', 'orang_tua_wali_id' => $this->ortu8->orangTuaWali->id, 'dikirim_pada' => '2000-01-01'];
        $this->post(route('umpan-balik-saya.store', $f), $data)->assertRedirect();
        $this->post(route('umpan-balik-saya.store', $f), $data)->assertRedirect();
        $this->assertSame(2, JawabanUmpanBalikHumas::count());
        $s = $f->sasaran()->whereNotNull('dikirim_pada')->first();
        $this->assertSame($this->ortu->orangTuaWali->id, $s->orang_tua_wali_id);
        $this->assertSame('2026-10-05', $s->dikirim_pada->format('Y-m-d'));
        $this->assertSame('aktif', $f->fresh()->status);
        $this->assertStringNotContainsString('MASUKAN PRIVAT', DB::table('jawaban_umpan_balik_humas')->whereNotNull('teks')->value('teks'));
        $this->assertSame('MASUKAN PRIVAT', JawabanUmpanBalikHumas::whereNotNull('teks')->first()->teks);
        $this->kirim($f, 1)->assertUnprocessable();
        $this->actingAs($this->ortu8)->postJson(route('umpan-balik-saya.store', $f), $data)->assertForbidden();
        $this->assertSame(1, $f->sasaran()->whereNotNull('dikirim_pada')->count());
        $this->actingAs($this->humas);
        $this->ubahStatus($f, 'ditutup')->assertRedirect();
        $this->actingAs($this->ortu)->post(route('umpan-balik-saya.store', $f), $data)->assertRedirect();
    }

    public function test_validasi_jawaban_tidak_menerima_soal_asing_atau_nilai_di_luar_skala(): void
    {
        $f = $this->dibuka();
        $lain = $this->dibuka();
        $scale = $f->pertanyaan()->first()->id;
        $asing = $lain->pertanyaan()->first()->id;
        $this->actingAs($this->ortu);
        foreach ([['jawaban' => []], ['jawaban' => [$scale => 5]], ['jawaban' => [$scale => -1]], ['jawaban' => [$scale => ['4']]], ['jawaban' => [$scale => 4, $asing => 2]], ['jawaban' => [$scale => '3.5']]] as $salah) {
            $this->postJson(route('umpan-balik-saya.store', $f), $salah + ['token_pengiriman' => (string) Str::uuid()])->assertUnprocessable();
        }
        $this->assertSame(0, JawabanUmpanBalikHumas::count());
        $this->kirim($f, 0, '')->assertRedirect();
        $this->assertSame(0, JawabanUmpanBalikHumas::first()->nilai);
    }

    public function test_rekap_rata_persentase_dan_tidak_menilai_tanpa_identitas(): void
    {
        $tambahan = [$this->orangTua($this->kelas), $this->orangTua($this->kelas), $this->orangTua($this->kelas)];
        $f = $this->dibuka();
        foreach ([$this->ortu, $this->ortu8, ...$tambahan] as $i => $akun) {
            $this->actingAs($akun);
            $this->kirim($f, [1, 2, 3, 4, 0][$i], 'Masukan '.$i)->assertRedirect();
        }
        $this->actingAs($this->humas);
        $r = $this->get(route('umpan-balik-humas.show', $f))->assertOk()->assertDontSee('IDENTITAS PRIVAT')->assertSee('2,50')->assertSee('50,0%');
        $rekap = $r->viewData('rekap');
        $p = $f->pertanyaan()->first();
        $this->assertSame(5, $rekap['respons']);
        $this->assertSame(4, $rekap['pertanyaan'][$p->id]['menilai']);
        $this->assertSame(2.5, $rekap['pertanyaan'][$p->id]['rata']);
        $this->assertSame(50.0, $rekap['pertanyaan'][$p->id]['puas']);
        $teks = $f->pertanyaan()->where('jenis', 'teks')->first();
        $comments = $this->get(route('umpan-balik-humas.show', [$f, 'pertanyaan_id' => $teks->id]))->assertOk()->assertSee('Masukan 0')->assertDontSee('IDENTITAS PRIVAT');
        $this->assertSame(['teks'], array_keys($comments->viewData('jawabanTeks')->first()->getAttributes()));
        $lain = $this->buat();
        $this->get(route('umpan-balik-humas.show', [$f, 'pertanyaan_id' => $lain->pertanyaan()->where('jenis', 'teks')->first()->id]))->assertNotFound();
        $this->get(route('umpan-balik-humas.show', [$f, 'pertanyaan_id' => $p->id]))->assertNotFound();
    }

    public function test_rekap_kosong_dan_seluruhnya_tidak_menilai_tidak_menjadi_nol(): void
    {
        $f = $this->dibuka();
        $p = $f->pertanyaan()->first();
        $rekap = app(UmpanBalikHumasService::class)->rekap($f);
        $this->assertNull($rekap['pertanyaan'][$p->id]['rata']);
        $this->actingAs($this->ortu);
        $this->kirim($f, 0)->assertRedirect();
        $rekap = app(UmpanBalikHumasService::class)->rekap($f);
        $this->assertNull($rekap['pertanyaan'][$p->id]['rata']);
        $this->assertNull($rekap['pertanyaan'][$p->id]['puas']);
        $this->assertSame(1, $rekap['pertanyaan'][$p->id]['skala'][0]);
    }

    public function test_orang_tua_hanya_melihat_jawaban_sendiri_dan_riwayat_arsip(): void
    {
        $f = $this->dibuka();
        $this->actingAs($this->ortu);
        $this->kirim($f, 3, 'MASUKAN AKUN A')->assertRedirect();
        $this->get(route('umpan-balik-saya.index'))->assertViewHas('daftar', fn ($daftar) => $daftar->isEmpty());
        $this->get(route('umpan-balik-saya.index', ['tab' => 'riwayat']))->assertSee($f->judul);
        $this->actingAs($this->ortu8);
        $this->kirim($f, 4, 'MASUKAN AKUN B')->assertRedirect();
        $this->get(route('umpan-balik-saya.show', $f))->assertSee('MASUKAN AKUN B')->assertDontSee('MASUKAN AKUN A')->assertDontSee('Rekap per pertanyaan');
        $this->actingAs($this->humas);
        $this->ubahStatus($f, 'arsip')->assertRedirect();
        $this->actingAs($this->ortu)->get(route('umpan-balik-saya.show', $f))->assertOk()->assertSee('MASUKAN AKUN A')->assertDontSee('MASUKAN AKUN B');
    }

    public function test_tindak_lanjut_validasi_idempoten_dan_parent_hanya_ringkasan_publik(): void
    {
        $f = $this->dibuka();
        $data = $this->tindakData($f);
        $this->post(route('umpan-balik-humas.tindak.store', $f), $data)->assertRedirect();
        $this->post(route('umpan-balik-humas.tindak.store', $f), $data)->assertRedirect();
        $this->assertSame(1, $f->tindakLanjut()->count());
        $t = $f->tindakLanjut()->first();
        $this->actingAs($this->ortu)->get(route('umpan-balik-saya.show', $f))->assertDontSee('CATATAN INTERNAL PRIVAT')->assertDontSee('Tindakan internal');
        $this->actingAs($this->humas);
        $this->putJson(route('umpan-balik-humas.tindak.update', [$f, $t]), array_replace($data, ['versi' => $f->fresh()->versi, 'alasan' => 'Mencatat pelaksanaan', 'bagikan_ringkasan' => 1, 'ringkasan_publik' => 'HASIL PUBLIK']))->assertUnprocessable();
        $this->put(route('umpan-balik-humas.tindak.update', [$f, $t]), array_replace($data, ['versi' => $f->fresh()->versi, 'alasan' => 'Mencatat pelaksanaan', 'status' => 'selesai', 'hasil' => 'CATATAN INTERNAL PRIVAT', 'bagikan_ringkasan' => 1, 'ringkasan_publik' => 'HASIL PUBLIK']))->assertRedirect();
        $this->assertNotNull($t->fresh()->selesai_pada);
        $this->actingAs($this->ortu)->get(route('umpan-balik-saya.show', $f))->assertSee('HASIL PUBLIK')->assertDontSee('CATATAN INTERNAL PRIVAT')->assertDontSee('Tindakan internal');
        $this->actingAs($this->humas);
        $this->put(route('umpan-balik-humas.tindak.update', [$f, $t]), array_replace($data, ['versi' => $f->fresh()->versi, 'alasan' => 'Perlu pekerjaan tambahan', 'status' => 'diproses', 'bagikan_ringkasan' => 0]))->assertRedirect();
        $this->assertNull($t->fresh()->selesai_pada);
        $this->actingAs($this->ortu)->get(route('umpan-balik-saya.show', $f))->assertDontSee('HASIL PUBLIK');
    }

    public function test_tindak_lanjut_asing_versi_lama_draf_dan_arsip_ditolak(): void
    {
        $f = $this->dibuka();
        $lain = $this->dibuka();
        $data = $this->tindakData($f);
        $this->post(route('umpan-balik-humas.tindak.store', $f), $data)->assertRedirect();
        $t = $f->tindakLanjut()->first();
        $this->putJson(route('umpan-balik-humas.tindak.update', [$f, $t]), $data + ['alasan' => 'Ubah catatan tindak'])->assertUnprocessable();
        $this->putJson(route('umpan-balik-humas.tindak.update', [$lain, $t]), array_replace($this->tindakData($lain), ['alasan' => 'Ubah catatan tindak']))->assertNotFound();
        $this->postJson(route('umpan-balik-humas.tindak.store', $f), array_replace($this->tindakData($f), ['pertanyaan_umpan_balik_humas_id' => $lain->pertanyaan()->first()->id]))->assertUnprocessable();
        $draf = $this->buat();
        $this->postJson(route('umpan-balik-humas.tindak.store', $draf), $this->tindakData($draf))->assertUnprocessable();
        $this->ubahStatus($f, 'arsip')->assertRedirect();
        $this->putJson(route('umpan-balik-humas.tindak.update', [$f, $t]), array_replace($this->tindakData($f), ['alasan' => 'Ubah setelah arsip']))->assertUnprocessable();
    }

    public function test_filter_statistik_status_invalid_cetak_dan_fixture(): void
    {
        $this->capture('empty', $this->get(route('umpan-balik-humas.index'))->assertOk());
        $this->capture('form', $this->get(route('umpan-balik-humas.create'))->assertOk());
        $f = $this->buat(['judul' => 'Evaluasi komunikasi, layanan pendidikan, dan pelibatan orang tua dalam peningkatan mutu sekolah']);
        $this->capture('draft', $this->get(route('umpan-balik-humas.show', $f))->assertOk());
        $this->ubahStatus($f)->assertRedirect();
        $this->actingAs($this->ortu);
        $this->capture('parent-index', $this->get(route('umpan-balik-saya.index'))->assertOk());
        $this->capture('parent-form', $this->get(route('umpan-balik-saya.show', $f))->assertOk());
        $this->kirim($f, 3, '<script>window.injected=1</script>')->assertRedirect();
        $this->capture('parent-sent', $this->get(route('umpan-balik-saya.show', $f))->assertOk()->assertDontSee('<script>window.injected=1</script>', false));
        $this->actingAs($this->humas);
        $this->post(route('umpan-balik-humas.tindak.store', $f), $this->tindakData($f))->assertRedirect();
        $this->capture('index', $this->get(route('umpan-balik-humas.index'))->assertOk());
        $this->capture('show', $this->get(route('umpan-balik-humas.show', $f))->assertOk());
        $p = $f->pertanyaan()->where('jenis', 'teks')->first();
        $this->capture('comments', $this->get(route('umpan-balik-humas.show', [$f, 'pertanyaan_id' => $p->id]))->assertOk()->assertDontSee('<script>window.injected=1</script>', false));
        $this->capture('cetak', $this->get(route('umpan-balik-humas.cetak', $f))->assertOk()->assertSee('REKAP UMPAN BALIK ORANG TUA')->assertDontSee('window.injected'));
        $this->get(route('umpan-balik-humas.index', ['kata_kunci' => 'Tidak cocok']))->assertDontSee($f->judul);
        $this->getJson(route('umpan-balik-humas.index', ['status' => 'bad']))->assertUnprocessable();
        $this->getJson(route('umpan-balik-humas.show', [$f, 'pertanyaan_id' => ['bad']]))->assertUnprocessable();
        $this->ubahStatus($f, 'draf')->assertUnprocessable();
        $this->ubahStatus($f, 'ditutup')->assertRedirect();
        $this->actingAs($this->ortu8);
        $this->capture('parent-closed', $this->get(route('umpan-balik-saya.show', $f))->assertOk()->assertDontSee('Kirim jawaban'));
    }

    public function test_batas_pertanyaan_payload_bersarang_dan_jawaban_teks(): void
    {
        $p = ['jenis' => 'teks', 'teks' => 'Masukan tertulis untuk sekolah.', 'wajib' => 0];
        $this->postJson(route('umpan-balik-humas.store'), $this->data(['pertanyaan' => array_fill(0, 31, $p)]))->assertUnprocessable();
        $this->postJson(route('umpan-balik-humas.store'), $this->data(['cakupan' => 'kelas', 'kelas_ids' => [$this->kelas->id], 'tahun_pelajaran_id' => ['bad']]))->assertUnprocessable();
        $f = $this->dibuka(['pertanyaan' => [$p]]);
        $id = $f->pertanyaan()->first()->id;
        $this->actingAs($this->ortu);
        foreach ([null, '', str_repeat('a', 2001), ['bad']] as $isi) {
            $this->postJson(route('umpan-balik-saya.store', $f), ['token_pengiriman' => (string) Str::uuid(), 'jawaban' => [$id => $isi]])->assertUnprocessable();
        }
        $this->assertSame(0, $f->sasaran()->whereNotNull('dikirim_pada')->count());
        $this->kirim($f, 4, str_repeat('a', 2000))->assertRedirect();
    }

    public function test_status_versi_lama_dan_sasaran_beda_tahun_ditolak(): void
    {
        $f = $this->buat();
        $lama = $f->versi;
        $this->ubahStatus($f)->assertRedirect();
        $this->ubahStatus($f, 'ditutup', ['versi' => $lama])->assertUnprocessable();
        $this->assertSame('aktif', $f->fresh()->status);
        $tahun = TahunPelajaran::create(['nama' => '2027/2028', 'aktif' => false]);
        $this->postJson(route('umpan-balik-humas.store'), $this->data(['tahun_pelajaran_id' => $tahun->id, 'cakupan' => 'kelas', 'kelas_ids' => [$this->kelas->id]]))->assertUnprocessable();
        $this->ubahStatus($f, 'arsip')->assertRedirect();
        $this->actingAs($this->ortu)->get(route('umpan-balik-saya.show', $f))->assertNotFound();
        $this->kirim($f)->assertNotFound();
    }

    public function test_izin_agenda_di_rekap_cetak_edit_dan_tindak_lanjut(): void
    {
        $a = $this->agenda();
        $a->peserta()->create(['nama' => 'IDENTITAS PRIVAT', 'orang_tua_wali_id' => $this->ortu->orangTuaWali->id, 'anak_undangan' => [['siswa_id' => $this->ortu->orangTuaWali->siswa()->first()->id]]]);
        $f = $this->buat(['cakupan' => 'agenda', 'agenda_humas_id' => $a->id]);
        $terbatas = $this->terbatas(['umpan_balik_humas.lihat', 'umpan_balik_humas.kelola']);
        $this->actingAs($terbatas)->get(route('umpan-balik-humas.edit', $f))->assertForbidden();
        $this->putJson(route('umpan-balik-humas.update', $f), $this->data() + ['versi' => $f->versi, 'alasan' => 'Ubah cakupan tanpa izin sumber'])->assertForbidden();
        $this->ubahStatus($f, 'arsip')->assertForbidden();
        $this->actingAs($this->humas);
        $this->ubahStatus($f)->assertRedirect();
        $this->actingAs($terbatas)->get(route('umpan-balik-humas.show', $f))->assertForbidden();
        $this->get(route('umpan-balik-humas.cetak', $f))->assertForbidden();
        $this->postJson(route('umpan-balik-humas.tindak.store', $f), $this->tindakData($f))->assertForbidden();
    }

    public function test_token_tindak_lanjut_tidak_dapat_diganti_saat_edit(): void
    {
        $f = $this->dibuka();
        $data = $this->tindakData($f);
        $this->post(route('umpan-balik-humas.tindak.store', $f), $data)->assertRedirect();
        $t = $f->tindakLanjut()->first();
        $this->put(route('umpan-balik-humas.tindak.update', [$f, $t]), array_replace($data, ['versi' => $f->fresh()->versi, 'alasan' => 'Memperbarui pekerjaan', 'token_pembuatan' => (string) Str::uuid()]))->assertRedirect();
        $this->assertSame($data['token_pembuatan'], $t->fresh()->token_pembuatan);
    }

    public function test_tindak_lanjut_memulihkan_input_saat_validasi_gagal(): void
    {
        $f = $this->dibuka();
        $data = array_replace($this->tindakData($f), ['form_tindak' => 'baru', 'uraian' => 'Perbaikan komunikasi yang sedang disusun', 'batas_tanggal' => 'tidak-valid']);
        $this->from(route('umpan-balik-humas.show', $f))->post(route('umpan-balik-humas.tindak.store', $f), $data)->assertSessionHasErrors('batas_tanggal');
        $this->get(route('umpan-balik-humas.show', $f))->assertOk()->assertSee('Perbaikan komunikasi yang sedang disusun');
        $this->assertSame(0, $f->tindakLanjut()->count());
    }

    private function akun(string $role): Pengguna
    {
        $u = Pengguna::create(['nama' => 'Petugas '.$role, 'username' => 'umpan.'.Str::uuid(), 'kata_sandi' => 'UjiUmpanBalik123', 'aktif' => true, 'wajib_ganti_kata_sandi' => false, 'peran' => 'pegawai']);
        $u->daftarPeran()->attach(Peran::where('kode', $role)->firstOrFail());

        return $u;
    }

    private function terbatas(array $izin): Pengguna
    {
        $role = Peran::create(['nama' => 'Umpan terbatas', 'kode' => 'umpan_'.Str::random(10), 'aktif' => true]);
        $role->izin()->attach(Izin::whereIn('kode', $izin)->pluck('id'));
        $u = $this->akun('pegawai');
        $u->daftarPeran()->sync([$role->id]);

        return $u;
    }

    private function siswa(Kelas $kelas): Siswa
    {
        $s = Siswa::create(['nama_lengkap' => 'ANAK PRIVAT', 'nisn' => (string) random_int(1000000000, 9999999999), 'aktif' => true]);
        AnggotaKelas::create(['siswa_id' => $s->id, 'kelas_id' => $kelas->id, 'tahun_pelajaran_id' => $kelas->tahun_pelajaran_id, 'nomor_absen' => AnggotaKelas::where('kelas_id', $kelas->id)->count() + 1, 'status_keanggotaan' => 'aktif']);

        return $s;
    }

    private function orangTua(Kelas $kelas): Pengguna
    {
        $u = $this->akun('orang_tua');
        $u->update(['nama' => 'IDENTITAS PRIVAT']);
        $w = OrangTuaWali::create(['pengguna_id' => $u->id, 'nama_lengkap' => 'IDENTITAS PRIVAT', 'nomor_wa' => '081299999999']);
        $w->siswa()->attach($this->siswa($kelas)->id, ['hubungan' => 'ibu', 'utama' => true]);

        return $u->fresh('orangTuaWali');
    }

    private function agenda(): AgendaHumas
    {
        return AgendaHumas::create(['judul' => 'Pertemuan Orang Tua', 'jenis' => 'orang_tua', 'waktu_mulai' => '2026-10-04 08:00', 'waktu_selesai' => '2026-10-04 10:00', 'tempat' => 'Aula', 'topik' => 'Komunikasi', 'status' => 'selesai']);
    }

    private function data(array $ubah = []): array
    {
        return array_replace(['token_pembuatan' => (string) Str::uuid(), 'tahun_pelajaran_id' => $this->tahun->id, 'judul' => 'Evaluasi layanan sekolah', 'pengantar' => 'Masukan untuk meningkatkan layanan sekolah.', 'penanggung_jawab' => 'Waka Humas', 'cakupan' => 'seluruh',
            'mulai_pada' => '2026-10-05T09:00', 'selesai_pada' => '2026-10-12T12:00', 'pertanyaan' => [['jenis' => 'skala', 'teks' => 'Kejelasan informasi sekolah.', 'wajib' => 1], ['jenis' => 'teks', 'teks' => 'Saran perbaikan untuk sekolah.', 'wajib' => 0]]], $ubah);
    }

    private function buat(array $ubah = []): UmpanBalikHumas
    {
        $data = $this->data($ubah);
        $this->post(route('umpan-balik-humas.store'), $data)->assertRedirect();

        return UmpanBalikHumas::where('token_pembuatan', $data['token_pembuatan'])->firstOrFail();
    }

    private function dibuka(array $ubah = []): UmpanBalikHumas
    {
        $f = $this->buat($ubah);
        $this->ubahStatus($f)->assertRedirect();

        return $f->fresh();
    }

    private function ubahStatus(UmpanBalikHumas $f, string $status = 'aktif', array $data = []): TestResponse
    {
        return $this->postJson(route('umpan-balik-humas.status', $f), $data + ['versi' => $f->fresh()->versi, 'status' => $status, 'alasan' => 'Periode evaluasi ditetapkan']);
    }

    private function jawaban(UmpanBalikHumas $f, int $nilai = 4, string $teks = 'Saran perbaikan'): array
    {
        $j = [];
        foreach ($f->pertanyaan()->get() as $p) {
            $j[$p->id] = $p->jenis === 'skala' ? $nilai : $teks;
        }

        return ['jawaban' => $j];
    }

    private function kirim(UmpanBalikHumas $f, int $nilai = 4, string $teks = 'Saran perbaikan'): TestResponse
    {
        return $this->postJson(route('umpan-balik-saya.store', $f), $this->jawaban($f, $nilai, $teks) + ['token_pengiriman' => (string) Str::uuid()]);
    }

    private function tindakData(UmpanBalikHumas $f): array
    {
        return ['token_pembuatan' => (string) Str::uuid(), 'versi' => $f->fresh()->versi, 'uraian' => 'Tindakan internal untuk peningkatan layanan komunikasi sekolah', 'penanggung_jawab' => 'Waka Humas', 'batas_tanggal' => '2026-10-04', 'status' => 'diproses', 'hasil' => 'CATATAN INTERNAL PRIVAT', 'bagikan_ringkasan' => 0];
    }

    private function capture(string $nama, TestResponse $r): void
    {
        if (getenv('NUSA_CAPTURE_UMPAN_UI') !== '1') {
            return;
        }
        $folder = storage_path('framework/testing/umpan-balik-humas');
        if (! is_dir($folder)) {
            mkdir($folder, 0777, true);
        }
        file_put_contents($folder.'/'.$nama.'.html', $r->getContent());
    }
}
