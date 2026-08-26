<?php

namespace Tests\Feature\Api;

use App\Models\GuruMataPelajaran;
use App\Models\Izin;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PublikasiNilaiSiswa;
use App\Models\SkemaBobotNilai;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SkemaBobotNilaiApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_memerlukan_token_dan_izin_kelola_skema(): void
    {
        $this->getJson(route('api.v1.skema-bobot-nilai.index'))->assertUnauthorized();

        $pengguna = $this->buatPengguna('Tanpa Izin Skema', 'tanpa.izin.skema');
        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.skema-bobot-nilai.index'))
            ->assertForbidden();
    }

    public function test_daftar_memuat_filter_ringkasan_dan_bobot(): void
    {
        $tahun = $this->tahun('2026/2027 Skema', true);
        $tahunLama = $this->tahun('2025/2026 Skema');
        $aktif = $this->skema($tahun, 'ganjil', 8, true);
        $this->skema($tahunLama, 'genap', null, false);
        $pengelola = $this->penggunaDenganIzin();

        $this->withToken($this->token($pengelola))
            ->getJson(route('api.v1.skema-bobot-nilai.index', [
                'tahun_pelajaran_id' => $tahun->id,
                'semester' => 'ganjil',
                'tingkat' => '8',
                'status' => 'aktif',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $aktif->id)
            ->assertJsonPath('data.items.0.tingkat_label', 'Kelas 8')
            ->assertJsonPath('data.items.0.bobot_formatif', 35)
            ->assertJsonPath('data.items.0.bobot_sumatif', 25)
            ->assertJsonPath('data.items.0.bobot_sts', 15)
            ->assertJsonPath('data.items.0.bobot_sas_saj', 25)
            ->assertJsonPath('data.items.0.total_bobot', 100)
            ->assertJsonPath('data.ringkasan.total', 2)
            ->assertJsonPath('data.ringkasan.aktif', 1)
            ->assertJsonPath('data.ringkasan.nonaktif', 1)
            ->assertJsonPath('data.filter.tahun_pelajaran_id', $tahun->id)
            ->assertJsonPath('data.hak_akses.dapat_kelola', true);
    }

    public function test_tambah_memerlukan_total_seratus_dan_scope_yang_unik(): void
    {
        $tahun = $this->tahun('2026/2027 Tambah Skema', true);
        $pengelola = $this->penggunaDenganIzin();
        $token = $this->token($pengelola);
        $payload = $this->payload($tahun, 'ganjil', 7);

        $response = $this->withToken($token)
            ->postJson(route('api.v1.skema-bobot-nilai.store'), $payload)
            ->assertCreated();
        $skema = SkemaBobotNilai::firstOrFail();
        $response->assertJsonPath('data.id', $skema->id);
        $this->assertSame(100, $skema->totalBobot());

        $this->withToken($token)
            ->postJson(route('api.v1.skema-bobot-nilai.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('tingkat');

        $payload['tingkat'] = 8;
        $payload['bobot_formatif'] = 30;
        $this->withToken($token)
            ->postJson(route('api.v1.skema-bobot-nilai.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('bobot_formatif');
    }

    public function test_update_dan_nonaktifkan_skema(): void
    {
        $tahun = $this->tahun('2026/2027 Ubah Skema', true);
        $skema = $this->skema($tahun, 'ganjil', 8, true);
        $pengelola = $this->penggunaDenganIzin();
        $token = $this->token($pengelola);
        $payload = $this->payload($tahun, 'genap', 9);
        $payload['bobot_formatif'] = 30;
        $payload['bobot_sumatif'] = 30;
        $payload['keterangan'] = 'Diperbarui dari Android';

        $this->withToken($token)
            ->patchJson(route('api.v1.skema-bobot-nilai.update', $skema), $payload)
            ->assertOk();
        $this->assertDatabaseHas('skema_bobot_nilai', [
            'id' => $skema->id,
            'semester' => 'genap',
            'tingkat' => 9,
            'bobot_formatif' => 30,
            'bobot_sumatif' => 30,
            'keterangan' => 'Diperbarui dari Android',
        ]);

        $this->withToken($token)
            ->deleteJson(route('api.v1.skema-bobot-nilai.destroy', $skema))
            ->assertOk();
        $this->assertFalse($skema->fresh()->aktif);
    }

    public function test_perubahan_skema_mengembalikan_publikasi_terkait_ke_draf(): void
    {
        $tahun = $this->tahun('2026/2027 Publikasi Skema', true);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VIII.SKEMA.API',
            'tingkat' => 8,
            'aktif' => true,
        ]);
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Guru Publikasi Skema',
            'nip' => '198101012020011077',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $mapel = MataPelajaran::create([
            'kode' => 'MTK-SKEMA-API',
            'nama' => 'Matematika Skema API',
            'kelompok' => 'Wajib',
            'aktif' => true,
        ]);
        $penugasan = GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mapel->id,
            'pegawai_id' => $pegawai->id,
            'jenis_penugasan' => 'pengampu',
            'aktif' => true,
        ]);
        $publikasi = PublikasiNilaiSiswa::create([
            'guru_mata_pelajaran_id' => $penugasan->id,
            'semester' => 'ganjil',
            'dipublikasikan' => true,
            'dipublikasikan_pada' => now(),
        ]);
        $pengelola = $this->penggunaDenganIzin();

        $this->withToken($this->token($pengelola))
            ->postJson(
                route('api.v1.skema-bobot-nilai.store'),
                $this->payload($tahun, 'ganjil', 8),
            )
            ->assertCreated();

        $this->assertFalse($publikasi->fresh()->dipublikasikan);
        $this->assertNull($publikasi->fresh()->dipublikasikan_pada);
    }

    public function test_form_web_tetap_memakai_service_skema_yang_sama(): void
    {
        $tahun = $this->tahun('2026/2027 Web Skema', true);
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $response = $this->actingAs($administrator)
            ->post(route('skema-bobot-nilai.store'), $this->payload($tahun, 'genap', null));
        $skema = SkemaBobotNilai::firstOrFail();

        $response->assertRedirect(route('skema-bobot-nilai.show', $skema));
        $this->assertDatabaseHas('skema_bobot_nilai', [
            'id' => $skema->id,
            'tingkat' => null,
            'bobot_formatif' => 35,
            'aktif' => true,
        ]);
    }

    private function tahun(string $nama, bool $aktif = false): TahunPelajaran
    {
        return TahunPelajaran::create(['nama' => $nama, 'aktif' => $aktif]);
    }

    private function skema(
        TahunPelajaran $tahun,
        string $semester,
        ?int $tingkat,
        bool $aktif,
    ): SkemaBobotNilai {
        return SkemaBobotNilai::create([
            'tahun_pelajaran_id' => $tahun->id,
            'semester' => $semester,
            'tingkat' => $tingkat,
            'bobot_formatif' => 35,
            'bobot_sumatif' => 25,
            'bobot_sts' => 15,
            'bobot_sas_saj' => 25,
            'aktif' => $aktif,
        ]);
    }

    private function payload(
        TahunPelajaran $tahun,
        string $semester,
        ?int $tingkat,
    ): array {
        return [
            'tahun_pelajaran_id' => $tahun->id,
            'semester' => $semester,
            'tingkat' => $tingkat,
            'bobot_formatif' => 35,
            'bobot_sumatif' => 25,
            'bobot_sts' => 15,
            'bobot_sas_saj' => 25,
            'aktif' => true,
            'keterangan' => 'Skema dari NUSA Mobile',
        ];
    }

    private function penggunaDenganIzin(): Pengguna
    {
        $peran = Peran::create([
            'nama' => 'Pengelola Skema Bobot API '.str()->random(5),
            'kode' => 'pengelola_skema_'.str()->lower(str()->random(8)),
            'aktif' => true,
            'sistem' => false,
        ]);
        $peran->izin()->attach(Izin::where('kode', 'nilai.skema_kelola')->firstOrFail());
        $pengguna = $this->buatPengguna(
            'Pengelola Skema Bobot API',
            'pengelola.skema.'.str()->lower(str()->random(5)),
        );
        $pengguna->daftarPeran()->attach($peran);

        return $pengguna;
    }

    private function buatPengguna(string $nama, string $username): Pengguna
    {
        return Pengguna::create([
            'nama' => $nama,
            'username' => $username,
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
