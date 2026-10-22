<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\V1\Humas\TahapTigaController;
use App\Http\Resources\Api\V1\PenggunaResource;
use App\Models\AlumniHumas;
use App\Models\Izin;
use App\Models\OrangTuaWali;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class HumasNativeTahapTigaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ThrottleRequests::class);
        Storage::fake('local');
        Queue::fake();
        $this->travelTo(now()->setDate(2026, 10, 23)->setTime(10, 0));
        $this->token($this->akun('wakil_pimpinan_humas'));
    }

    private function url(string $path): string
    {
        return '/api/v1/humas/'.$path;
    }

    private function input(string $kind): array
    {
        return ['token_pembuatan' => (string) Str::uuid()] + match ($kind) {
            'aset' => ['nama' => 'Logo sekolah', 'kategori' => 'logo', 'tanggal_aset' => '2026-10-01', 'metode' => 'tautan', 'tautan' => 'https://example.org/logo.png'],
            'media' => ['nama' => 'Website sekolah', 'jenis' => 'website', 'tautan' => 'https://example.org', 'penanggung_jawab' => 'Tim Humas', 'status' => 'aktif'],
            'kliping' => ['judul' => 'Kegiatan sekolah', 'nama_media' => 'Media pendidikan', 'jenis' => 'online', 'topik' => 'kegiatan', 'tanggal_terbit' => '2026-10-01', 'tautan' => 'https://example.org/berita', 'status' => 'aktif', 'metode' => 'tanpa'],
            'prestasi' => ['nama_kegiatan' => 'Olimpiade Sekolah', 'capaian' => 'Juara lomba', 'tanggal_prestasi' => '2026-10-01', 'kategori' => 'akademik', 'tingkat' => 'provinsi', 'perolehan' => 'juara_1', 'penerima' => 'sekolah', 'bentuk' => 'sekolah', 'status' => 'draf', 'metode' => 'tanpa'],
            'alumni' => ['nama_lengkap' => 'Alumni Uji', 'tahun_lulus' => 2026, 'status' => 'aktif', 'status_penelusuran' => 'belum_terdata', 'konfirmasi_lulus' => true, 'nomor_wa' => '081234567890', 'email' => 'alumni@example.org', 'catatan_penelusuran' => 'Catatan privat alumni'],
        };
    }

    public function test_lima_modul_crud_retry_filter_paginasi_riwayat_dan_konflik(): void
    {
        foreach (array_keys(TahapTigaController::MODUL) as $kind) {
            $data = $this->input($kind);
            $saved = $this->postJson($this->url($kind), $data)->assertCreated()->json('data');
            $this->postJson($this->url($kind), $data)->assertOk()->assertJsonPath('data.id', $saved['id']);
            $path = $kind.'/'.$saved['id'];
            $this->getJson($this->url($kind.'/referensi'))->assertOk()->assertJsonPath('data.dapat_kelola', true);
            $this->getJson($this->url($path))->assertOk()->assertJsonPath('data.versi', 0)->assertDontSee('token_pembuatan')->assertDontSee('lokasi_file');
            $this->getJson($this->url($kind.'?per_halaman=1&halaman=1'))->assertOk()->assertJsonPath('data.paginasi.total', 1);
            $this->getJson($this->url($kind.'?kata_kunci=tidak-ditemukan'))->assertOk()->assertJsonPath('data.paginasi.total', 0);
            $edit = array_replace($data, ['status' => 'arsip', 'versi' => 0, 'catatan_perubahan' => 'Data diarsipkan sementara', 'catatan_revisi' => 'Arsipkan aset lama']);
            if (in_array($kind, ['aset', 'kliping', 'prestasi'])) {
                $edit['metode'] = 'tetap';
            }
            if ($kind === 'aset') {
                unset($edit['tautan']);
            }
            $this->patchJson($this->url($path), $edit)->assertOk()->assertJsonPath('data.versi', 1);
            $this->patchJson($this->url($path), $edit)->assertUnprocessable();
            $this->getJson($this->url($path.'/riwayat'))->assertOk()->assertJsonCount(2, 'data.items')->assertDontSee('snapshot')->assertDontSee('lokasi_file');
            $this->getJson($this->url($kind.'?per_halaman=51'))->assertUnprocessable();
        }
    }

    public function test_role_menu_dan_privasi_alumni_dengan_fixture_android(): void
    {
        $ids = [];
        foreach (array_keys(TahapTigaController::MODUL) as $kind) {
            $ids[$kind] = $this->postJson($this->url($kind), $this->input($kind))->assertCreated()->json('data.id');
        }
        $fixture = [];
        foreach (['administrator', 'wakil_pimpinan_humas', 'pimpinan', 'pegawai'] as $role) {
            $u = $this->akun($role);
            $this->token($u);
            $records = [];
            foreach (TahapTigaController::MODUL as $kind => [, $permission]) {
                $allowed = $u->memilikiIzin([$permission.'.lihat', $permission.'.kelola']);
                $response = $this->getJson($this->url($kind.'/'.$ids[$kind]));
                $response->assertStatus($allowed ? 200 : 403);
                if ($allowed) {
                    $records[$kind] = ['detail' => $response->json('data'),
                        'references' => $this->getJson($this->url($kind.'/referensi'))->assertOk()->json('data'),
                        'list' => $this->getJson($this->url($kind))->assertOk()->json('data')];
                    $manage = $u->memilikiIzin($permission.'.kelola');
                    $response->assertJsonPath('data.hak_akses.dapat_kelola', $manage);
                    $this->getJson($this->url($kind.'/referensi'))->assertOk();
                    if (! $manage) {
                        $this->patchJson($this->url($kind.'/'.$ids[$kind]), [])->assertForbidden();
                    }
                    if ($kind === 'alumni' && ! $manage) {
                        $response->assertDontSee('081234567890')->assertDontSee('alumni@example.org')->assertDontSee('Catatan privat');
                        $this->getJson($this->url('alumni/'.$ids[$kind].'/riwayat'))->assertDontSee('Catatan privat');
                        $this->getJson($this->url('alumni/ekspor?sertakan_kontak=1'))->assertForbidden();
                    }
                }
            }
            $fixture[$role] = ['pengguna' => (new PenggunaResource($u))->resolve(), 'menu' => $this->getJson('/api/v1/menu')->assertOk()->json('data'), 'records' => $records];
        }
        if (getenv('NUSA_CAPTURE_HUMAS_MOBILE')) {
            File::put(base_path('mobile/test/fixtures/humas_native_tahap_tiga.json'), json_encode($fixture, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        $this->assertSame('081234567890', AlumniHumas::first()->nomor_wa);
    }

    public function test_unggah_bukti_kepemilikan_berkas_dan_verifikasi_prestasi(): void
    {
        foreach (['aset', 'kliping', 'prestasi'] as $kind) {
            $data = array_replace($this->input($kind), ['metode' => 'unggah']);
            unset($data['tautan']);
            $data['berkas'] = UploadedFile::fake()->createWithContent('bukti.pdf', '%PDF-1.4 Dokumen uji');
            $id = $this->post($this->url($kind), $data, ['Accept' => 'application/json'])->assertCreated()->json('data.id');
            $detail = $this->getJson($this->url($kind.'/'.$id))->assertOk()->json('data');
            $file = $detail['berkas']['id'];
            $this->getJson($this->url($kind.'/'.$id.'/berkas/'.$file))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->getJson($this->url($kind.'/'.$id.'/berkas/999999'))->assertNotFound();
            if ($kind === 'prestasi') {
                unset($data['berkas']);
                $this->patchJson($this->url($kind.'/'.$id), array_replace($data, ['metode' => 'tetap', 'status' => 'terverifikasi', 'versi' => 0, 'konfirmasi_prestasi' => true, 'catatan_perubahan' => 'Bukti sudah diverifikasi']))->assertOk();
            }
        }
    }

    public function test_cetak_web_tidak_berubah_dan_ekspor_tersedia(): void
    {
        foreach (['prestasi', 'alumni'] as $kind) {
            $this->postJson($this->url($kind), $this->input($kind))->assertCreated();
            $api = $this->getJson($this->url($kind.'/cetak'))->assertOk()->getContent();
            $u = auth('sanctum')->user();
            $this->actingAs($u, 'web');
            $web = $this->get(route($kind === 'prestasi' ? 'prestasi-sekolah.cetak' : 'alumni-humas.cetak'))->assertOk()->getContent();
            $this->assertSame($web, $api);
            $this->getJson($this->url($kind.'/ekspor'))->assertOk()->assertDownload();
        }
    }

    public function test_sumber_berkas_dan_identitas_tidak_bocor_ke_pembaca_atau_akun_siswa_ortu(): void
    {
        $input = array_replace($this->input('aset'), ['metode' => 'unggah', 'berkas' => UploadedFile::fake()->createWithContent('logo.pdf', '%PDF-1.4 Uji')]);
        unset($input['tautan']);
        $id = $this->post($this->url('aset'), $input, ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $file = $this->getJson($this->url('aset/'.$id))->json('data.berkas.id');
        $input['token_pembuatan'] = (string) Str::uuid();
        $other = $this->post($this->url('aset'), $input, ['Accept' => 'application/json'])->assertCreated()->json('data.id');
        $this->getJson($this->url('aset/'.$other.'/berkas/'.$file))->assertNotFound();
        $reader = $this->akun('pegawai');
        $role = Peran::create(['kode' => 'pembaca_tahap3', 'nama' => 'Pembaca Uji', 'aktif' => true]);
        $role->izin()->sync(Izin::whereIn('kode', array_map(fn ($r) => $r[1].'.lihat', TahapTigaController::MODUL))->pluck('id'));
        $reader->daftarPeran()->sync([$role->id]);
        $this->token($reader->fresh());
        $this->getJson($this->url('aset/'.$id))->assertOk()->assertJsonMissingPath('data.berkas')->assertDontSee('lokasi_file');
        $this->getJson($this->url('aset/'.$id.'/berkas/'.$file))->assertForbidden();
        $this->getJson($this->url('alumni/pilihan'))->assertForbidden();
        $this->getJson($this->url('prestasi/pilihan?jenis=siswa'))->assertForbidden();
        foreach (['siswa', 'orang_tua'] as $type) {
            $u = $this->akun('administrator');
            $u->update(['pegawai_id' => null]);
            if ($type === 'siswa') {
                $s = Siswa::create(['nama_lengkap' => 'Siswa Uji', 'nisn' => '1234567890', 'jenis_kelamin' => 'L', 'aktif' => true]);
                $u->update(['siswa_id' => $s->id]);
            } else {
                OrangTuaWali::create(['pengguna_id' => $u->id, 'nama_lengkap' => 'Wali Uji']);
            }
            $this->token($u->fresh());
            foreach (array_keys(TahapTigaController::MODUL) as $kind) {
                $this->getJson($this->url($kind))->assertForbidden();
                $this->postJson($this->url($kind), [])->assertForbidden();
            }
        }
    }

    public function test_paginasi_tidak_mengulang_baris_dan_uuid_milik_akun_lain_ditolak(): void
    {
        $inputs = [];
        foreach (array_keys(TahapTigaController::MODUL) as $kind) {
            $inputs[$kind] = $data = $this->input($kind);
            for ($i = 1; $i <= 3; $i++) {
                $row = array_replace($data, ['token_pembuatan' => $i === 1 ? $data['token_pembuatan'] : (string) Str::uuid()]);
                foreach (['nama', 'judul', 'nama_kegiatan', 'nama_lengkap'] as $key) {
                    if (isset($row[$key])) {
                        $row[$key] .= ' '.$i;
                    }
                }
                if (isset($row['tautan'])) {
                    $row['tautan'] .= '/'.$i;
                }
                $this->postJson($this->url($kind), $row)->assertCreated();
                if ($i === 1) {
                    $inputs[$kind] = $row;
                }
            }
            $one = $this->getJson($this->url($kind.'?halaman=1&per_halaman=2'))->assertOk()->assertJsonPath('data.paginasi.total', 3)->assertJsonCount(2, 'data.items')->json('data.items');
            $two = $this->getJson($this->url($kind.'?halaman=2&per_halaman=2'))->assertOk()->assertJsonCount(1, 'data.items')->json('data.items');
            $this->assertSame([], array_intersect(array_column($one, 'id'), array_column($two, 'id')));
        }
        $this->token($this->akun('wakil_pimpinan_humas'));
        foreach ($inputs as $kind => $input) {
            $this->postJson($this->url($kind), $input)->assertForbidden();
        }
    }

    private function akun(string $role): Pengguna
    {
        $u = Pengguna::create(['nama' => 'Uji '.$role, 'username' => 'tahap3.'.Str::uuid(), 'kata_sandi' => 'Humas2026!', 'peran' => 'pegawai', 'aktif' => true, 'wajib_ganti_kata_sandi' => false, 'pegawai_id' => Pegawai::create(['nama_lengkap' => 'Uji '.$role, 'aktif' => true])->id]);
        $u->daftarPeran()->sync([Peran::where('kode', $role)->value('id')]);

        return $u->fresh();
    }

    private function token(Pengguna $u): void
    {
        auth()->forgetGuards();
        $this->withToken($u->createToken('Tahap 3', ['mobile'])->plainTextToken);
    }
}
