<?php

namespace Tests\Feature\Api;

use App\Models\GuruMataPelajaran;
use App\Models\Kelas;
use App\Models\KomponenNilai;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PublikasiNilaiSiswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KomponenNilaiApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_memerlukan_token_dan_izin_kelola_komponen(): void
    {
        $this->getJson(route('api.v1.komponen-nilai.index'))->assertUnauthorized();

        $pengguna = $this->buatPengguna('Tanpa Izin Komponen', 'tanpa.izin.komponen');
        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.komponen-nilai.index'))
            ->assertForbidden();
    }

    public function test_daftar_memuat_filter_ringkasan_dan_referensi_penugasan(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $data = $this->dataAkademik();
        $formatif = $this->buatKomponen($data['penugasan'], 'ganjil', 'formatif', 'Kuis Aljabar');
        $this->buatKomponen($data['penugasan_lain'], 'genap', 'sumatif', 'Proyek IPA', false);

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.komponen-nilai.index', [
                'cari' => 'aljabar',
                'tahun_pelajaran_id' => $data['tahun']->id,
                'semester' => 'ganjil',
                'jenis_komponen' => 'formatif',
                'status' => 'aktif',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $formatif->id)
            ->assertJsonPath('data.items.0.guru_mata_pelajaran.kelas.nama', 'VIII.KOMPONEN')
            ->assertJsonPath('data.items.0.guru_mata_pelajaran.mata_pelajaran.nama', 'Matematika Komponen')
            ->assertJsonPath('data.items.0.jenis_label', 'Formatif')
            ->assertJsonPath('data.ringkasan.total', 2)
            ->assertJsonPath('data.ringkasan.aktif', 1)
            ->assertJsonPath('data.ringkasan.nonaktif', 1)
            ->assertJsonCount(2, 'data.guru_mata_pelajaran')
            ->assertJsonPath('data.hak_akses.dapat_kelola', true);
    }

    public function test_tambah_ubah_dan_nonaktifkan_menandai_publikasi_sebagai_draf(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $data = $this->dataAkademik();
        $publikasi = PublikasiNilaiSiswa::create([
            'guru_mata_pelajaran_id' => $data['penugasan']->id,
            'semester' => 'ganjil',
            'dipublikasikan' => true,
            'dipublikasikan_pada' => now(),
            'dipublikasikan_oleh_pengguna_id' => $administrator->id,
        ]);
        $token = $this->token($administrator);

        $response = $this->withToken($token)
            ->postJson(route('api.v1.komponen-nilai.store'), $this->payload(
                $data['penugasan'],
                'ganjil',
                'sumatif',
                'Proyek Statistika',
            ))
            ->assertCreated();
        $komponen = KomponenNilai::firstOrFail();
        $response->assertJsonPath('data.id', $komponen->id);
        $this->assertFalse($publikasi->fresh()->dipublikasikan);

        $publikasi->update([
            'dipublikasikan' => true,
            'dipublikasikan_pada' => now(),
            'dipublikasikan_oleh_pengguna_id' => $administrator->id,
        ]);
        $payload = $this->payload(
            $data['penugasan'],
            'ganjil',
            'sumatif',
            'Proyek Statistika Revisi',
        );
        $payload['urutan'] = 4;
        $payload['tanggal_penilaian'] = '2026-09-12';
        $this->withToken($token)
            ->patchJson(route('api.v1.komponen-nilai.update', $komponen), $payload)
            ->assertOk();
        $this->assertDatabaseHas('komponen_nilai', [
            'id' => $komponen->id,
            'nama' => 'Proyek Statistika Revisi',
            'urutan' => 4,
        ]);
        $this->assertSame('2026-09-12', $komponen->fresh()->tanggal_penilaian->toDateString());
        $this->assertFalse($publikasi->fresh()->dipublikasikan);

        $this->withToken($token)
            ->deleteJson(route('api.v1.komponen-nilai.destroy', $komponen))
            ->assertOk();
        $this->assertFalse($komponen->fresh()->aktif);
    }

    public function test_nama_scope_sts_dan_sas_aktif_divalidasi(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $data = $this->dataAkademik();
        $token = $this->token($administrator);
        $payload = $this->payload($data['penugasan'], 'ganjil', 'sts', 'STS Ganjil');

        $this->withToken($token)
            ->postJson(route('api.v1.komponen-nilai.store'), $payload)
            ->assertCreated();
        $this->withToken($token)
            ->postJson(route('api.v1.komponen-nilai.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('nama');

        $payload['nama'] = 'STS Susulan Aktif';
        $this->withToken($token)
            ->postJson(route('api.v1.komponen-nilai.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('jenis_komponen');

        $payload['aktif'] = false;
        $this->withToken($token)
            ->postJson(route('api.v1.komponen-nilai.store'), $payload)
            ->assertCreated();
    }

    public function test_guru_mapel_hanya_melihat_dan_mengelola_penugasan_sendiri(): void
    {
        $data = $this->dataAkademik();
        $milikSendiri = $this->buatKomponen(
            $data['penugasan'],
            'ganjil',
            'formatif',
            'Latihan Guru Sendiri',
        );
        $milikLain = $this->buatKomponen(
            $data['penugasan_lain'],
            'ganjil',
            'formatif',
            'Latihan Guru Lain',
        );
        $guru = $this->buatPengguna(
            'Guru Komponen Sendiri',
            'guru.komponen.sendiri',
            $data['guru'],
        );
        $guru->daftarPeran()->attach(
            Peran::where('kode', 'guru_mapel')->firstOrFail(),
        );
        $this->app['auth']->forgetGuards();
        $token = $this->token($guru);

        $this->withToken($token)
            ->getJson(route('api.v1.komponen-nilai.index'))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $milikSendiri->id)
            ->assertJsonCount(1, 'data.guru_mata_pelajaran')
            ->assertJsonMissing(['id' => $milikLain->id]);

        $this->withToken($token)
            ->patchJson(
                route('api.v1.komponen-nilai.update', $milikLain),
                $this->payload(
                    $data['penugasan_lain'],
                    'ganjil',
                    'formatif',
                    'Percobaan Akses Silang',
                ),
            )
            ->assertForbidden();
        $this->withToken($token)
            ->postJson(
                route('api.v1.komponen-nilai.store'),
                $this->payload(
                    $data['penugasan_lain'],
                    'genap',
                    'formatif',
                    'Komponen Guru Lain',
                ),
            )
            ->assertForbidden();
    }

    public function test_form_web_tetap_memakai_service_komponen_yang_sama(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $data = $this->dataAkademik();

        $response = $this->actingAs($administrator)
            ->post(route('komponen-nilai.store'), $this->payload(
                $data['penugasan'],
                'genap',
                'formatif',
                'Diagnostik Web',
            ));
        $komponen = KomponenNilai::firstOrFail();

        $response->assertRedirect(route('komponen-nilai.show', $komponen));
        $this->assertDatabaseHas('komponen_nilai', [
            'id' => $komponen->id,
            'nama' => 'Diagnostik Web',
            'aktif' => true,
        ]);
    }

    private function dataAkademik(): array
    {
        $tahun = TahunPelajaran::create(['nama' => '2026/2027 Komponen', 'aktif' => true]);
        $kelas = $this->buatKelas($tahun, 'VIII.KOMPONEN', 8);
        $kelasLain = $this->buatKelas($tahun, 'VIII.KOMPONEN.LAIN', 8);
        $guru = $this->buatPegawai('Guru Matematika Komponen', '198101012020011061');
        $guruLain = $this->buatPegawai('Guru IPA Komponen', '198101012020011062');
        $matematika = $this->buatMataPelajaran('MTK-KOMP', 'Matematika Komponen');
        $ipa = $this->buatMataPelajaran('IPA-KOMP', 'IPA Komponen');
        $penugasan = $this->buatPenugasan($tahun, $kelas, $matematika, $guru);
        $penugasanLain = $this->buatPenugasan($tahun, $kelasLain, $ipa, $guruLain);

        return [
            'tahun' => $tahun,
            'guru' => $guru,
            'penugasan' => $penugasan,
            'penugasan_lain' => $penugasanLain,
        ];
    }

    private function buatPegawai(string $nama, string $nip): Pegawai
    {
        return Pegawai::create([
            'nama_lengkap' => $nama,
            'nip' => $nip,
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
    }

    private function buatPengguna(
        string $nama,
        string $username,
        ?Pegawai $pegawai = null,
    ): Pengguna {
        return Pengguna::create([
            'pegawai_id' => $pegawai?->id,
            'nama' => $nama,
            'username' => $username,
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
    }

    private function buatKelas(TahunPelajaran $tahun, string $nama, int $tingkat): Kelas
    {
        return Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => $nama,
            'tingkat' => $tingkat,
            'aktif' => true,
        ]);
    }

    private function buatMataPelajaran(string $kode, string $nama): MataPelajaran
    {
        return MataPelajaran::create([
            'kode' => $kode,
            'nama' => $nama,
            'kelompok' => 'Wajib',
            'aktif' => true,
        ]);
    }

    private function buatPenugasan(
        TahunPelajaran $tahun,
        Kelas $kelas,
        MataPelajaran $mataPelajaran,
        Pegawai $guru,
    ): GuruMataPelajaran {
        return GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'pegawai_id' => $guru->id,
            'jenis_penugasan' => 'pengampu',
            'aktif' => true,
        ]);
    }

    private function buatKomponen(
        GuruMataPelajaran $penugasan,
        string $semester,
        string $jenis,
        string $nama,
        bool $aktif = true,
    ): KomponenNilai {
        return KomponenNilai::create([
            'guru_mata_pelajaran_id' => $penugasan->id,
            'semester' => $semester,
            'jenis_komponen' => $jenis,
            'nama' => $nama,
            'urutan' => 1,
            'aktif' => $aktif,
        ]);
    }

    private function payload(
        GuruMataPelajaran $penugasan,
        string $semester,
        string $jenis,
        string $nama,
    ): array {
        return [
            'guru_mata_pelajaran_id' => $penugasan->id,
            'semester' => $semester,
            'jenis_komponen' => $jenis,
            'nama' => $nama,
            'tanggal_penilaian' => null,
            'urutan' => 1,
            'aktif' => true,
            'keterangan' => 'Komponen dari NUSA Mobile',
        ];
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
