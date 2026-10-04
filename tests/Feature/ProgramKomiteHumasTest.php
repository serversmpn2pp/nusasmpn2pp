<?php

namespace Tests\Feature;

use App\Models\AgendaHumas;
use App\Models\Izin;
use App\Models\OrangTuaWali;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PeriodeKomiteHumas;
use App\Models\ProgramKomiteHumas;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ProgramKomiteHumasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(10, 0));
        $this->actingAs($this->akun());
    }

    public function test_akses_humas_pimpinan_dan_akun_tanpa_izin(): void
    {
        $p = $this->periode();
        $program = $this->program($p);
        $this->get(route('komite-humas.show', $p))->assertOk()->assertSee('Program kerja & rapat', false);
        $this->actingAs($this->akun('pimpinan'))->get(route('komite-humas.program.index', $p))->assertOk()->assertDontSee('Tambah program');
        $this->get(route('komite-humas.program.show', [$p, $program]))->assertOk()->assertDontSee('Edit program')->assertDontSee('081234567890');
        $this->get(route('komite-humas.program.create', $p))->assertForbidden();
        $this->post(route('komite-humas.program.store', $p), $this->data($p))->assertForbidden();
        foreach (['pegawai', 'guru_mapel', 'siswa', 'orang_tua'] as $role) {
            $this->actingAs($this->akun($role))->get(route('komite-humas.program.index', $p))->assertForbidden();
            $this->get(route('komite-humas.program.show', [$p, $program]))->assertForbidden();
        }
        auth()->forgetGuards();
        $this->get(route('komite-humas.program.index', $p))->assertRedirect(route('login'));
    }

    public function test_program_idempoten_aktor_server_dan_periode_tetap(): void
    {
        $p = $this->periode();
        $asing = $this->periode();
        $data = $this->data($p) + ['periode_komite_humas_id' => $asing->id, 'dibuat_oleh_pengguna_id' => 999, 'versi' => 99];
        $this->post(route('komite-humas.program.store', $p), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('komite-humas.program.store', $p), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('program_komite_humas', 1);
        $this->assertDatabaseCount('riwayat_program_komite_humas', 1);
        $program = ProgramKomiteHumas::firstOrFail();
        $this->assertSame(0, $program->versi);
        $this->assertSame($p->id, $program->periode_komite_humas_id);
        $this->assertSame(auth()->id(), $program->dibuat_oleh_pengguna_id);
        $this->assertStringNotContainsString('081234567890', $program->riwayat->toJson());
        $this->assertStringNotContainsString($data['token_pembuatan'], $program->toJson());
        $this->post(route('komite-humas.program.store', $asing), $data)->assertNotFound();
        $this->actingAs($this->akun())->post(route('komite-humas.program.store', $p), $data)->assertForbidden();
    }

    public function test_program_hanya_bisa_dibuka_dari_periode_miliknya(): void
    {
        $p = $this->periode();
        $program = $this->program($p);
        $asing = $this->periode();
        $this->get(route('komite-humas.program.show', [$asing, $program]))->assertNotFound();
        $this->get(route('komite-humas.program.edit', [$asing, $program]))->assertNotFound();
        $this->put(route('komite-humas.program.update', [$asing, $program]), $this->editData($program))->assertNotFound();
        $this->post(route('komite-humas.program.rapat.store', [$asing, $program]), $this->linkData($program, $this->agenda()))->assertNotFound();
    }

    public function test_penanggung_jawab_dari_periode_ini_dan_harus_aktif_saat_dipilih(): void
    {
        $p = $this->periode();
        $asing = $this->periode();
        $data = $this->data($p);
        $data['pengurus_komite_humas_id'] = $asing->pengurus()->first()->id;
        $this->postJson(route('komite-humas.program.store', $p), $data)->assertJsonValidationErrors('pengurus_komite_humas_id');
        $pj = $p->pengurus()->first();
        $pj->update(['aktif' => false]);
        $data['pengurus_komite_humas_id'] = $pj->id;
        $this->postJson(route('komite-humas.program.store', $p), $data)->assertJsonValidationErrors('pengurus_komite_humas_id');
        $pj->update(['aktif' => true]);
        $program = $this->program($p);
        $pj->update(['aktif' => false]);
        $this->put(route('komite-humas.program.update', [$p, $program]), $this->editData($program, ['tujuan' => 'Tujuan dikoreksi']))->assertSessionHasNoErrors();
        $this->get(route('komite-humas.program.edit', [$p, $program]))->assertOk()->assertSee('(Tidak aktif)')->assertDontSee('081234567890');
    }

    public function test_tanggal_program_dalam_masa_bakti_dan_batas_inklusif(): void
    {
        $p = $this->periode();
        $this->postJson(route('komite-humas.program.store', $p), $this->data($p, ['tanggal_mulai' => '2025-12-31']))->assertJsonValidationErrors('tanggal_mulai');
        $this->postJson(route('komite-humas.program.store', $p), $this->data($p, ['tanggal_selesai' => '2027-01-01']))->assertJsonValidationErrors('tanggal_mulai');
        $this->postJson(route('komite-humas.program.store', $p), $this->data($p, ['tanggal_selesai' => '2026-09-01']))->assertJsonValidationErrors('tanggal_selesai');
        $this->program($p, ['tanggal_mulai' => '2026-01-01', 'tanggal_selesai' => '2026-12-31']);
        $this->assertDatabaseCount('program_komite_humas', 1);
    }

    public function test_draf_hanya_rencana_dan_selesai_memerlukan_capaian(): void
    {
        $p = $this->periode('draf');
        $this->program($p, ['pengurus_komite_humas_id' => null]);
        $this->postJson(route('komite-humas.program.store', $p), $this->data($p, ['status' => 'berjalan']))->assertJsonValidationErrors('status');
        $p->update(['status' => 'aktif']);
        $this->postJson(route('komite-humas.program.store', $p), $this->data($p, ['status' => 'berjalan', 'pengurus_komite_humas_id' => null]))->assertJsonValidationErrors('pengurus_komite_humas_id');
        $this->postJson(route('komite-humas.program.store', $p), $this->data($p, ['status' => 'selesai']))->assertJsonValidationErrors('capaian');
        $this->postJson(route('komite-humas.program.store', $p), $this->data($p, ['status' => 'dibatalkan']))->assertJsonValidationErrors('catatan_evaluasi');
        $selesai = $this->program($p, ['status' => 'selesai', 'capaian' => 'Seluruh target terlaksana.']);
        $this->assertNotNull($selesai->diselesaikan_pada);
        $this->put(route('komite-humas.program.update', [$p, $selesai]), $this->editData($selesai, ['status' => 'berjalan']))->assertSessionHasNoErrors();
        $this->assertNull($selesai->fresh()->diselesaikan_pada);
    }

    public function test_perubahan_memiliki_riwayat_stale_ditolak_dan_noop_tidak_menambah_versi(): void
    {
        $p = $this->periode();
        $program = $this->program($p);
        $this->put(route('komite-humas.program.update', [$p, $program]), $this->editData($program))->assertSessionHasNoErrors();
        $this->assertSame(0, $program->fresh()->versi);
        $lama = $this->editData($program);
        $this->put(route('komite-humas.program.update', [$p, $program]), $this->editData($program, ['nama' => 'Program hasil koreksi']))->assertSessionHasNoErrors();
        $this->assertSame(1, $program->fresh()->versi);
        $this->assertSame('Program kerja komite', $program->riwayat()->reorder()->oldest('id')->first()->snapshot['nama']);
        $this->putJson(route('komite-humas.program.update', [$p, $program]), $lama)->assertJsonValidationErrors('versi');
        $this->assertSame('Program hasil koreksi', $program->fresh()->nama);
        $this->assertSame(2, $program->riwayat()->count());
    }

    public function test_hubungkan_rapat_dan_lepas_tidak_menghapus_agenda_atau_notulen(): void
    {
        $p = $this->periode();
        $program = $this->program($p);
        $agenda = $this->agenda(['pembahasan' => 'Pembahasan privat rapat', 'keputusan' => 'Keputusan rapat']);
        $this->post(route('komite-humas.program.rapat.store', [$p, $program]), $this->linkData($program, $agenda))->assertSessionHasNoErrors();
        $this->assertSame(1, $program->agenda()->count());
        $this->assertSame(1, $program->fresh()->versi);
        $this->post(route('komite-humas.program.rapat.store', [$p, $program]), $this->linkData($program, $agenda))->assertSessionHasNoErrors();
        $this->assertSame(2, $program->riwayat()->count());
        $this->get(route('agenda-humas.show', $agenda))->assertOk()->assertSee('Program komite:')->assertSee($program->nama);
        $this->delete(route('komite-humas.program.rapat.destroy', [$p, $program, $agenda]), ['versi' => $program->fresh()->versi, 'catatan_perubahan' => 'Agenda salah dipilih.'])->assertSessionHasNoErrors();
        $this->assertSame(0, $program->agenda()->count());
        $this->assertDatabaseHas('agenda_humas', ['id' => $agenda->id, 'keputusan' => 'Keputusan rapat']);
        $this->assertSame($agenda->judul, $program->riwayat()->where('aksi', 'Rapat dihubungkan')->first()->snapshot['rapat'][0]['judul']);
        $this->delete(route('komite-humas.program.rapat.destroy', [$p, $program, $agenda]), ['versi' => $program->fresh()->versi, 'catatan_perubahan' => 'Ulangi pelepasan.'])->assertNotFound();
    }

    public function test_jenis_tanggal_status_rapat_dan_program_batal_diperiksa(): void
    {
        $p = $this->periode();
        $program = $this->program($p);
        foreach ([['jenis' => 'orang_tua'], ['status' => 'dibatalkan'], ['waktu_mulai' => '2027-01-01 10:00', 'waktu_selesai' => '2027-01-01 11:00']] as $data) {
            $a = $this->agenda($data);
            $this->postJson(route('komite-humas.program.rapat.store', [$p, $program]), $this->linkData($program, $a))->assertUnprocessable();
        }
        $selesai = $this->agenda(['status' => 'selesai', 'waktu_mulai' => '2026-12-31 10:00', 'waktu_selesai' => '2026-12-31 11:00']);
        $this->post(route('komite-humas.program.rapat.store', [$p, $program]), $this->linkData($program, $selesai))->assertSessionHasNoErrors();
        $this->assertSame('rencana', $program->fresh()->status);
        $program->update(['status' => 'dibatalkan', 'catatan_evaluasi' => 'Dibatalkan.']);
        $a = $this->agenda();
        $this->postJson(route('komite-humas.program.rapat.store', [$p, $program]), $this->linkData($program, $a))->assertJsonValidationErrors('status');
    }

    public function test_izin_agenda_dan_komite_diperlukan_bersama_untuk_pengaitan(): void
    {
        $p = $this->periode();
        $program = $this->program($p);
        $agenda = $this->agenda();
        foreach ([['komite_humas.kelola'], ['agenda_humas.kelola'], ['komite_humas.lihat', 'agenda_humas.kelola']] as $izin) {
            $this->actingAs($this->akunIzin($izin))->post(route('komite-humas.program.rapat.store', [$p, $program]), $this->linkData($program, $agenda))->assertForbidden();
            $this->get(route('agenda-humas.create', ['program_komite_humas_id' => $program->id]))->assertForbidden();
        }
        $this->assertSame(0, $program->agenda()->count());
    }

    public function test_arsip_hanya_baca_dan_rapat_tidak_bisa_ditambahkan(): void
    {
        $p = $this->periode();
        $program = $this->program($p);
        $agenda = $this->agenda();
        $this->post(route('komite-humas.program.rapat.store', [$p, $program]), $this->linkData($program, $agenda))->assertSessionHasNoErrors();
        $p->update(['status' => 'arsip']);
        $this->get(route('komite-humas.program.index', $p))->assertOk()->assertDontSee('Tambah program');
        $this->get(route('komite-humas.program.show', [$p, $program]))->assertOk()->assertSee($agenda->judul)->assertDontSee('Edit program')->assertDontSee('Buat rapat baru')->assertDontSee('Lepas hubungan rapat');
        $this->putJson(route('komite-humas.program.update', [$p, $program]), $this->editData($program))->assertJsonValidationErrors('periode');
        $this->postJson(route('komite-humas.program.store', $p), $this->data($p))->assertJsonValidationErrors('periode');
        $this->postJson(route('agenda-humas.store'), $this->agendaData() + ['program_komite_humas_id' => $program->id])->assertJsonValidationErrors('program_komite_humas_id');
        $this->assertSame(1, $program->agenda()->count());
    }

    public function test_rapat_baru_dari_program_terkait_otomatis_dan_invalid_atomik(): void
    {
        $p = $this->periode();
        $program = $this->program($p);
        $r = $this->get(route('agenda-humas.create', ['program_komite_humas_id' => $program->id]))->assertOk()->assertSee($program->nama);
        $this->assertSame('komite', $r->viewData('agendaHumas')->jenis);
        $data = $this->agendaData() + ['program_komite_humas_id' => $program->id];
        $this->postJson(route('agenda-humas.store'), array_replace($data, ['jenis' => 'internal']))->assertJsonValidationErrors('agenda_humas_id');
        $this->postJson(route('agenda-humas.store'), array_replace($data, ['waktu_mulai' => '2027-01-01T10:00', 'waktu_selesai' => '2027-01-01T11:00']))->assertJsonValidationErrors('waktu_mulai');
        $this->assertDatabaseCount('agenda_humas', 0);
        $this->post(route('agenda-humas.store'), $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, $program->agenda()->count());
        $this->assertSame(1, $program->fresh()->versi);
    }

    public function test_perubahan_agenda_tidak_merusak_pengaitan_dan_agenda_umum_tetap_bisa_diedit(): void
    {
        $p = $this->periode();
        $program = $this->program($p);
        $a = $this->agenda();
        $this->post(route('komite-humas.program.rapat.store', [$p, $program]), $this->linkData($program, $a))->assertSessionHasNoErrors();
        $this->putJson(route('agenda-humas.update', $a), $this->agendaData(['jenis' => 'internal']))->assertJsonValidationErrors('agenda_humas_id');
        $this->putJson(route('agenda-humas.update', $a), $this->agendaData(['waktu_selesai' => '2027-01-01T11:00']))->assertJsonValidationErrors('waktu_mulai');
        $this->assertSame('komite', $a->fresh()->jenis);
        $this->put(route('agenda-humas.update', $a), $this->agendaData(['judul' => 'Nama rapat dikoreksi']))->assertSessionHasNoErrors();
        $umum = $this->agenda(['jenis' => 'orang_tua']);
        $this->actingAs($this->akunIzin(['agenda_humas.kelola']))->put(route('agenda-humas.update', $umum), $this->agendaData(['jenis' => 'internal']))->assertSessionHasNoErrors();
        $this->assertSame('internal', $umum->fresh()->jenis);
    }

    public function test_masa_bakti_tidak_bisa_dipersempit_melewati_program_atau_rapat(): void
    {
        $p = $this->periode('draf');
        $program = $this->program($p);
        $base = ['nama' => $p->nama, 'tanggal_mulai' => '2026-01-01', 'tanggal_selesai' => '2026-12-31', 'status' => 'draf', 'metode' => 'tetap', 'versi' => 0,
            'catatan_perubahan' => 'Koreksi masa bakti.', 'pengurus' => $p->pengurus->map(fn ($pj) => $pj->only(['id', 'nama', 'jabatan', 'aktif']))->all()];
        $this->putJson(route('komite-humas.update', $p), array_replace($base, ['tanggal_selesai' => '2026-09-30']))->assertJsonValidationErrors('tanggal_mulai');
        $a = $this->agenda(['waktu_mulai' => '2026-12-31 10:00', 'waktu_selesai' => '2026-12-31 11:00']);
        $this->post(route('komite-humas.program.rapat.store', [$p, $program]), $this->linkData($program, $a))->assertSessionHasNoErrors();
        $this->putJson(route('komite-humas.update', $p), array_replace($base, ['tanggal_selesai' => '2026-11-30']))->assertJsonValidationErrors('tanggal_mulai');
        $this->assertSame('2026-12-31', $p->fresh()->tanggal_selesai->format('Y-m-d'));
    }

    public function test_statistik_filter_paginasi_terlambat_dan_pemisahan_periode(): void
    {
        $p = $this->periode();
        $this->program($p, ['nama' => 'Program lewat target', 'tanggal_mulai' => '2026-01-01', 'tanggal_selesai' => '2026-10-04']);
        $this->program($p, ['nama' => 'Program batas hari ini', 'tanggal_selesai' => '2026-10-05']);
        $this->program($p, ['status' => 'selesai', 'capaian' => 'Tercapai.']);
        $this->program($this->periode(), ['nama' => 'Program kepengurusan lain']);
        $r = $this->get(route('komite-humas.program.index', $p))->assertOk()->assertDontSee('Program kepengurusan lain');
        $this->assertSame(1, $r->viewData('terlambat'));
        $this->assertSame(['rencana' => 2, 'berjalan' => 0, 'selesai' => 1, 'dibatalkan' => 0], $r->viewData('statistik'));
        $this->get(route('komite-humas.program.index', [$p, 'terlambat' => 1]))->assertSee('Program lewat target')->assertDontSee('Program batas hari ini');
        $this->get(route('komite-humas.program.index', [$p, 'status' => 'selesai']))->assertDontSee('Program lewat target');
        $this->getJson(route('komite-humas.program.index', [$p, 'status' => 'keliru']))->assertJsonValidationErrors('status');
        for ($i = 0; $i < 20; $i++) {
            $this->program($p, ['nama' => 'Program tambahan '.$i]);
        }
        $this->assertSame(20, $this->get(route('komite-humas.program.index', $p))->viewData('daftar')->count());
        $this->assertSame(3, $this->get(route('komite-humas.program.index', [$p, 'page' => 2]))->viewData('daftar')->count());
    }

    public function test_tanpa_izin_agenda_riwayat_tidak_membocorkan_judul_rapat(): void
    {
        $p = $this->periode();
        $program = $this->program($p);
        $a = $this->agenda(['judul' => 'Judul rapat dibatasi']);
        $this->post(route('komite-humas.program.rapat.store', [$p, $program]), $this->linkData($program, $a))->assertSessionHasNoErrors();
        $this->actingAs($this->akunIzin(['komite_humas.lihat']))->get(route('komite-humas.program.show', [$p, $program]))->assertOk()->assertDontSee('Judul rapat dibatasi')->assertDontSee('Hubungkan rapat')->assertDontSee('081234567890');
        $this->actingAs($this->akunIzin(['agenda_humas.lihat']))->get(route('agenda-humas.show', $a))->assertOk()->assertDontSee('Program komite:');
    }

    public function test_stale_pengaitan_ditolak_dan_pilihan_hanya_rapat_valid(): void
    {
        $p = $this->periode();
        $program = $this->program($p);
        $a = $this->agenda(['judul' => 'Rapat sudah terkait']);
        $lama = $this->linkData($program, $a);
        $this->post(route('komite-humas.program.rapat.store', [$p, $program]), $lama)->assertSessionHasNoErrors();
        $this->postJson(route('komite-humas.program.rapat.store', [$p, $program]), $lama)->assertJsonValidationErrors('versi');
        $this->agenda(['judul' => 'Rapat batal tersembunyi', 'status' => 'dibatalkan']);
        $this->agenda(['judul' => 'Rapat umum tersembunyi', 'jenis' => 'orang_tua']);
        $valid = $this->agenda(['judul' => 'Rapat kandidat cocok']);
        $r = $this->get(route('komite-humas.program.show', [$p, $program, 'cari_rapat' => 'kandidat']))->assertOk();
        $this->assertSame([$valid->id], $r->viewData('pilihanRapat')->pluck('id')->all());
    }

    public function test_xss_dan_capture_tampilan_program(): void
    {
        $p = $this->periode();
        $program = $this->program($p, ['nama' => '<script>alert(1)</script> Program literasi orang tua']);
        $a = $this->agenda(['judul' => 'Rapat evaluasi program literasi']);
        $a->peserta()->create(['nama' => 'Pengurus hadir', 'status_kehadiran' => 'hadir']);
        $a->tindakLanjut()->create(['uraian' => 'Siapkan laporan program', 'penanggung_jawab' => 'Ketua Komite', 'status' => 'belum_mulai']);
        $this->post(route('komite-humas.program.rapat.store', [$p, $program]), $this->linkData($program, $a))->assertSessionHasNoErrors();
        $this->agenda(['judul' => 'Rapat persiapan pemberian apresiasi siswa']);
        $show = $this->get(route('komite-humas.program.show', [$p, $program]))->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee(e($program->nama), false);
        $this->capture('show', $show);
        $this->capture('index', $this->get(route('komite-humas.program.index', $p))->assertOk());
        $this->capture('form', $this->get(route('komite-humas.program.create', $p))->assertOk());
        $this->capture('edit', $this->get(route('komite-humas.program.edit', [$p, $program]))->assertOk());
        $this->capture('agenda', $this->get(route('agenda-humas.create', ['program_komite_humas_id' => $program->id]))->assertOk());
        $this->actingAs($this->akun('pimpinan'));
        $this->capture('readonly', $this->get(route('komite-humas.program.show', [$p, $program]))->assertOk());
        $p->update(['status' => 'arsip']);
        $this->capture('archive', $this->get(route('komite-humas.program.show', [$p, $program]))->assertOk());
        $empty = $this->periode('draf');
        $this->capture('empty', $this->get(route('komite-humas.program.index', $empty))->assertOk());
    }

    public function test_akun_nonaktif_dan_akun_orang_tua_siswa_meski_salah_diberi_peran_humas(): void
    {
        $p = $this->periode();
        $program = $this->program($p);
        $ortu = $this->akun();
        OrangTuaWali::create(['pengguna_id' => $ortu->id, 'nama_lengkap' => 'Wali Siswa']);
        $siswa = $this->akun();
        $siswa->update(['siswa_id' => Siswa::create(['nama_lengkap' => 'Siswa Uji', 'nisn' => '9988776655', 'aktif' => true])->id]);
        $nonaktif = $this->akun();
        $nonaktif->update(['aktif' => false]);
        foreach ([$ortu, $siswa, $nonaktif] as $akun) {
            $this->actingAs($akun)->get(route('komite-humas.program.index', $p))->assertForbidden();
            $this->get(route('komite-humas.program.show', [$p, $program]))->assertForbidden();
            $this->postJson(route('komite-humas.program.store', $p), $this->data($p))->assertForbidden();
        }
        $this->actingAs($ortu)->get(route('agenda-humas.create', ['program_komite_humas_id' => $program->id]))->assertForbidden();
        $this->actingAs($siswa)->postJson(route('agenda-humas.store'), $this->agendaData() + ['program_komite_humas_id' => $program->id])->assertForbidden();
    }

    public function test_validasi_formulir_dan_program_berjalan_mencegah_kepengurusan_kembali_draf(): void
    {
        $p = $this->periode();
        $program = $this->program($p, ['status' => 'berjalan']);
        $data = $this->editData($program, ['versi' => null, 'catatan_perubahan' => '', 'tujuan' => [], 'nama' => str_repeat('a', 181)]);
        $this->putJson(route('komite-humas.program.update', [$p, $program]), $data)->assertJsonValidationErrors(['versi', 'catatan_perubahan', 'tujuan', 'nama']);
        $this->assertSame(0, $program->fresh()->versi);
        $this->postJson(route('komite-humas.program.store', $p), $this->data($p, ['token_pembuatan' => 'tidak-valid']))->assertJsonValidationErrors('token_pembuatan');
        $this->putJson(route('komite-humas.update', $p), ['nama' => $p->nama, 'tanggal_mulai' => '2026-01-01', 'tanggal_selesai' => '2026-12-31',
            'status' => 'draf', 'metode' => 'tetap', 'versi' => 0, 'catatan_perubahan' => 'Ubah menjadi draf.',
            'pengurus' => $p->pengurus->map(fn ($pj) => $pj->only(['id', 'nama', 'jabatan', 'aktif']))->all()])->assertJsonValidationErrors('status');
        $this->assertSame('aktif', $p->fresh()->status);
    }

    private function akun(string $role = 'wakil_pimpinan_humas'): Pengguna
    {
        $p = Pengguna::create(['nama' => 'Petugas '.$role, 'username' => 'program.'.Str::uuid(), 'kata_sandi' => 'UjiProgram123', 'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $p->daftarPeran()->attach(Peran::where('kode', $role)->firstOrFail());

        return $p;
    }

    private function akunIzin(array $izin): Pengguna
    {
        $role = Peran::create(['kode' => 'program_'.Str::random(10), 'nama' => 'Program terbatas', 'aktif' => true]);
        $role->izin()->attach(Izin::whereIn('kode', $izin)->pluck('id'));
        $p = $this->akun('pegawai');
        $p->daftarPeran()->attach($role);

        return $p;
    }

    private function periode(string $status = 'aktif'): PeriodeKomiteHumas
    {
        $p = PeriodeKomiteHumas::create(['token_pembuatan' => Str::uuid(), 'nama' => 'Komite 2026', 'tanggal_mulai' => '2026-01-01', 'tanggal_selesai' => '2026-12-31', 'status' => $status]);
        $p->pengurus()->create(['nama' => 'Ketua Komite', 'jabatan' => 'ketua', 'aktif' => true, 'nomor_telepon' => '081234567890']);

        return $p;
    }

    private function data(PeriodeKomiteHumas $p, array $data = []): array
    {
        return array_replace(['token_pembuatan' => (string) Str::uuid(), 'nama' => 'Program kerja komite', 'tujuan' => 'Menguatkan hubungan sekolah dan orang tua.',
            'target_hasil' => 'Terlaksana dua pertemuan dengan orang tua.', 'tanggal_mulai' => '2026-10-01', 'tanggal_selesai' => '2026-10-31',
            'pengurus_komite_humas_id' => $p->pengurus()->first()->id, 'status' => 'rencana', 'capaian' => null, 'catatan_evaluasi' => null], $data);
    }

    private function program(PeriodeKomiteHumas $p, array $data = []): ProgramKomiteHumas
    {
        $this->post(route('komite-humas.program.store', $p), $this->data($p, $data))->assertRedirect()->assertSessionHasNoErrors();

        return $p->program()->latest('id')->firstOrFail();
    }

    private function editData(ProgramKomiteHumas $program, array $data = []): array
    {
        $program = $program->fresh();

        return array_replace($program->only(ProgramKomiteHumas::KOLOM), ['tanggal_mulai' => $program->tanggal_mulai->format('Y-m-d'), 'tanggal_selesai' => $program->tanggal_selesai->format('Y-m-d'),
            'versi' => $program->versi, 'catatan_perubahan' => 'Koreksi program kerja.'], $data);
    }

    private function linkData(ProgramKomiteHumas $p, AgendaHumas $a): array
    {
        return ['agenda_humas_id' => $a->id, 'versi' => $p->fresh()->versi, 'catatan_perubahan' => 'Rapat membahas program ini.'];
    }

    private function agendaData(array $data = []): array
    {
        return array_replace(['judul' => 'Rapat komite sekolah', 'jenis' => 'komite', 'waktu_mulai' => '2026-10-15T10:00', 'waktu_selesai' => '2026-10-15T11:00',
            'tempat' => 'Ruang pertemuan', 'topik' => 'Pembahasan program kerja komite.'], $data);
    }

    private function agenda(array $data = []): AgendaHumas
    {
        return AgendaHumas::create(array_replace($this->agendaData(), ['status' => 'terjadwal'], $data));
    }

    private function capture(string $name, TestResponse $response): void
    {
        if (getenv('NUSA_CAPTURE_PROGRAM_KOMITE_UI') !== '1') {
            return;
        }
        $folder = storage_path('framework/testing/program-komite-humas');
        if (! is_dir($folder)) {
            mkdir($folder, 0777, true);
        }
        file_put_contents($folder.'/'.$name.'.html', $response->getContent());
    }
}
