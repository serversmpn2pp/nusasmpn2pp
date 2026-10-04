<?php

namespace Tests\Feature;

use App\Models\NotifikasiPengguna;
use App\Models\OrangTuaWali;
use App\Models\Pegawai;
use App\Models\PengaduanHumas;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PengaduanOrangTuaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 10, 5)->setTime(10, 0));
        Storage::fake('local');
        Storage::fake('public');
        $this->withoutMiddleware(ThrottleRequests::class);
    }

    public function test_hanya_akun_orang_tua_aktif_terhubung_yang_bisa_mengakses(): void
    {
        $this->get(route('pengaduan-saya.index'))->assertRedirect(route('login'));
        $this->post(route('pengaduan-saya.store'), $this->data())->assertRedirect(route('login'));
        $ortu = $this->ortu();
        $this->actingAs($ortu);
        $t = $this->buat();
        foreach (['pegawai', 'guru_mapel', 'siswa', 'orang_tua', 'administrator'] as $role) {
            $this->actingAs($this->akun($role))->get(route('pengaduan-saya.index'))->assertForbidden();
            $this->get(route('pengaduan-saya.create'))->assertForbidden();
            $this->get(route('pengaduan-saya.show', $t->id))->assertForbidden();
            $this->postJson(route('pengaduan-saya.store'), $this->data())->assertForbidden();
            $this->informasi($t)->assertForbidden();
        }
        $ortu->update(['aktif' => false]);
        $this->actingAs($ortu)->get(route('pengaduan-saya.index'))->assertForbidden();
        $this->postJson(route('pengaduan-saya.store'), $this->data())->assertForbidden();
        $ortu->update(['aktif' => true]);
        $ortu->orangTuaWali->siswa()->detach();
        $this->actingAs($ortu)->get(route('pengaduan-saya.show', $t->id))->assertForbidden();
        $this->informasi($t)->assertForbidden();
        $this->assertDatabaseCount('pengaduan_humas', 1);
    }

    public function test_identitas_dan_otoritas_diambil_dari_akun_bukan_input(): void
    {
        $ortu = $this->ortu();
        $this->actingAs($ortu);
        $t = $this->buat(['nama_pelapor' => 'Nama palsu', 'kontak_pelapor' => 'Kontak palsu', 'pelapor_pengguna_id' => 999,
            'dibuat_oleh_pengguna_id' => 999, 'status' => 'selesai', 'petugas_pengguna_id' => 999, 'hasil_penanganan' => 'Palsu', 'batas_tanggal' => '2027-01-01',
            'prioritas' => 'tinggi', 'kanal' => 'surat', 'tanggal_diterima' => '2020-01-01', 'anonim' => true, 'versi' => 100, 'lampiran' => [$this->foto()]]);
        $this->assertSame($ortu->id, $t->pelapor_pengguna_id);
        $this->assertSame($ortu->id, $t->dibuat_oleh_pengguna_id);
        $this->assertSame('Pelapor Orang Tua Rahasia', $t->nama_pelapor);
        $this->assertSame('081234567890', $t->kontak_pelapor);
        $this->assertSame('akun_orang_tua', $t->kanal);
        $this->assertSame('2026-10-05', $t->tanggal_diterima->format('Y-m-d'));
        $this->assertSame('normal', $t->prioritas);
        $this->assertSame('baru', $t->status);
        $this->assertSame(0, $t->versi);
        $this->assertFalse($t->anonim);
        $this->assertTrue($t->rahasiakan_identitas);
        $this->assertNull($t->petugas_pengguna_id);
        $this->assertNull($t->hasil_penanganan);
        $this->assertNull($t->batas_tanggal);
        $this->assertSame('orang_tua', $t->lampiran()->first()->asal);
        $this->assertStringNotContainsString('Pelapor Orang Tua Rahasia', DB::table('pengaduan_humas')->first()->nama_pelapor);
        $this->assertStringNotContainsString('081234567890', $t->toJson());
        $this->assertStringNotContainsString('Pelapor Orang Tua Rahasia', $t->riwayat->toJson());
        $this->assertDatabaseCount('dokumen_humas', 0);
        $this->assertCount(0, Storage::disk('public')->allFiles());
        $this->assertStringNotContainsString($t->isi, NotifikasiPengguna::all()->toJson());
    }

    public function test_pembuatan_idempoten_dan_token_akun_lain_tidak_dapat_dipakai(): void
    {
        $a = $this->ortu();
        $b = $this->ortu();
        $this->actingAs($a);
        $data = $this->data() + ['lampiran' => [$this->foto()]];
        $this->postJson(route('pengaduan-saya.store'), $data)->assertOk();
        $this->postJson(route('pengaduan-saya.store'), $data)->assertOk();
        $this->assertDatabaseCount('pengaduan_humas', 1);
        $this->assertDatabaseCount('riwayat_pengaduan_humas', 1);
        $this->assertDatabaseCount('lampiran_pengaduan_humas', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->actingAs($b)->postJson(route('pengaduan-saya.store'), $data)->assertForbidden();
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->actingAs($this->akun('wakil_pimpinan_humas'))->postJson(route('pengaduan-humas.store'), array_replace($this->dataInternal(), ['token_pembuatan' => $data['token_pembuatan']]))->assertForbidden();
    }

    public function test_daftar_filter_statistik_dan_detail_hanya_laporan_sendiri(): void
    {
        $a = $this->ortu();
        $b = $this->ortu();
        $this->actingAs($a);
        $milik = $this->buat();
        $milik->forceFill(['hasil_penanganan' => 'Hasil internal sangat rahasia'])->save();
        $this->actingAs($b);
        $asing = $this->buat(['judul' => 'Laporan lain rahasia', 'lampiran' => [$this->foto()]]);
        $this->actingAs($a);
        $r = $this->get(route('pengaduan-saya.index'))->assertOk()->assertSee($milik->judul)->assertDontSee($asing->judul)->assertDontSee('081234567890');
        $this->assertSame(['total' => 1, 'aktif' => 1, 'selesai' => 0], $r->viewData('statistik'));
        $this->get(route('pengaduan-saya.index', ['kata_kunci' => 'Laporan lain']))->assertOk()->assertDontSee($asing->judul);
        $r = $this->get(route('pengaduan-saya.show', $milik->id))->assertOk()->assertDontSee('Hasil internal sangat rahasia')->assertDontSee('Riwayat penanganan')->assertDontSee('Simpan disposisi');
        $this->assertStringContainsString('no-store', $r->headers->get('Cache-Control'));
        $this->get(route('pengaduan-saya.show', $asing->id))->assertNotFound();
        $this->informasi($asing)->assertNotFound();
        $this->get(route('pengaduan-saya.lampiran', [$asing->id, $asing->lampiran()->first()->id]))->assertNotFound();
        $this->get(route('pengaduan-humas.index'))->assertForbidden();
        $this->get(route('pengaduan-humas.show', $milik))->assertForbidden();
        $this->postJson(route('pengaduan-humas.balasan', $milik), $this->pesan($milik))->assertForbidden();
        $this->delete(route('pengaduan-saya.show', $milik->id))->assertStatus(405);
    }

    public function test_lampiran_orang_tua_tidak_membuka_berkas_internal_atau_tiket_lain(): void
    {
        $ortu = $this->ortu();
        $humas = $this->akun('wakil_pimpinan_humas');
        $this->actingAs($ortu);
        $t = $this->buat(['lampiran' => [$this->foto()]]);
        $file = $t->lampiran()->first();
        $this->get(route('pengaduan-saya.lampiran', [$t->id, $file->id]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('pengaduan-saya.lampiran', [$t->id, $file->id, 'unduh' => 1]))->assertDownload('bukti.jpg');
        $this->actingAs($humas)->postJson(route('pengaduan-humas.lampiran.store', $t), ['versi' => 0, 'catatan_perubahan' => 'Bukti internal penanganan', 'lampiran' => [$this->foto('internal.jpg')]])->assertOk();
        $internal = $t->lampiran()->where('asal', 'internal')->firstOrFail();
        $this->actingAs($ortu)->get(route('pengaduan-saya.show', $t->id))->assertOk()->assertDontSee('internal.jpg')->assertSee('bukti.jpg');
        $this->get(route('pengaduan-saya.lampiran', [$t->id, $internal->id]))->assertNotFound();
        $this->get(route('pengaduan-humas.berkas', [$t, $file]))->assertForbidden();
        $lain = $this->buat(['lampiran' => [$this->foto('lain.jpg')]]);
        $this->get(route('pengaduan-saya.lampiran', [$t->id, $lain->lampiran()->first()->id]))->assertNotFound();
        Storage::disk('local')->delete($file->lokasi_file);
        $this->get(route('pengaduan-saya.lampiran', [$t->id, $file->id]))->assertNotFound();
    }

    public function test_identitas_pelapor_tidak_bocor_ke_petugas_melalui_riwayat(): void
    {
        $ortu = $this->ortu();
        $humas = $this->akun('wakil_pimpinan_humas');
        $petugas = $this->akun('pegawai', true);
        $this->actingAs($ortu);
        $t = $this->buat(['lampiran' => [$this->foto()]]);
        $this->informasi($t)->assertOk();
        $this->actingAs($humas);
        $this->aksi($t, 'disposisi', ['petugas_pengguna_id' => $petugas->id, 'batas_tanggal' => '2026-10-06'])->assertOk();
        $this->get(route('pengaduan-humas.show', $t))->assertOk()->assertSee('Pelapor Orang Tua Rahasia')->assertSee('081234567890')->assertSee('Kirim balasan resmi')->assertDontSee('Koreksi data');
        $this->actingAs($petugas);
        $r = $this->get(route('pengaduan-humas.show', $t))->assertOk()->assertDontSee('Pelapor Orang Tua Rahasia')->assertDontSee('081234567890')->assertDontSee('bukti.jpg')->assertDontSee('Kirim balasan resmi')->assertSee('Informasi pelapor');
        $this->assertNull($r->viewData('riwayat')->firstWhere('aksi', 'Laporan orang tua diterima')->pengguna);
        $this->postJson(route('pengaduan-humas.balasan', $t), $this->pesan($t))->assertForbidden();
        $this->actingAs($this->akun('pimpinan'))->get(route('pengaduan-humas.show', $t))->assertOk()->assertDontSee('Pelapor Orang Tua Rahasia');
    }

    public function test_balasan_resmi_terpisah_dari_catatan_internal_dan_bisa_setelah_selesai(): void
    {
        $ortu = $this->ortu();
        $humas = $this->akun('wakil_pimpinan_humas');
        $this->actingAs($ortu);
        $t = $this->buat();
        $this->actingAs($humas);
        $this->aksi($t, 'selesaikan')->assertJsonValidationErrors('balasan');
        $this->aksi($t, 'tutup')->assertJsonValidationErrors('balasan');
        $data = $this->pesan($t, ['isi_pesan' => 'Balasan resmi yang boleh dibaca orang tua.']);
        $this->postJson(route('pengaduan-humas.balasan', $t), $data)->assertOk();
        $this->postJson(route('pengaduan-humas.balasan', $t), $data)->assertOk();
        $this->assertSame(1, $t->fresh()->versi);
        $this->assertSame(1, $t->pesan()->count());
        $this->aksi($t, 'selesaikan', ['catatan' => 'Catatan rahasia penutupan internal.'])->assertOk();
        $this->postJson(route('pengaduan-humas.balasan', $t), $this->pesan($t, ['isi_pesan' => 'Konfirmasi resmi tambahan setelah selesai.']))->assertOk();
        $this->assertSame('selesai', $t->fresh()->status);
        $this->actingAs($ortu)->get(route('pengaduan-saya.show', $t->id))->assertOk()->assertSee($data['isi_pesan'])->assertSee('Konfirmasi resmi tambahan setelah selesai.')->assertDontSee('Catatan rahasia penutupan internal.')->assertDontSee('Kirim informasi');
        $this->informasi($t)->assertJsonValidationErrors('status');
        $this->actingAs($humas);
        $this->aksi($t, 'buka-kembali')->assertOk();
        $this->actingAs($ortu)->get(route('pengaduan-saya.show', $t->id))->assertOk()->assertSee('Kirim informasi');
        $this->assertStringNotContainsString($data['isi_pesan'], NotifikasiPengguna::all()->toJson());
        $this->assertTrue(NotifikasiPengguna::where('pengguna_id', $ortu->id)->where('judul', 'Balasan Humas diterima')->exists());
        $this->assertTrue(NotifikasiPengguna::where('pengguna_id', $ortu->id)->where('judul', 'Status laporan diperbarui')->exists());
    }

    public function test_informasi_tambahan_menunggu_lock_dan_token_tidak_bisa_dipalsukan(): void
    {
        $ortu = $this->ortu();
        $humas = $this->akun('wakil_pimpinan_humas');
        $petugas = $this->akun('pegawai', true);
        $this->actingAs($ortu);
        $t = $this->buat();
        $asing = $this->buat();
        $this->actingAs($humas);
        $this->aksi($t, 'disposisi', ['petugas_pengguna_id' => $petugas->id, 'batas_tanggal' => '2026-10-06'])->assertOk();
        $this->aksi($t, 'menunggu')->assertOk();
        $this->actingAs($ortu);
        $this->informasi($t, ['versi' => 0])->assertJsonValidationErrors('versi');
        $data = $this->pesan($t) + ['pengguna_id' => $humas->id, 'asal' => 'humas', 'status' => 'selesai'];
        $this->postJson(route('pengaduan-saya.informasi', $t->id), $data)->assertOk();
        $this->postJson(route('pengaduan-saya.informasi', $t->id), $data)->assertOk();
        $this->assertSame('diproses', $t->fresh()->status);
        $this->assertSame('orang_tua', $t->pesan()->first()->asal);
        $this->assertSame($ortu->id, $t->pesan()->first()->pengguna_id);
        $this->assertSame(1, $t->pesan()->count());
        $this->assertTrue(NotifikasiPengguna::where('pengguna_id', $petugas->id)->where('judul', 'Informasi tiket Humas')->exists());
        $this->informasi($asing, ['token_pengiriman' => $data['token_pengiriman']])->assertForbidden();
        $this->actingAs($humas)->postJson(route('pengaduan-humas.balasan', $t), $data)->assertForbidden();
        $this->postJson(route('pengaduan-humas.balasan', $t), $this->pesan($t, ['versi' => 0]))->assertJsonValidationErrors('versi');
        $this->assertSame(1, $t->pesan()->count());
    }

    public function test_validasi_teks_berkas_dan_batas_pengiriman(): void
    {
        $this->actingAs($this->ortu());
        foreach ([['judul' => ''], ['isi' => 'pendek'], ['jenis' => 'tidak_ada'], ['kategori' => 'tidak_ada'], ['rahasiakan_identitas' => 'abc'], ['token_pembuatan' => 'abc']] as $override) {
            $this->postJson(route('pengaduan-saya.store'), array_replace($this->data(), $override))->assertJsonValidationErrors(array_keys($override)[0]);
        }
        foreach ([UploadedFile::fake()->create('bahaya.html', 2, 'text/html'), $this->foto()->size(10241)] as $file) {
            $this->postJson(route('pengaduan-saya.store'), $this->data() + ['lampiran' => [$file]])->assertJsonValidationErrors('lampiran.0');
        }
        $this->postJson(route('pengaduan-saya.store'), $this->data() + ['lampiran' => [$this->foto(), $this->foto(), $this->foto(), $this->foto()]])->assertJsonValidationErrors('lampiran');
        $this->assertCount(0, Storage::disk('local')->allFiles());
        $this->withMiddleware(ThrottleRequests::class);
        for ($i = 0; $i < 10; $i++) {
            $this->postJson(route('pengaduan-saya.store'), $this->data())->assertOk();
        }
        $this->postJson(route('pengaduan-saya.store'), $this->data())->assertStatus(429);
        $this->travel(11)->minutes();
        $this->postJson(route('pengaduan-saya.store'), $this->data())->assertOk();
    }

    public function test_laporan_asli_tidak_dapat_diubah_atau_diubah_menjadi_laporan_internal(): void
    {
        $this->actingAs($this->ortu());
        $t = $this->buat();
        $this->actingAs($this->akun('wakil_pimpinan_humas'));
        $this->get(route('pengaduan-humas.edit', $t))->assertSessionHasErrors('tiket');
        $this->putJson(route('pengaduan-humas.update', $t), $this->dataInternal() + ['versi' => 0, 'catatan_perubahan' => 'Ubah laporan asli.'])->assertJsonValidationErrors('tiket');
        $this->postJson(route('pengaduan-humas.store'), array_replace($this->dataInternal(), ['kanal' => 'akun_orang_tua']))->assertJsonValidationErrors('kanal');
        $this->assertSame('akun_orang_tua', $t->fresh()->kanal);
        $this->assertSame(0, $t->fresh()->versi);
    }

    public function test_tiket_yang_akun_pelapornya_dihapus_tetap_bisa_ditutup_dengan_alasan(): void
    {
        $ortu = $this->ortu();
        $humas = $this->akun('wakil_pimpinan_humas');
        $this->actingAs($ortu);
        $t = $this->buat();
        $ortu->delete();
        $this->assertNull($t->fresh()->pelapor_pengguna_id);
        $this->actingAs($humas);
        $this->aksi($t, 'tutup')->assertOk();
        $this->postJson(route('pengaduan-humas.balasan', $t), $this->pesan($t))->assertNotFound();
    }

    public function test_akun_orang_tua_tetap_dibatasi_meski_diberi_peran_humas_secara_keliru(): void
    {
        $ortu = $this->ortu();
        $this->actingAs($this->ortu());
        $asing = $this->buat();
        $ortu->daftarPeran()->attach(Peran::where('kode', 'wakil_pimpinan_humas')->firstOrFail());
        $this->actingAs($ortu);
        $this->get(route('pengaduan-humas.index'))->assertOk()->assertDontSee($asing->judul);
        $this->get(route('pengaduan-humas.show', $asing))->assertNotFound();
        $this->get(route('pengaduan-humas.create'))->assertForbidden();
        $this->postJson(route('pengaduan-humas.store'), $this->dataInternal())->assertForbidden();
        $this->get(route('pengaduan-saya.show', $asing->id))->assertNotFound();
    }

    public function test_render_halaman_dan_teks_aman_dengan_paginasi(): void
    {
        $ortu = $this->ortu();
        $humas = $this->akun('wakil_pimpinan_humas');
        $petugas = $this->akun('pegawai', true);
        $this->actingAs($ortu);
        $this->capture('form', $this->get(route('pengaduan-saya.create'))->assertOk());
        $this->capture('empty', $this->get(route('pengaduan-saya.index'))->assertOk());
        $t = $this->buat(['judul' => 'Permohonan peningkatan kenyamanan ruang kelas dan perbaikan fasilitas pembelajaran siswa sekolah', 'lampiran' => [$this->foto()]]);
        $this->capture('new', $this->get(route('pengaduan-saya.show', $t->id))->assertOk());
        $this->informasi($t)->assertOk();
        $this->actingAs($humas);
        $this->postJson(route('pengaduan-humas.balasan', $t), $this->pesan($t, ['isi_pesan' => 'Terima kasih. Informasi telah diterima dan sedang ditindaklanjuti.']))->assertOk();
        $this->aksi($t, 'disposisi', ['petugas_pengguna_id' => $petugas->id, 'batas_tanggal' => '2026-10-06'])->assertOk();
        $this->capture('manager', $this->get(route('pengaduan-humas.show', $t))->assertOk());
        $this->actingAs($petugas);
        $this->capture('handler', $this->get(route('pengaduan-humas.show', $t))->assertOk());
        $this->actingAs($ortu);
        $this->capture('active', $this->get(route('pengaduan-saya.show', $t->id))->assertOk());
        $this->capture('index', $this->get(route('pengaduan-saya.index'))->assertOk());
        $this->actingAs($humas);
        $this->aksi($t, 'selesaikan')->assertOk();
        $this->actingAs($ortu);
        $this->capture('closed', $this->get(route('pengaduan-saya.show', $t->id))->assertOk());
        $xss = $this->buat(['judul' => '<script>alert(1)</script>', 'isi' => '<img src=x onerror=alert(1)>']);
        $this->get(route('pengaduan-saya.show', $xss->id))->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        for ($i = 0; $i < 20; $i++) {
            $this->informasi($xss)->assertOk();
        }
        $r = $this->get(route('pengaduan-saya.show', $xss->id));
        $this->assertCount(15, $r->viewData('pesan'));
        $this->assertCount(5, $this->get(route('pengaduan-saya.show', [$xss->id, 'page' => 2]))->viewData('pesan'));
    }

    private function ortu(): Pengguna
    {
        $p = $this->akun('orang_tua');
        $p->update(['nama' => 'Pelapor Orang Tua Rahasia']);
        $wali = OrangTuaWali::create(['pengguna_id' => $p->id, 'nama_lengkap' => $p->nama, 'nomor_wa' => '081234567890']);
        $s = Siswa::create(['nama_lengkap' => 'Anak Pelapor', 'nisn' => (string) random_int(1000000000, 9999999999), 'aktif' => true]);
        $wali->siswa()->attach($s->id, ['hubungan' => 'ibu', 'utama' => true]);

        return $p;
    }

    private function akun(string $role, bool $pegawai = false): Pengguna
    {
        $p = Pengguna::create(['nama' => 'Petugas '.$role, 'username' => 'pengaduan.'.Str::uuid(), 'kata_sandi' => 'TesPengaduan123', 'peran' => 'pegawai', 'aktif' => true,
            'wajib_ganti_kata_sandi' => false, 'pegawai_id' => $pegawai ? Pegawai::create(['nama_lengkap' => 'Petugas '.$role, 'aktif' => true])->id : null]);
        $p->daftarPeran()->attach(Peran::where('kode', $role)->firstOrFail());

        return $p;
    }

    private function data(): array
    {
        return ['token_pembuatan' => (string) Str::uuid(), 'judul' => 'Masukan layanan sekolah', 'jenis' => 'pengaduan', 'kategori' => 'layanan',
            'isi' => 'Mohon tindak lanjut perbaikan fasilitas sekolah.', 'rahasiakan_identitas' => true];
    }

    private function dataInternal(): array
    {
        return $this->data() + ['kanal' => 'tatap_muka', 'tanggal_diterima' => '2026-10-05', 'prioritas' => 'normal', 'anonim' => false, 'nama_pelapor' => 'Manual Humas'];
    }

    private function buat(array $data = []): PengaduanHumas
    {
        $this->postJson(route('pengaduan-saya.store'), array_replace($this->data(), $data))->assertOk();

        return PengaduanHumas::latest('id')->firstOrFail();
    }

    private function pesan(PengaduanHumas $t, array $data = []): array
    {
        return array_replace(['token_pengiriman' => (string) Str::uuid(), 'versi' => $t->fresh()->versi, 'isi_pesan' => 'Informasi tambahan terkait laporan saya.'], $data);
    }

    private function informasi(PengaduanHumas $t, array $data = []): TestResponse
    {
        return $this->postJson(route('pengaduan-saya.informasi', $t->id), $this->pesan($t, $data));
    }

    private function aksi(PengaduanHumas $t, string $aksi, array $data = []): TestResponse
    {
        return $this->postJson(route('pengaduan-humas.tindakan', [$t, $aksi]), array_replace(['versi' => $t->fresh()->versi, 'catatan' => 'Tindak lanjut internal penanganan.'], $data));
    }

    private function foto(string $name = 'bukti.jpg'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, file_get_contents(public_path('images/login-sekolah.jpg')));
    }

    private function capture(string $name, TestResponse $response): void
    {
        if (getenv('NUSA_CAPTURE_PENGADUAN_ORTU_UI') !== '1') {
            return;
        }
        $folder = storage_path('framework/testing/pengaduan-saya');
        if (! is_dir($folder)) {
            mkdir($folder, 0777, true);
        }
        file_put_contents($folder.'/'.$name.'.html', $response->getContent());
    }
}
