<?php

namespace Tests\Feature;

use App\Models\AsetPromosiHumas;
use App\Models\DokumenHumas;
use App\Models\Izin;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PublikasiHumas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class AsetPromosiHumasTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Storage::fake('public');
        Queue::fake();
    }

    public function test_menu_dan_hak_akses_role(): void
    {
        $this->get(route('aset-promosi-humas.index'))->assertRedirect(route('login'));
        foreach (['siswa', 'orang_tua', 'guru_mapel', 'pegawai', 'satpam'] as $role) {
            $this->actingAs($this->akun($role))->get(route('aset-promosi-humas.index'))->assertForbidden();
            $this->post(route('aset-promosi-humas.store'), $this->data())->assertForbidden();
            $this->get(route('aset-promosi-humas.dokumen'))->assertForbidden();
        }
        $this->actingAs($this->akun('pimpinan'))->get(route('aset-promosi-humas.index'))->assertOk()->assertSee('Bank Aset Promosi')->assertDontSee('Tambah aset');
        $this->get(route('aset-promosi-humas.create'))->assertForbidden();
        $this->actingAs($this->akun())->get(route('aset-promosi-humas.index'))->assertOk()->assertSee('Tambah aset');
        $this->actingAs(Pengguna::where('username', 'administrator')->firstOrFail())->get(route('aset-promosi-humas.index'))->assertOk();
    }

    public function test_upload_privat_idempoten_aktor_dan_status_tidak_bisa_dipalsukan(): void
    {
        $humas = $this->akun();
        $this->actingAs($humas);
        $data = array_replace($this->data(), ['metode' => 'unggah', 'tautan' => null, 'berkas' => $this->foto(), 'status' => 'arsip', 'versi' => 99, 'dibuat_oleh_pengguna_id' => 999]);
        $this->postJson(route('aset-promosi-humas.store'), $data)->assertOk()->assertJsonStructure(['redirect', 'pesan']);
        $aset = AsetPromosiHumas::firstOrFail();
        $this->assertSame('aktif', $aset->status);
        $this->assertSame(0, $aset->versi);
        $this->assertSame($humas->id, $aset->dibuat_oleh_pengguna_id);
        $this->assertArrayNotHasKey('token_pembuatan', $aset->toArray());
        $this->postJson(route('aset-promosi-humas.store'), $data)->assertOk();
        $this->assertDatabaseCount('aset_promosi_humas', 1);
        $this->assertDatabaseCount('dokumen_humas', 1);
        $this->assertDatabaseCount('riwayat_dokumen_humas', 1);
        $this->assertDatabaseCount('riwayat_aset_promosi_humas', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles('dokumen-humas'));
        $this->assertCount(0, Storage::disk('public')->allFiles());
        $this->actingAs($this->akun())->postJson(route('aset-promosi-humas.store'), $data)->assertForbidden();
        $this->assertCount(1, Storage::disk('local')->allFiles('dokumen-humas'));
    }

    public function test_dokumen_dihubungkan_tanpa_duplikasi_dan_memakai_versi_tertentu(): void
    {
        $this->actingAs($this->akun());
        $doc = $this->dokumen();
        $aset = $this->buat(['metode' => 'dokumen', 'dokumen_humas_id' => $doc->id, 'tautan' => null]);
        $awal = $aset->riwayat_dokumen_humas_id;
        $this->versiDokumen($doc, 2);
        $this->assertSame($awal, $aset->fresh()->riwayat_dokumen_humas_id);
        $this->assertDatabaseCount('dokumen_humas', 1);
        $this->assertCount(2, Storage::disk('local')->allFiles('dokumen-humas'));
        $this->get(route('aset-promosi-humas.berkas', [$aset, $awal]))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get(route('aset-promosi-humas.berkas', [$aset, $awal, 'unduh' => 1]))->assertDownload('foto-kegiatan.jpg');
        $this->get(route('aset-promosi-humas.berkas', [$aset, $doc->riwayat()->first()]))->assertNotFound();
        $this->put(route('aset-promosi-humas.update', $aset), $this->editData($aset, ['metode' => 'dokumen', 'dokumen_humas_id' => $doc->id, 'catatan_revisi' => 'Perbarui berkas dokumen.']))->assertSessionHasNoErrors();
        $this->assertNotSame($awal, $aset->fresh()->riwayat_dokumen_humas_id);
        $this->get(route('aset-promosi-humas.berkas', [$aset, $awal]))->assertOk();
    }

    public function test_dokumen_arsip_tanpa_riwayat_atau_berkas_hilang_ditolak(): void
    {
        $this->actingAs($this->akun());
        $doc = $this->dokumen();
        $doc->update(['status' => 'arsip']);
        $data = array_replace($this->data(), ['metode' => 'dokumen', 'dokumen_humas_id' => $doc->id, 'tautan' => null]);
        $this->postJson(route('aset-promosi-humas.store'), $data)->assertUnprocessable()->assertJsonValidationErrors('dokumen_humas_id');
        $doc->update(['status' => 'aktif']);
        Storage::disk('local')->delete($doc->lokasi_file);
        $this->postJson(route('aset-promosi-humas.store'), $data)->assertJsonValidationErrors('berkas');
        $doc->riwayat()->delete();
        $this->postJson(route('aset-promosi-humas.store'), $data)->assertJsonValidationErrors('dokumen_humas_id');
        $this->assertDatabaseCount('aset_promosi_humas', 0);
    }

    public function test_tautan_validasi_sumber_tunggal_batas_ukuran_dan_tipe(): void
    {
        $this->actingAs($this->akun());
        foreach (['javascript:alert(1)', 'file:///C:/Windows', 'data:text/html,hello'] as $url) {
            $this->postJson(route('aset-promosi-humas.store'), array_replace($this->data(), ['tautan' => $url]))->assertJsonValidationErrors('tautan');
        }
        $this->postJson(route('aset-promosi-humas.store'), $this->data() + ['berkas' => $this->foto()])->assertJsonValidationErrors('berkas');
        foreach ([UploadedFile::fake()->create('fake.html', 1, 'text/html'), $this->foto()->size(20481)] as $file) {
            $this->postJson(route('aset-promosi-humas.store'), array_replace($this->data(), ['metode' => 'unggah', 'tautan' => null, 'berkas' => $file]))->assertJsonValidationErrors('berkas');
        }
        $aset = $this->buat();
        $this->assertSame('tautan', $aset->sumber);
        $this->assertNull($aset->riwayat_dokumen_humas_id);
        $this->assertCount(0, Storage::disk('local')->allFiles());
    }

    public function test_izin_bank_tidak_membuka_berkas_dokumen_privat(): void
    {
        $this->actingAs($this->akun());
        $aset = $this->buat(['metode' => 'unggah', 'tautan' => null, 'berkas' => $this->foto()]);
        $terbatas = $this->akunIzin(['aset_promosi_humas.lihat', 'aset_promosi_humas.kelola', 'publikasi_humas.kelola']);
        $this->actingAs($terbatas)->get(route('aset-promosi-humas.show', $aset))->assertOk()->assertDontSee('foto-kegiatan.jpg');
        $this->get(route('aset-promosi-humas.index'))->assertOk()->assertDontSee('/berkas/', false);
        $this->get(route('aset-promosi-humas.berkas', [$aset, $aset->berkas]))->assertForbidden();
        $this->get(route('aset-promosi-humas.dokumen'))->assertForbidden();
        $this->postJson(route('aset-promosi-humas.store'), array_replace($this->data(), ['metode' => 'dokumen', 'dokumen_humas_id' => $aset->berkas->dokumen_humas_id, 'tautan' => null]))->assertForbidden();
        $this->postJson(route('aset-promosi-humas.store'), array_replace($this->data(), ['metode' => 'unggah', 'berkas' => $this->foto(), 'tautan' => null]))->assertForbidden();
        $this->postJson(route('publikasi-humas.store'), $this->pubData([$aset->id]))->assertForbidden();
        $this->getJson(route('publikasi-humas.pilihan-aset'))->assertOk()->assertJsonCount(0, 'aset');
        $this->actingAs($this->akunIzin(['publikasi_humas.kelola']))->postJson(route('publikasi-humas.store'), $this->pubData([$aset->id]))->assertForbidden();
        $this->getJson(route('publikasi-humas.pilihan-aset'))->assertForbidden();
    }

    public function test_revisi_terkunci_versi_alasan_dan_berkas_gagal_dibersihkan(): void
    {
        $this->actingAs($this->akun());
        $aset = $this->buat(['metode' => 'unggah', 'tautan' => null, 'berkas' => $this->foto()]);
        $token = $aset->token_pembuatan;
        $awal = $aset->berkas;
        $data = $this->editData($aset, ['metode' => 'unggah', 'berkas' => $this->foto(), 'nama' => 'Foto versi kedua']);
        $this->putJson(route('aset-promosi-humas.update', $aset), $data)->assertJsonValidationErrors('catatan_revisi');
        $data['catatan_revisi'] = 'Foto baru dengan izin penggunaan.';
        $data['token_pembuatan'] = (string) Str::uuid();
        $this->putJson(route('aset-promosi-humas.update', $aset), $data)->assertOk();
        $this->assertSame($token, $aset->fresh()->token_pembuatan);
        $this->assertSame(1, $aset->fresh()->versi);
        $this->assertDatabaseCount('dokumen_humas', 2);
        $this->assertSame('foto-kegiatan.jpg', $awal->dokumen->nama_file_asli);
        $this->putJson(route('aset-promosi-humas.update', $aset), $data)->assertJsonValidationErrors('versi');
        $this->assertCount(2, Storage::disk('local')->allFiles('dokumen-humas'));
        $this->get(route('aset-promosi-humas.berkas', [$aset, $awal]))->assertOk();
        $this->assertSame('Logo sekolah', $aset->riwayat()->where('versi', 0)->first()->snapshot['nama']);
    }

    public function test_publikasi_menyimpan_versi_beku_dan_pembaruan_harus_disengaja(): void
    {
        $this->actingAs($this->akun());
        $aset = $this->buat(['metode' => 'unggah', 'tautan' => null, 'berkas' => $this->foto()]);
        $p = $this->publikasi([$aset->id]);
        $awal = $p->aset()->firstOrFail();
        $this->putJson(route('aset-promosi-humas.update', $aset), $this->editData($aset, ['nama' => 'Logo terbaru', 'metode' => 'unggah', 'berkas' => $this->foto(), 'catatan_revisi' => 'Gunakan logo terbaru.']))->assertOk();
        $this->assertSame('Logo sekolah', $p->aset()->first()->snapshot['nama']);
        $this->putJson(route('publikasi-humas.update', $p), $this->pubData([$aset->id]) + ['versi' => 0])->assertOk();
        $this->assertSame(0, $p->fresh()->versi);
        $this->assertSame($awal->riwayat_dokumen_humas_id, $p->aset()->first()->riwayat_dokumen_humas_id);
        $this->putJson(route('publikasi-humas.update', $p), $this->pubData([$aset->id]) + ['versi' => 0, 'perbarui_aset' => [$aset->id]])->assertOk();
        $this->assertSame('Logo terbaru', $p->aset()->first()->snapshot['nama']);
        $this->assertSame(1, $p->fresh()->versi);
        $this->assertSame('Logo sekolah', $p->riwayat()->where('aksi', 'Draf dibuat')->first()->snapshot['aset'][0]['nama']);
        $this->putJson(route('publikasi-humas.update', $p), $this->pubData([]) + ['versi' => 1])->assertOk();
        $this->assertSame(0, $p->aset()->count());
        $this->assertSame(2, $p->fresh()->versi);
        $this->get(route('aset-promosi-humas.berkas', [$aset, $awal->riwayat_dokumen_humas_id]))->assertOk();
    }

    public function test_aset_arsip_tidak_bisa_dipilih_baru_tetapi_riwayat_tetap_tersedia(): void
    {
        $this->actingAs($this->akun());
        $aset = $this->buat();
        $p = $this->publikasi([$aset->id]);
        $this->putJson(route('aset-promosi-humas.update', $aset), $this->editData($aset, ['status' => 'arsip']))->assertOk();
        $this->postJson(route('publikasi-humas.store'), $this->pubData([$aset->id]))->assertJsonValidationErrors('aset_ids');
        $this->putJson(route('publikasi-humas.update', $p), $this->pubData([$aset->id]) + ['versi' => 0])->assertOk();
        $this->putJson(route('publikasi-humas.update', $p), $this->pubData([$aset->id]) + ['versi' => 0, 'perbarui_aset' => [$aset->id]])->assertJsonValidationErrors('aset_ids');
        $this->get(route('aset-promosi-humas.index'))->assertOk()->assertDontSee('Logo sekolah');
        $this->get(route('aset-promosi-humas.index', ['status' => 'arsip']))->assertOk()->assertSee('Logo sekolah');
        $this->get(route('publikasi-humas.show', $p))->assertOk()->assertSee('Logo sekolah');
        $this->assertDatabaseCount('publikasi_humas', 1);
    }

    public function test_versi_aset_pengajuan_dan_persetujuan_dikunci(): void
    {
        $humas = $this->akun();
        $pimpinan = $this->akun('pimpinan');
        $this->actingAs($humas);
        $aset = $this->buat(['metode' => 'unggah', 'tautan' => null, 'berkas' => $this->foto()]);
        $p = $this->publikasi([$aset->id]);
        $awal = $p->aset()->first();
        $this->postJson(route('publikasi-humas.tindakan', [$p, 'ajukan']), ['versi' => 0])->assertOk();
        $this->putJson(route('aset-promosi-humas.update', $aset), $this->editData($aset, ['nama' => 'Logo telah diperbarui']))->assertOk();
        $this->putJson(route('publikasi-humas.update', $p), $this->pubData([$aset->id]) + ['versi' => 1, 'perbarui_aset' => [$aset->id]])->assertJsonValidationErrors('status');
        $this->actingAs($pimpinan)->postJson(route('publikasi-humas.pemeriksaan', [$p, 'setujui']), ['versi' => 1])->assertOk();
        $snapshot = $p->riwayat()->where('aksi', 'Konten disetujui')->first()->snapshot['aset'][0];
        $this->assertSame('Logo sekolah', $snapshot['nama']);
        $this->assertSame($awal->riwayat_dokumen_humas_id, $snapshot['berkas_id']);
        $this->get(route('publikasi-humas.show', $p))->assertOk()->assertSee('Logo sekolah')->assertDontSee('Logo telah diperbarui');
    }

    public function test_berkas_aset_hilang_menghalangi_pengajuan(): void
    {
        $this->actingAs($this->akun());
        $aset = $this->buat(['metode' => 'unggah', 'tautan' => null, 'berkas' => $this->foto()]);
        $p = $this->publikasi([$aset->id]);
        Storage::disk('local')->delete($aset->berkas->lokasi_file);
        $this->postJson(route('publikasi-humas.tindakan', [$p, 'ajukan']), ['versi' => 0])->assertJsonValidationErrors('berkas');
        $this->assertSame('draf', $p->fresh()->status);
    }

    public function test_validasi_pilihan_aset_dan_payload_lama_tidak_menghapus_aset(): void
    {
        $this->actingAs($this->akun());
        $aset = $this->buat();
        $p = $this->publikasi([$aset->id]);
        $data = array_replace(collect($this->pubData([]))->except(['aset_dikirim', 'aset_ids'])->all(), ['versi' => 0, 'judul' => 'Naskah diperbarui']);
        $this->putJson(route('publikasi-humas.update', $p), $data)->assertOk();
        $this->assertSame(1, $p->aset()->count());
        $this->postJson(route('publikasi-humas.store'), $this->pubData([99999]))->assertJsonValidationErrors('aset_ids.0');
        $this->postJson(route('publikasi-humas.store'), $this->pubData([$aset->id, $aset->id]))->assertJsonValidationErrors('aset_ids.0');
        $this->postJson(route('publikasi-humas.store'), $this->pubData(array_fill(0, 11, $aset->id)))->assertJsonValidationErrors('aset_ids');
        $this->putJson(route('publikasi-humas.update', $p), $this->pubData([]) + ['versi' => 1, 'perbarui_aset' => [$aset->id]])->assertJsonValidationErrors('perbarui_aset');
        $this->assertSame(1, $p->aset()->count());
    }

    public function test_filter_pencarian_paginasi_escape_html_dan_tampilan(): void
    {
        $this->actingAs($this->akun());
        $this->capture('form', $this->get(route('aset-promosi-humas.create'))->assertOk());
        $aset = $this->buat(['metode' => 'unggah', 'tautan' => null, 'berkas' => $this->foto(), 'nama' => 'Foto kegiatan siswa bersama masyarakat dan mitra SMP Negeri 2 Padang Panjang', 'kategori' => 'foto']);
        $p = $this->publikasi([$aset->id]);
        $this->putJson(route('aset-promosi-humas.update', $aset), $this->editData($aset, ['nama' => 'Foto kegiatan siswa - revisi keterangan']))->assertOk();
        $this->capture('show', $this->get(route('aset-promosi-humas.show', $aset))->assertOk()->assertSee('Dipakai dalam publikasi'));
        $this->capture('edit', $this->get(route('aset-promosi-humas.edit', $aset))->assertOk());
        $this->capture('pub-form', $this->get(route('publikasi-humas.create', ['aset_awal' => $aset->id]))->assertOk());
        $this->capture('pub-edit', $this->get(route('publikasi-humas.edit', $p))->assertOk()->assertSee('Gunakan versi terbaru'));
        $this->capture('pub-show', $this->get(route('publikasi-humas.show', $p))->assertOk());
        $this->buat(['nama' => 'Profil sekolah versi video', 'kategori' => 'video']);
        for ($i = 0; $i < 19; $i++) {
            $this->buat(['nama' => 'Brosur program nomor '.$i, 'kategori' => 'brosur']);
        }
        $this->capture('index', $this->get(route('aset-promosi-humas.index'))->assertOk());
        $this->assertCount(18, $this->get(route('aset-promosi-humas.index'))->viewData('daftar'));
        $this->capture('filtered', $this->get(route('aset-promosi-humas.index', ['kategori' => 'foto']))->assertOk()->assertSee($aset->fresh()->nama)->assertDontSee('Brosur program nomor'));
        $this->getJson(route('aset-promosi-humas.dokumen', ['cari' => 'Foto kegiatan']))->assertOk()->assertJsonCount(1, 'dokumen');
        $this->getJson(route('publikasi-humas.pilihan-aset', ['cari' => 'video']))->assertOk()->assertJsonCount(1, 'aset');
        $this->get(route('aset-promosi-humas.index', ['kategori' => 'palsu']))->assertSessionHasErrors('kategori');
        $aset->update(['nama' => '</textarea><script>alert(1)</script>']);
        $this->get(route('aset-promosi-humas.show', $aset))->assertOk()->assertDontSee($aset->nama, false)->assertSee(e($aset->nama), false);
        $this->get(route('aset-promosi-humas.edit', $aset))->assertOk()->assertDontSee($aset->nama, false);
    }

    private function akun(string $role = 'wakil_pimpinan_humas'): Pengguna
    {
        $p = Pengguna::create(['nama' => 'Petugas '.$role, 'username' => 'aset.'.Str::uuid(), 'kata_sandi' => 'UjiAset123', 'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $p->daftarPeran()->attach(Peran::where('kode', $role)->firstOrFail());

        return $p;
    }

    private function akunIzin(array $izin): Pengguna
    {
        $role = Peran::create(['kode' => 'aset_'.Str::random(12), 'nama' => 'Aset terbatas', 'aktif' => true]);
        $role->izin()->attach(Izin::whereIn('kode', $izin)->pluck('id'));
        $p = $this->akun('pegawai');
        $p->daftarPeran()->attach($role);

        return $p;
    }

    private function data(): array
    {
        return ['token_pembuatan' => (string) Str::uuid(), 'nama' => 'Logo sekolah', 'kategori' => 'logo', 'tanggal_aset' => '2026-10-04',
            'metode' => 'tautan', 'tautan' => 'https://sekolah.test/video', 'kredit' => 'Humas sekolah', 'ketentuan_penggunaan' => 'Untuk kegiatan resmi sekolah.'];
    }

    private function buat(array $data = []): AsetPromosiHumas
    {
        $this->postJson(route('aset-promosi-humas.store'), array_replace($this->data(), $data))->assertOk();

        return AsetPromosiHumas::latest('id')->firstOrFail();
    }

    private function editData(AsetPromosiHumas $aset, array $data = []): array
    {
        return array_replace($aset->only(['nama', 'kategori', 'deskripsi', 'tanggal_aset', 'kata_kunci', 'kredit', 'ketentuan_penggunaan']),
            ['tanggal_aset' => $aset->tanggal_aset->format('Y-m-d'), 'metode' => 'tetap', 'status' => $aset->fresh()->status, 'versi' => $aset->fresh()->versi], $data);
    }

    private function foto(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('foto-kegiatan.jpg', file_get_contents(public_path('images/login-sekolah.jpg')));
    }

    private function dokumen(): DokumenHumas
    {
        $file = $this->foto();
        $path = $file->storeAs('dokumen-humas', Str::uuid().'.jpg', 'local');
        $doc = DokumenHumas::create(['judul' => 'Dokumen foto sekolah', 'kategori' => 'aset_digital', 'status' => 'aktif', 'lokasi_file' => $path,
            'nama_file_asli' => $file->getClientOriginalName(), 'tipe_file' => 'image/jpeg', 'ukuran_file' => $file->getSize()]);
        $this->versiDokumen($doc, 1);
        Storage::disk('local')->delete($path);

        return $doc->fresh();
    }

    private function versiDokumen(DokumenHumas $doc, int $versi): void
    {
        $file = $this->foto();
        $metadata = ['lokasi_file' => $file->storeAs('dokumen-humas', Str::uuid().'.jpg', 'local'), 'nama_file_asli' => $file->getClientOriginalName(), 'tipe_file' => 'image/jpeg', 'ukuran_file' => $file->getSize()];
        $doc->riwayat()->create($metadata + ['versi' => $versi, 'catatan' => 'Dokumen uji', 'diunggah_pada' => now()]);
        $doc->update($metadata);
    }

    private function pubData(array $ids): array
    {
        return ['token_pembuatan' => (string) Str::uuid(), 'judul' => 'Publikasi kegiatan sekolah', 'jenis' => 'berita', 'isi' => 'Kegiatan sekolah bersama masyarakat dan mitra pendidikan.',
            'kanal' => 'website', 'aset_dikirim' => '1', 'aset_ids' => $ids];
    }

    private function publikasi(array $ids): PublikasiHumas
    {
        $this->postJson(route('publikasi-humas.store'), $this->pubData($ids))->assertOk();

        return PublikasiHumas::latest('id')->firstOrFail();
    }

    private function capture(string $name, TestResponse $response): void
    {
        if (getenv('NUSA_CAPTURE_ASET_UI') !== '1') {
            return;
        }
        $folder = storage_path('framework/testing/aset-promosi-humas');
        if (! is_dir($folder)) {
            mkdir($folder, 0777, true);
        }
        file_put_contents($folder.'/'.$name.'.html', $response->getContent());
    }
}
