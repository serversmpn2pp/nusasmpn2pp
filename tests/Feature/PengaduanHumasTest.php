<?php

namespace Tests\Feature;

use App\Models\NotifikasiPengguna;
use App\Models\Pegawai;
use App\Models\PengaduanHumas;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PengaduanHumasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(10, 0));
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_izin_role_dan_layanan_tidak_terbuka_untuk_publik(): void
    {
        $this->get(route('pengaduan-humas.index'))->assertRedirect(route('login'));
        $manager = $this->akun('wakil_pimpinan_humas');
        $this->actingAs($manager);
        $t = $this->buat();
        foreach (['siswa', 'orang_tua', 'satpam', 'petugas_kebersihan'] as $role) {
            $this->actingAs($this->akun($role));
            $this->get(route('pengaduan-humas.index'))->assertForbidden();
            $this->postJson(route('pengaduan-humas.store'), $this->data())->assertForbidden();
            $this->get(route('pengaduan-humas.show', $t))->assertForbidden();
        }
        $this->actingAs($this->akun('pimpinan'))->get(route('pengaduan-humas.index'))->assertOk()->assertDontSee('Catat tiket');
        $this->get(route('pengaduan-humas.show', $t))->assertOk()->assertDontSee('Koreksi data')->assertDontSee('Simpan disposisi')->assertDontSee('Pelapor Rahasia');
        $this->get(route('pengaduan-humas.create'))->assertForbidden();
        $this->aksi($t, 'tutup')->assertForbidden();
        $this->actingAs($manager)->get(route('pengaduan-humas.show', $t))->assertOk()->assertSee('Pelapor Rahasia')->assertSee('Simpan disposisi');
        $this->delete(route('pengaduan-humas.show', $t))->assertStatus(405);
    }

    public function test_identitas_terenkripsi_tidak_masuk_daftar_riwayat_atau_notifikasi(): void
    {
        $this->actingAs($this->akun('wakil_pimpinan_humas'));
        $t = $this->buat(['lampiran' => [$this->foto()]]);
        $raw = DB::table('pengaduan_humas')->first();
        $this->assertStringNotContainsString('Pelapor Rahasia', $raw->nama_pelapor);
        $this->assertStringNotContainsString('081234567890', $raw->kontak_pelapor);
        $this->assertSame('Pelapor Rahasia', $t->nama_pelapor);
        $this->assertSame('081234567890', $t->kontak_pelapor);
        $this->assertStringNotContainsString('Pelapor Rahasia', $t->toJson());
        $this->assertStringNotContainsString('081234567890', json_encode($t->riwayat->toArray()));
        $this->assertStringNotContainsString('Pelapor Rahasia', json_encode($t->riwayat->toArray()));
        $this->get(route('pengaduan-humas.index'))->assertOk()->assertDontSee('Pelapor Rahasia')->assertDontSee('081234567890')->assertDontSee('bukti.jpg');
        $this->assertSame('HM-2026-'.str_pad((string) $t->id, 6, '0', STR_PAD_LEFT), $t->nomor);
        $this->assertDatabaseCount('dokumen_humas', 0);
        $this->assertCount(0, Storage::disk('public')->allFiles());
        $this->assertStringNotContainsString($t->judul, NotifikasiPengguna::all()->toJson());
        $this->assertStringNotContainsString($t->isi, NotifikasiPengguna::all()->toJson());
    }

    public function test_pembuatan_idempoten_dan_aktor_status_tidak_bisa_dipalsukan(): void
    {
        $manager = $this->akun('wakil_pimpinan_humas');
        $this->actingAs($manager);
        $data = $this->data() + ['status' => 'selesai', 'versi' => 999, 'petugas_pengguna_id' => $manager->id, 'dibuat_oleh_pengguna_id' => 999, 'hasil_penanganan' => 'Palsu', 'lampiran' => [$this->foto()]];
        $this->postJson(route('pengaduan-humas.store'), $data)->assertOk();
        $this->postJson(route('pengaduan-humas.store'), $data)->assertOk();
        $t = PengaduanHumas::firstOrFail();
        $this->assertSame('baru', $t->status);
        $this->assertSame(0, $t->versi);
        $this->assertSame($manager->id, $t->dibuat_oleh_pengguna_id);
        $this->assertNull($t->petugas_pengguna_id);
        $this->assertNull($t->hasil_penanganan);
        $this->assertDatabaseCount('pengaduan_humas', 1);
        $this->assertDatabaseCount('riwayat_pengaduan_humas', 1);
        $this->assertDatabaseCount('lampiran_pengaduan_humas', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->actingAs($this->akun('wakil_pimpinan_humas'))->postJson(route('pengaduan-humas.store'), $data)->assertForbidden();
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_tiket_tanpa_identitas_mengosongkan_kontak_dan_nama(): void
    {
        $this->actingAs($this->akun('wakil_pimpinan_humas'));
        $t = $this->buat(['anonim' => true]);
        $this->assertNull($t->nama_pelapor);
        $this->assertNull($t->kontak_pelapor);
        $this->get(route('pengaduan-humas.show', $t))->assertOk()->assertSee('Tanpa identitas')->assertDontSee('Pelapor Rahasia');
        $this->postJson(route('pengaduan-humas.store'), array_replace($this->data(), ['anonim' => false, 'nama_pelapor' => null]))->assertJsonValidationErrors('nama_pelapor');
    }

    public function test_scope_petugas_berlaku_pada_tiket_filter_statistik_dan_pencabutan(): void
    {
        $manager = $this->akun('wakil_pimpinan_humas');
        $a = $this->akun('pegawai', true);
        $b = $this->akun('pegawai', true);
        $this->actingAs($manager);
        $t = $this->buat(['lampiran' => [$this->foto()]]);
        $asing = $this->buat(['judul' => 'Tiket asing rahasia']);
        $this->aksi($t, 'disposisi', ['petugas_pengguna_id' => $a->id, 'batas_tanggal' => '2026-10-06'])->assertOk();
        $this->actingAs($a);
        $r = $this->get(route('pengaduan-humas.index', ['status' => 'semua']))->assertOk()->assertSee($t->judul)->assertDontSee($asing->judul)->assertDontSee('Pelapor Rahasia');
        $this->assertSame(1, $r->viewData('statistik')['aktif']);
        $this->get(route('pengaduan-humas.show', $t))->assertOk()->assertDontSee('081234567890')->assertDontSee('bukti.jpg')->assertSee('Ajukan selesai');
        $this->get(route('pengaduan-humas.show', $asing))->assertNotFound();
        $this->aksi($asing, 'proses')->assertNotFound();
        $this->get(route('pengaduan-humas.edit', $t))->assertForbidden();
        $this->get(route('pengaduan-humas.berkas', [$t, $t->lampiran()->first()]))->assertForbidden();
        $this->aksi($t, 'selesaikan')->assertForbidden();
        $this->actingAs($b)->get(route('pengaduan-humas.show', $t))->assertNotFound();
        $this->actingAs($manager);
        $this->aksi($t, 'disposisi', ['petugas_pengguna_id' => $b->id, 'batas_tanggal' => '2026-10-07'])->assertOk();
        $this->actingAs($a)->get(route('pengaduan-humas.show', $t))->assertNotFound();
        $this->get(route('pengaduan-humas.index'))->assertOk()->assertDontSee($t->judul);
        $this->aksi($t, 'proses')->assertNotFound();
        $this->actingAs($b)->get(route('pengaduan-humas.show', $t))->assertOk();
    }

    public function test_disposisi_hanya_pegawai_aktif_berizin_dan_tenggat_valid(): void
    {
        $manager = $this->akun('wakil_pimpinan_humas');
        $mati = $this->akun('pegawai', true);
        $mati->update(['aktif' => false]);
        $pegawaiMati = $this->akun('pegawai', true);
        $pegawaiMati->pegawai->update(['aktif' => false]);
        $tanpaPegawai = $this->akun('pegawai');
        $siswa = $this->akun('siswa', true);
        $this->actingAs($manager);
        $t = $this->buat();
        foreach ([$mati, $pegawaiMati, $tanpaPegawai, $siswa] as $p) {
            $this->aksi($t, 'disposisi', ['petugas_pengguna_id' => $p->id, 'batas_tanggal' => '2026-10-06'])->assertJsonValidationErrors('petugas_pengguna_id');
        }
        $this->aksi($t, 'disposisi', ['petugas_pengguna_id' => $manager->id, 'batas_tanggal' => '2026-10-01'])->assertJsonValidationErrors('batas_tanggal');
        $this->assertSame(0, $t->fresh()->versi);
        $p = $this->akun('pegawai', true);
        $this->aksi($t, 'disposisi', ['petugas_pengguna_id' => $p->id, 'batas_tanggal' => '2026-10-05'])->assertOk();
        $p->pegawai->update(['aktif' => false]);
        $this->actingAs($p)->get(route('pengaduan-humas.show', $t))->assertNotFound();
    }

    public function test_alur_proses_menunggu_verifikasi_selesai_dan_buka_kembali(): void
    {
        $manager = $this->akun('wakil_pimpinan_humas');
        $p = $this->akun('pegawai', true);
        $this->actingAs($manager);
        $t = $this->buat();
        $this->aksi($t, 'disposisi', ['petugas_pengguna_id' => $p->id, 'batas_tanggal' => '2026-10-06'])->assertOk();
        $this->actingAs($p);
        foreach ([['proses', 'diproses'], ['menunggu', 'menunggu'], ['proses', 'diproses'], ['usulkan-selesai', 'verifikasi']] as [$aksi, $status]) {
            $this->aksi($t, $aksi)->assertOk();
            $this->assertSame($status, $t->fresh()->status);
        }
        $this->assertNull($t->fresh()->diselesaikan_pada);
        $this->aksi($t, 'proses')->assertJsonValidationErrors('status');
        $this->actingAs($manager);
        $this->aksi($t, 'buka-kembali', ['catatan' => 'Perbaiki hasil yang belum sesuai.'])->assertOk();
        $this->assertSame('diproses', $t->fresh()->status);
        $this->aksi($t, 'selesaikan', ['catatan' => 'Hasil telah diverifikasi dan ditangani.'])->assertOk();
        $this->assertSame('selesai', $t->fresh()->status);
        $this->assertNotNull($t->fresh()->diselesaikan_pada);
        $this->assertSame('Hasil telah diverifikasi dan ditangani.', $t->fresh()->hasil_penanganan);
        $this->get(route('pengaduan-humas.edit', $t))->assertSessionHasErrors('status');
        $this->aksi($t, 'disposisi', ['petugas_pengguna_id' => $p->id, 'batas_tanggal' => '2026-10-06'])->assertJsonValidationErrors('status');
        $this->aksi($t, 'buka-kembali')->assertOk();
        $this->assertNull($t->fresh()->diselesaikan_pada);
        $this->assertNull($t->fresh()->hasil_penanganan);
        $this->assertTrue($t->riwayat()->where('status', 'selesai')->exists());
    }

    public function test_tutup_tarik_alasan_wajib_dan_optimistic_lock(): void
    {
        $this->actingAs($this->akun('wakil_pimpinan_humas'));
        $t = $this->buat();
        $this->aksi($t, 'tarik')->assertJsonValidationErrors('status');
        $this->aksi($t, 'tutup', ['catatan' => ''])->assertJsonValidationErrors('catatan');
        $this->aksi($t, 'buka-kembali')->assertJsonValidationErrors('status');
        $this->aksi($t, 'tutup', ['catatan' => 'Duplikat tiket yang sudah ditangani.'])->assertOk();
        $this->aksi($t, 'tutup', ['versi' => 0])->assertJsonValidationErrors('versi');
        $this->assertDatabaseCount('riwayat_pengaduan_humas', 2);
        $this->aksi($t, 'buka-kembali')->assertOk();
        $this->assertSame('baru', $t->fresh()->status);
        $p = $this->akun('pegawai', true);
        $this->aksi($t, 'disposisi', ['petugas_pengguna_id' => $p->id, 'batas_tanggal' => '2026-10-05'])->assertOk();
        $this->aksi($t, 'tarik')->assertOk();
        $this->assertNull($t->fresh()->petugas_pengguna_id);
        $this->assertNull($t->fresh()->batas_tanggal);
        $this->actingAs($p)->get(route('pengaduan-humas.show', $t))->assertNotFound();
        $this->postJson(route('pengaduan-humas.tindakan', [$t, 'hapus']))->assertNotFound();
    }

    public function test_koreksi_diaudit_identitas_tidak_direplikasi_dan_status_tidak_terganti(): void
    {
        $this->actingAs($this->akun('wakil_pimpinan_humas'));
        $t = $this->buat();
        $data = $this->editData($t) + ['status' => 'selesai', 'petugas_pengguna_id' => 99];
        $data['judul'] = 'Judul tiket dikoreksi';
        $data['nama_pelapor'] = 'Identitas Baru';
        $this->putJson(route('pengaduan-humas.update', $t), $data)->assertOk();
        $this->assertSame('Judul tiket dikoreksi', $t->fresh()->judul);
        $this->assertSame('baru', $t->fresh()->status);
        $this->assertSame('Identitas Baru', $t->fresh()->nama_pelapor);
        $this->assertStringNotContainsString('Identitas Baru', $t->riwayat()->get()->toJson());
        $this->assertSame('Keluhan layanan sekolah', $t->riwayat()->reorder('id')->first()->snapshot['judul']);
        $this->putJson(route('pengaduan-humas.update', $t), $data)->assertJsonValidationErrors('versi');
        $this->putJson(route('pengaduan-humas.update', $t), $this->editData($t->fresh(), ['catatan_perubahan' => '']))->assertJsonValidationErrors('catatan_perubahan');
    }

    public function test_lampiran_privat_jenis_ukuran_versi_dan_berkas_asing(): void
    {
        $this->actingAs($this->akun('wakil_pimpinan_humas'));
        foreach ([UploadedFile::fake()->create('bahaya.html', 1, 'text/html'), $this->foto()->size(10241), UploadedFile::fake()->createWithContent('bahaya.html', file_get_contents(public_path('images/login-sekolah.jpg')))] as $file) {
            $this->postJson(route('pengaduan-humas.store'), $this->data() + ['lampiran' => [$file]])->assertJsonValidationErrors('lampiran.0');
        }
        $this->postJson(route('pengaduan-humas.store'), $this->data() + ['lampiran' => [$this->foto(), $this->foto(), $this->foto(), $this->foto()]])->assertJsonValidationErrors('lampiran');
        $t = $this->buat(['lampiran' => [$this->foto()]]);
        $f = $t->lampiran()->firstOrFail();
        $r = $this->get(route('pengaduan-humas.berkas', [$t, $f]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $this->get(route('pengaduan-humas.berkas', [$t, $f, 'unduh' => 1]))->assertDownload('bukti.jpg');
        $asing = $this->buat(['lampiran' => [$this->foto()]]);
        $this->get(route('pengaduan-humas.berkas', [$t, $asing->lampiran()->first()]))->assertNotFound();
        $data = ['versi' => 0, 'catatan_perubahan' => 'Lampirkan bukti tambahan.', 'lampiran' => [UploadedFile::fake()->create('bukti.pdf', 20, 'application/pdf')]];
        $this->postJson(route('pengaduan-humas.lampiran.store', $t), $data)->assertOk();
        $this->postJson(route('pengaduan-humas.lampiran.store', $t), $data)->assertJsonValidationErrors('versi');
        $this->assertCount(3, Storage::disk('local')->allFiles());
        $this->assertSame(2, $t->lampiran()->count());
        Storage::disk('local')->delete($f->lokasi_file);
        $this->get(route('pengaduan-humas.berkas', [$t, $f]))->assertNotFound();
        $this->actingAs($this->akun('pimpinan'))->get(route('pengaduan-humas.berkas', [$t, $f]))->assertForbidden();
    }

    public function test_filter_statistik_paginasi_terlambat_dan_tiket_selesai(): void
    {
        $this->actingAs($this->akun('wakil_pimpinan_humas'));
        $t = $this->buat(['judul' => 'Usulan fasilitas belajar', 'jenis' => 'aspirasi', 'kategori' => 'sarpras', 'prioritas' => 'tinggi']);
        $p = $this->akun('pegawai', true);
        $this->aksi($t, 'disposisi', ['petugas_pengguna_id' => $p->id, 'batas_tanggal' => '2026-10-05'])->assertOk();
        $this->travel(1)->days();
        $this->assertTrue($t->fresh()->terlambat());
        $r = $this->get(route('pengaduan-humas.index', ['jenis' => 'aspirasi', 'kategori' => 'sarpras', 'prioritas' => 'tinggi', 'batas' => 'lewat', 'mulai' => '2026-10-01', 'sampai' => '2026-10-06']))->assertOk()->assertSee('Usulan fasilitas belajar');
        $this->assertSame(1, $r->viewData('statistik')['terlambat']);
        $this->get(route('pengaduan-humas.index', ['kata_kunci' => $t->nomor]))->assertOk()->assertSee($t->judul);
        $this->aksi($t, 'selesaikan')->assertOk();
        $this->get(route('pengaduan-humas.index'))->assertOk()->assertDontSee($t->judul);
        $this->get(route('pengaduan-humas.index', ['status' => 'selesai']))->assertOk()->assertSee($t->judul);
        for ($i = 0; $i < 21; $i++) {
            $this->buat(['judul' => 'Laporan tambahan '.$i]);
        }
        $r = $this->get(route('pengaduan-humas.index', ['status' => 'semua']))->assertOk();
        $this->assertSame(22, $r->viewData('daftar')->total());
        $this->assertCount(20, $r->viewData('daftar')->items());
        $this->get(route('pengaduan-humas.index', ['mulai' => '2026-10-06', 'sampai' => '2026-10-01']))->assertSessionHasErrors('sampai');
        $this->get(route('pengaduan-humas.index', ['sampai' => '2026-10-01']))->assertOk()->assertSee('Belum ada tiket');
    }

    public function test_notifikasi_penugasan_dan_tindak_lanjut_tanpa_isi_atau_identitas(): void
    {
        $manager = $this->akun('wakil_pimpinan_humas');
        $p = $this->akun('pegawai', true);
        $lain = $this->akun('pegawai', true);
        $this->actingAs($manager);
        $t = $this->buat();
        $this->aksi($t, 'disposisi', ['petugas_pengguna_id' => $p->id, 'batas_tanggal' => '2026-10-06'])->assertOk();
        $this->assertDatabaseHas('notifikasi_pengguna', ['pengguna_id' => $p->id, 'judul' => 'Pembaruan tiket Humas']);
        $this->assertDatabaseMissing('notifikasi_pengguna', ['pengguna_id' => $lain->id]);
        $this->actingAs($p);
        $this->aksi($t, 'usulkan-selesai', ['catatan' => 'Catatan penanganan sangat rahasia.'])->assertOk();
        $this->assertDatabaseHas('notifikasi_pengguna', ['pengguna_id' => $manager->id, 'judul' => 'Pembaruan tiket Humas']);
        foreach ([$t->judul, $t->isi, 'Pelapor Rahasia', '081234567890', 'Catatan penanganan sangat rahasia.'] as $rahasia) {
            $this->assertStringNotContainsString($rahasia, NotifikasiPengguna::all()->toJson());
        }
    }

    public function test_validasi_konten_dan_riwayat_tidak_mengeksekusi_html(): void
    {
        $this->actingAs($this->akun('wakil_pimpinan_humas'));
        foreach (['judul' => '', 'jenis' => 'aneh', 'kategori' => 'aneh', 'kanal' => 'aneh', 'prioritas' => 'aneh', 'isi' => 'singkat', 'tanggal_diterima' => '2026-10-06', 'anonim' => 'aneh'] as $key => $value) {
            $this->postJson(route('pengaduan-humas.store'), array_replace($this->data(), [$key => $value]))->assertJsonValidationErrors($key);
        }
        $html = '</textarea><script>alert(1)</script>';
        $t = $this->buat(['judul' => $html, 'isi' => $html, 'nama_pelapor' => $html]);
        $this->get(route('pengaduan-humas.show', $t))->assertOk()->assertDontSee($html, false)->assertSee(e($html), false);
        $this->get(route('pengaduan-humas.edit', $t))->assertOk()->assertDontSee($html, false);
    }

    public function test_tampilan_dan_akses_baca_privat(): void
    {
        $manager = $this->akun('wakil_pimpinan_humas');
        $p = $this->akun('pegawai', true);
        $this->actingAs($manager);
        $this->capture('form', $this->get(route('pengaduan-humas.create'))->assertOk());
        $t = $this->buat(['judul' => 'Permohonan peningkatan sarana pembelajaran dan kenyamanan ruang kelas untuk menunjang kegiatan siswa sekolah', 'lampiran' => [$this->foto()]]);
        $this->capture('new', $this->get(route('pengaduan-humas.show', $t))->assertOk());
        $this->capture('edit', $this->get(route('pengaduan-humas.edit', $t))->assertOk());
        $this->aksi($t, 'disposisi', ['petugas_pengguna_id' => $p->id, 'batas_tanggal' => '2026-10-06'])->assertOk();
        $this->capture('assigned', $this->get(route('pengaduan-humas.show', $t))->assertOk());
        $this->actingAs($p);
        $this->capture('handler', $this->get(route('pengaduan-humas.show', $t))->assertOk());
        $this->aksi($t, 'usulkan-selesai')->assertOk();
        $this->actingAs($manager);
        $this->capture('verify', $this->get(route('pengaduan-humas.show', $t))->assertOk());
        $this->capture('index', $this->get(route('pengaduan-humas.index'))->assertOk());
        $this->capture('empty', $this->get(route('pengaduan-humas.index', ['kata_kunci' => 'Tidak ada']))->assertOk());
        $this->aksi($t, 'selesaikan')->assertOk();
        $r = $this->get(route('pengaduan-humas.show', $t))->assertOk();
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $this->capture('closed', $r);
        $this->actingAs($this->akun('pimpinan'));
        $this->capture('readonly', $this->get(route('pengaduan-humas.show', $t))->assertOk()->assertDontSee('Pelapor Rahasia')->assertDontSee('bukti.jpg'));
    }

    public function test_pencabutan_izin_petugas_dan_akun_nonaktif(): void
    {
        $manager = $this->akun('wakil_pimpinan_humas');
        $petugas = $this->akun('pegawai', true);
        $this->actingAs($manager);
        $t = $this->buat();
        $this->aksi($t, 'disposisi', ['petugas_pengguna_id' => $petugas->id, 'batas_tanggal' => '2026-10-06'])->assertOk();
        $petugas->daftarPeran()->detach();
        $this->actingAs($petugas)->get(route('pengaduan-humas.show', $t))->assertForbidden();
        $this->aksi($t, 'proses')->assertForbidden();
        $manager->update(['aktif' => false]);
        $this->actingAs($manager)->get(route('pengaduan-humas.create'))->assertForbidden();
        $this->postJson(route('pengaduan-humas.store'), $this->data())->assertForbidden();
        $this->get(route('pengaduan-humas.show', $t))->assertNotFound();
        $this->assertDatabaseCount('pengaduan_humas', 1);
    }

    public function test_batas_lampiran_dan_tiket_final_tidak_meninggalkan_berkas_baru(): void
    {
        $this->actingAs($this->akun('wakil_pimpinan_humas'));
        $t = $this->buat(['lampiran' => [$this->foto()]]);
        for ($i = 0; $i < 3; $i++) {
            $this->postJson(route('pengaduan-humas.lampiran.store', $t), ['versi' => $t->fresh()->versi, 'catatan_perubahan' => 'Tambahkan bukti penanganan.', 'lampiran' => [$this->foto(), $this->foto(), $this->foto()]])->assertOk();
        }
        $this->postJson(route('pengaduan-humas.lampiran.store', $t), ['versi' => $t->fresh()->versi, 'catatan_perubahan' => 'Bukti melebihi batas.', 'lampiran' => [$this->foto()]])->assertJsonValidationErrors('lampiran');
        $this->assertCount(10, Storage::disk('local')->allFiles());
        $this->assertSame(10, $t->lampiran()->count());
        $this->aksi($t, 'selesaikan')->assertOk();
        $this->postJson(route('pengaduan-humas.lampiran.store', $t), ['versi' => $t->fresh()->versi, 'catatan_perubahan' => 'Bukti setelah selesai.', 'lampiran' => [$this->foto()]])->assertJsonValidationErrors('status');
        $this->assertCount(10, Storage::disk('local')->allFiles());
        $this->assertSame(10, $t->lampiran()->count());
    }

    private function akun(string $role, bool $pegawai = false): Pengguna
    {
        $p = Pengguna::create(['pegawai_id' => $pegawai ? Pegawai::create(['nama_lengkap' => 'Petugas '.$role, 'aktif' => true])->id : null, 'nama' => 'Petugas '.$role,
            'username' => 'aduan.'.Str::uuid(), 'kata_sandi' => 'UjiAduan123', 'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $p->daftarPeran()->attach(Peran::where('kode', $role)->firstOrFail());

        return $p;
    }

    private function data(): array
    {
        return ['token_pembuatan' => (string) Str::uuid(), 'judul' => 'Keluhan layanan sekolah', 'jenis' => 'pengaduan', 'kategori' => 'layanan', 'kanal' => 'tatap_muka',
            'tanggal_diterima' => '2026-10-05', 'isi' => 'Mohon perbaikan layanan yang disampaikan kepada pengelola.', 'anonim' => false, 'nama_pelapor' => 'Pelapor Rahasia', 'kontak_pelapor' => '081234567890', 'prioritas' => 'normal'];
    }

    private function buat(array $data = []): PengaduanHumas
    {
        $this->postJson(route('pengaduan-humas.store'), array_replace($this->data(), $data))->assertOk();

        return PengaduanHumas::latest('id')->firstOrFail();
    }

    private function aksi(PengaduanHumas $t, string $aksi, array $data = []): TestResponse
    {
        return $this->postJson(route('pengaduan-humas.tindakan', [$t, $aksi]), array_replace(['versi' => $t->fresh()->versi, 'catatan' => 'Tindak lanjut penanganan tiket.'], $data));
    }

    private function editData(PengaduanHumas $t, array $data = []): array
    {
        return array_replace($t->only(PengaduanHumas::KOLOM), ['tanggal_diterima' => $t->tanggal_diterima->format('Y-m-d'), 'versi' => $t->versi, 'catatan_perubahan' => 'Koreksi pencatatan data tiket.'], $data);
    }

    private function foto(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('bukti.jpg', file_get_contents(public_path('images/login-sekolah.jpg')));
    }

    private function capture(string $name, TestResponse $response): void
    {
        if (getenv('NUSA_CAPTURE_PENGADUAN_UI') !== '1') {
            return;
        }
        $folder = storage_path('framework/testing/pengaduan-humas');
        if (! is_dir($folder)) {
            mkdir($folder, 0777, true);
        }
        file_put_contents($folder.'/'.$name.'.html', $response->getContent());
    }
}
