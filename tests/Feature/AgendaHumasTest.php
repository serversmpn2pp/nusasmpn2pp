<?php

namespace Tests\Feature;

use App\Models\AgendaHumas;
use App\Models\DokumenHumas;
use App\Models\Izin;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Services\Humas\IngatkanAgendaHumasService;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AgendaHumasTest extends TestCase
{
    use RefreshDatabase;

    public function test_akses_akun_humas_pimpinan_dan_akun_tanpa_izin(): void
    {
        $this->get(route('agenda-humas.index'))->assertRedirect(route('login'));
        $tanpaIzin = $this->akun();
        $this->actingAs($tanpaIzin)->get(route('agenda-humas.index'))->assertForbidden();
        $humas = $this->akun('wakil_pimpinan_humas');
        $this->actingAs($humas)->get(route('agenda-humas.index'))->assertOk()->assertSee('Tambah agenda');
        $this->actingAs($humas)->post(route('agenda-humas.store'), $this->dataAgenda())->assertRedirect();
        $agenda = AgendaHumas::firstOrFail();
        $this->assertSame($humas->id, $agenda->dibuat_oleh_pengguna_id);
        $pimpinan = $this->akun('pimpinan');
        $this->actingAs($pimpinan)->get(route('agenda-humas.show', $agenda))->assertOk()->assertDontSee('Edit agenda');
        $this->actingAs($pimpinan)->put(route('agenda-humas.update', $agenda), $this->dataAgenda())->assertForbidden();
        $this->actingAs($pimpinan)->get(route('agenda-humas.cetak', [$agenda, 'jenis' => 'notulen']))->assertOk();
    }

    public function test_validasi_jadwal_tautan_dan_filter_tanggal(): void
    {
        $this->actingAs($this->akun('wakil_pimpinan_humas'));
        $data = $this->dataAgenda();
        $data['waktu_selesai'] = $data['waktu_mulai'];
        $data['tautan_pertemuan'] = 'javascript:alert(1)';
        $this->post(route('agenda-humas.store'), $data)->assertSessionHasErrors(['waktu_selesai', 'tautan_pertemuan']);
        $this->assertDatabaseCount('agenda_humas', 0);
        $this->get(route('agenda-humas.index', ['sampai' => '2026-10-20']))->assertOk();
        $this->get(route('agenda-humas.index', ['dari' => '2026-10-20', 'sampai' => '2026-10-01']))->assertSessionHasErrors('sampai');
        $agenda = $this->agenda();
        $this->get(route('agenda-humas.index', ['kata_kunci' => 'Orang Tua', 'jenis' => 'orang_tua']))->assertOk()->assertSee($agenda->judul);
        $this->get(route('agenda-humas.index', ['jenis' => 'kemitraan']))->assertOk()->assertDontSee($agenda->judul);
        $judulBerbahaya = '</title><script>alert(1)</script>';
        $agenda->update(['judul' => $judulBerbahaya]);
        $this->get(route('agenda-humas.show', $agenda))->assertOk()
            ->assertDontSee($judulBerbahaya, false)->assertSee(e($judulBerbahaya), false);
    }

    public function test_peserta_kehadiran_dan_identitas_dapat_dikoreksi_tanpa_mengubah_waktu_hadir_pertama(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(9, 0));
        $humas = $this->akun('wakil_pimpinan_humas');
        $agenda = $this->agenda();
        $this->actingAs($humas)->post(route('agenda-humas.peserta.store', $agenda), ['peserta' => [
            ['nama' => 'Wali Aditya', 'instansi' => 'VII.A', 'peran' => 'Orang tua'],
            ['nama' => 'Pengurus Komite', 'instansi' => null, 'peran' => null],
        ]])->assertRedirect();
        $peserta = $agenda->peserta()->firstOrFail();
        $baris = ['id' => $peserta->id, 'status_kehadiran' => 'hadir', 'catatan' => 'Tepat waktu', 'nama' => 'Nama dikoreksi', 'versi_presensi' => 0];
        $this->put(route('agenda-humas.presensi', $agenda), ['jumlah_baris' => 1, 'kehadiran' => [$baris]])->assertRedirect()->assertSessionHasNoErrors();
        $waktuHadir = $peserta->fresh()->hadir_pada->toDateTimeString();
        $this->travel(5)->minutes();
        $baris['versi_presensi'] = $peserta->fresh()->versi_presensi;
        $this->put(route('agenda-humas.presensi', $agenda), ['jumlah_baris' => 1, 'kehadiran' => [$baris]])->assertRedirect();
        $this->assertSame($waktuHadir, $peserta->fresh()->hadir_pada->toDateTimeString());
        $this->assertSame('Nama dikoreksi', $peserta->fresh()->nama);
        $this->delete(route('agenda-humas.peserta.destroy', [$agenda, $peserta]))->assertSessionHasErrors('peserta');
        $baris['status_kehadiran'] = 'izin';
        $baris['versi_presensi'] = $peserta->fresh()->versi_presensi;
        $this->put(route('agenda-humas.presensi', $agenda), ['jumlah_baris' => 1, 'kehadiran' => [$baris]])->assertRedirect();
        $this->assertNull($peserta->fresh()->hadir_pada);
        $this->assertSame($humas->id, $peserta->fresh()->dicatat_oleh_pengguna_id);
    }

    public function test_presensi_menolak_data_terpotong_dan_peserta_agenda_lain_secara_atomik(): void
    {
        $this->actingAs($this->akun('wakil_pimpinan_humas'));
        $agenda = $this->agenda();
        $peserta = $agenda->peserta()->create(['nama' => 'Peserta pertama']);
        $asing = $this->agenda()->peserta()->create(['nama' => 'Peserta agenda lain']);
        $data = ['id' => $peserta->id, 'status_kehadiran' => 'hadir', 'versi_presensi' => 0];
        $this->put(route('agenda-humas.presensi', $agenda), ['jumlah_baris' => 2, 'kehadiran' => [$data]])->assertSessionHasErrors('kehadiran');
        $this->put(route('agenda-humas.presensi', $agenda), ['jumlah_baris' => 2, 'kehadiran' => [$data, ['id' => $asing->id, 'status_kehadiran' => 'hadir', 'versi_presensi' => 0]]])->assertSessionHasErrors('kehadiran');
        $this->assertSame('belum_dicatat', $peserta->fresh()->status_kehadiran);
        $this->delete(route('agenda-humas.peserta.destroy', [$agenda, $asing]))->assertNotFound();
        $this->delete(route('agenda-humas.peserta.destroy', [$agenda, $peserta]))->assertRedirect();
        $this->assertDatabaseMissing('peserta_pertemuan_humas', ['id' => $peserta->id]);
    }

    public function test_agenda_selesai_memerlukan_notulen_dan_agenda_batal_memerlukan_alasan(): void
    {
        $this->actingAs($this->akun('wakil_pimpinan_humas'));
        $agenda = $this->agenda();
        $this->patch(route('agenda-humas.status', $agenda), ['status' => 'selesai'])->assertSessionHasErrors('status');
        $this->put(route('agenda-humas.notulen', $agenda), ['pembahasan' => 'Pembahasan program', 'keputusan' => 'Program disepakati'])->assertRedirect();
        $this->patch(route('agenda-humas.status', $agenda), ['status' => 'selesai'])->assertRedirect();
        $this->assertNotNull($agenda->fresh()->diselesaikan_pada);
        $this->patch(route('agenda-humas.status', $agenda), ['status' => 'dibatalkan'])->assertSessionHasErrors('alasan_pembatalan');
        $this->patch(route('agenda-humas.status', $agenda), ['status' => 'dibatalkan', 'alasan_pembatalan' => 'Penjadwalan ulang'])->assertRedirect();
        $this->post(route('agenda-humas.peserta.store', $agenda), ['peserta' => [['nama' => 'Tamu']]])->assertSessionHasErrors('agenda');
        $this->put(route('agenda-humas.notulen', $agenda), ['pembahasan' => 'Perubahan', 'keputusan' => 'Perubahan'])->assertSessionHasErrors('agenda');
        $this->patch(route('agenda-humas.status', $agenda), ['status' => 'terjadwal'])->assertRedirect();
        $this->assertNull($agenda->fresh()->diselesaikan_pada);
        $this->assertNull($agenda->fresh()->alasan_pembatalan);
    }

    public function test_tindak_lanjut_mencatat_hasil_penyelesaian_dan_tidak_dapat_dipindahkan_ke_agenda_lain(): void
    {
        $this->actingAs($this->akun('wakil_pimpinan_humas'));
        $agenda = $this->agenda();
        $data = ['uraian' => 'Kirim surat ke komite', 'penanggung_jawab' => 'Staf Humas', 'batas_tanggal' => '2026-10-08'];
        $this->post(route('agenda-humas.tindak-lanjut.store', $agenda), $data)->assertRedirect();
        $tugas = $agenda->tindakLanjut()->firstOrFail();
        $this->put(route('agenda-humas.tindak-lanjut.update', [$agenda, $tugas]), $data + ['status' => 'selesai'])->assertSessionHasErrors('catatan');
        $this->put(route('agenda-humas.tindak-lanjut.update', [$this->agenda(), $tugas]), $data + ['status' => 'diproses'])->assertNotFound();
        $this->put(route('agenda-humas.tindak-lanjut.update', [$agenda, $tugas]), $data + ['status' => 'selesai', 'catatan' => 'Surat diterima ketua komite'])->assertRedirect();
        $this->assertNotNull($tugas->fresh()->selesai_pada);
        $this->put(route('agenda-humas.tindak-lanjut.update', [$agenda, $tugas]), $data + ['status' => 'diproses', 'catatan' => 'Perlu revisi'])->assertRedirect();
        $this->assertNull($tugas->fresh()->selesai_pada);
    }

    public function test_penghubung_dokumen_menghormati_izin_arsip_dan_tidak_menghapus_berkas(): void
    {
        $agenda = $this->agenda();
        $dokumen = $this->dokumen();
        $akunTerbatas = $this->akunDenganIzinAgenda();
        $agenda->dokumen()->attach($dokumen);
        $this->actingAs($akunTerbatas)->get(route('agenda-humas.show', [$agenda, 'tab' => 'dokumen']))->assertOk()->assertDontSee($dokumen->judul);
        $this->post(route('agenda-humas.dokumen.store', $agenda), ['dokumen_humas_id' => $dokumen->id])->assertForbidden();
        $this->actingAs($this->akun('wakil_pimpinan_humas'));
        $this->post(route('agenda-humas.dokumen.store', $agenda), ['dokumen_humas_id' => $dokumen->id])->assertRedirect();
        $this->assertDatabaseCount('agenda_humas_dokumen', 1);
        $this->delete(route('agenda-humas.dokumen.destroy', [$agenda, $dokumen]))->assertRedirect();
        $this->assertDatabaseCount('agenda_humas_dokumen', 0);
        $this->assertModelExists($dokumen);
    }

    public function test_upload_dokumen_dari_agenda_terhubung_otomatis_dan_batal_tidak_meninggalkan_berkas(): void
    {
        Storage::fake('local');
        $this->actingAs($this->akun('wakil_pimpinan_humas'));
        $agenda = $this->agenda();
        $data = ['judul' => 'Surat undangan', 'kategori' => 'surat_keluar', 'ingatkan_hari_sebelum' => 30,
            'agenda_humas_id' => $agenda->id, 'berkas' => UploadedFile::fake()->create('undangan.pdf', 20, 'application/pdf')];
        $this->get(route('dokumen-humas.create', ['agenda_humas_id' => $agenda->id]))->assertOk()->assertSee('agenda_humas_id');
        $this->post(route('dokumen-humas.store'), $data)->assertRedirect(route('agenda-humas.show', [$agenda, 'tab' => 'dokumen']));
        $dokumen = $agenda->dokumen()->firstOrFail();
        Storage::disk('local')->assertExists($dokumen->lokasi_file);
        $agenda->update(['status' => 'dibatalkan', 'alasan_pembatalan' => 'Dibatalkan']);
        $data['berkas'] = UploadedFile::fake()->create('baru.pdf', 20, 'application/pdf');
        $this->post(route('dokumen-humas.store'), $data)->assertSessionHasErrors('agenda_humas_id');
        $this->assertDatabaseCount('dokumen_humas', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles('dokumen-humas'));
        $dokumenOnly = $this->akunDenganIzinAgenda(['dokumen_humas.kelola']);
        $this->actingAs($dokumenOnly)->post(route('dokumen-humas.store'), $data)->assertForbidden();
    }

    public function test_pengingat_tidak_berulang_dan_melewati_agenda_batal_tugas_selesai(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 4)->setTime(8, 0));
        $akun = $this->akun('wakil_pimpinan_humas');
        $agenda = $this->agenda();
        $agenda->tindakLanjut()->create(['uraian' => 'Periksa dokumen', 'penanggung_jawab' => 'Humas', 'batas_tanggal' => '2026-10-03']);
        $agenda->tindakLanjut()->create(['uraian' => 'Sudah selesai', 'penanggung_jawab' => 'Humas', 'batas_tanggal' => '2026-10-03', 'status' => 'selesai']);
        $batal = $this->agenda();
        $batal->update(['status' => 'dibatalkan']);
        $batal->tindakLanjut()->create(['uraian' => 'Agenda batal', 'penanggung_jawab' => 'Humas', 'batas_tanggal' => '2026-10-03']);
        $service = app(IngatkanAgendaHumasService::class);
        $jumlahPenerima = app(NotifikasiPenggunaService::class)->penggunaDenganIzin('agenda_humas.kelola')->count();
        $this->assertSame(2 * $jumlahPenerima, $service->kirimPengingat());
        $this->assertSame(0, $service->kirimPengingat());
        $this->assertSame(2, $akun->notifikasiPengguna()->count());
        $agenda->update(['waktu_mulai' => '2026-10-05 07:30:00']);
        $this->assertSame($jumlahPenerima, $service->kirimPengingat());
    }

    public function test_seluruh_tab_render_dan_daftar_hadir_dibagi_24_peserta_per_lembar(): void
    {
        $this->actingAs($this->akun('wakil_pimpinan_humas'));
        $agenda = $this->agenda();
        for ($i = 1; $i <= 51; $i++) {
            $agenda->peserta()->create(['nama' => sprintf('Peserta %02d', $i), 'instansi' => 'Orang tua kelas VII.A', 'peran' => 'Orang tua']);
        }
        $agenda->tindakLanjut()->create(['uraian' => 'Kirim hasil rapat', 'penanggung_jawab' => 'Humas', 'batas_tanggal' => '2026-10-06']);
        $this->capture('index', $this->get(route('agenda-humas.index'))->assertOk()->assertSee($agenda->judul));
        foreach (['ringkasan', 'peserta', 'notulen', 'tindak-lanjut', 'dokumen'] as $tab) {
            $response = $this->get(route('agenda-humas.show', [$agenda, 'tab' => $tab]))->assertOk();
            $this->capture($tab, $response);
        }
        $this->get(route('agenda-humas.show', [$agenda, 'tab' => 'peserta']))->assertSee('Peserta 50')->assertDontSee('Peserta 51');
        $this->get(route('agenda-humas.show', [$agenda, 'tab' => 'peserta', 'halaman_peserta' => 2]))->assertSee('Peserta 51')->assertDontSee('Peserta 50');
        $cetak = $this->get(route('agenda-humas.cetak', [$agenda, 'jenis' => 'daftar-hadir']))->assertOk()->assertSee('Lembar 3 dari 3');
        $this->assertSame(3, substr_count($cetak->getContent(), '<article class="sheet sheet--attendance">'));
        $this->capture('cetak-hadir', $cetak);
        $this->capture('cetak-notulen', $this->get(route('agenda-humas.cetak', [$agenda, 'jenis' => 'notulen']))->assertOk()->assertSee('DRAF NOTULEN'));
        $this->capture('form', $this->get(route('agenda-humas.create'))->assertOk());
        $this->get(route('agenda-humas.edit', $agenda))->assertOk();
    }

    private function akun(?string $role = null): Pengguna
    {
        $akun = Pengguna::create(['nama' => 'Pengguna Humas', 'username' => 'humas.'.uniqid(), 'kata_sandi' => 'TesHumas123',
            'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        if ($role) {
            $akun->daftarPeran()->attach(Peran::where('kode', $role)->firstOrFail());
        }

        return $akun;
    }

    private function akunDenganIzinAgenda(array $kode = ['agenda_humas.lihat', 'agenda_humas.kelola']): Pengguna
    {
        $peran = Peran::create(['kode' => 'humas_terbatas_'.uniqid(), 'nama' => 'Humas terbatas', 'aktif' => true]);
        $peran->izin()->attach(Izin::whereIn('kode', $kode)->pluck('id'));
        $akun = $this->akun();
        $akun->daftarPeran()->attach($peran);

        return $akun;
    }

    private function agenda(): AgendaHumas
    {
        return AgendaHumas::create($this->dataAgenda());
    }

    private function dataAgenda(): array
    {
        return ['judul' => 'Pertemuan Orang Tua Kelas VII', 'jenis' => 'orang_tua', 'waktu_mulai' => '2026-10-05T07:00',
            'waktu_selesai' => '2026-10-05T09:00', 'tempat' => 'Aula SMP Negeri 2 Padang Panjang',
            'sasaran' => 'Orang tua/wali kelas VII', 'pemimpin' => 'Kepala Sekolah', 'notulis' => 'Waka Humas',
            'topik' => 'Evaluasi pembelajaran dan koordinasi kegiatan semester.'];
    }

    private function dokumen(): DokumenHumas
    {
        return DokumenHumas::create(['judul' => 'Dokumen privat rapat', 'kategori' => 'notulen', 'lokasi_file' => 'dokumen-humas/rapat.pdf',
            'nama_file_asli' => 'rapat.pdf', 'tipe_file' => 'application/pdf', 'ukuran_file' => 20, 'status' => 'aktif']);
    }

    private function capture(string $nama, TestResponse $response): void
    {
        if (getenv('NUSA_CAPTURE_AGENDA_UI') !== '1') {
            return;
        }
        $folder = storage_path('framework/testing/agenda-humas');
        if (! is_dir($folder)) {
            mkdir($folder, 0777, true);
        }
        file_put_contents($folder.'/'.$nama.'.html', $response->getContent());
    }
}
