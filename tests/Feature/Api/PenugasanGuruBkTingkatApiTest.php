<?php

namespace Tests\Feature\Api;

use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\PenugasanGuruBkTingkat;
use App\Models\Peran;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PenugasanGuruBkTingkatApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_dapat_melihat_menambah_dan_mengakhiri_penugasan(): void
    {
        $tahun = $this->buatTahun();
        [$guruBk] = $this->buatAkunPegawai(
            'Guru BK Mobile',
            '197801012008011111',
            'bk',
        );
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $token = $this->token($administrator);

        $this->getJson(route('api.v1.penugasan-guru-bk-tingkat.index'))
            ->assertUnauthorized();

        $this->withToken($token)
            ->getJson(route('api.v1.penugasan-guru-bk-tingkat.index', [
                'tahun_pelajaran_id' => $tahun->id,
            ]))
            ->assertOk()
            ->assertJsonCount(3, 'data.tingkat')
            ->assertJsonPath('data.tingkat.0.tingkat', 7)
            ->assertJsonPath('data.guru_bk.0.id', $guruBk->id)
            ->assertJsonPath('data.hak_akses.dapat_kelola', true)
            ->assertJsonPath('data.ringkasan.pembagian_aktif', false);

        $this->withToken($token)
            ->postJson(route('api.v1.penugasan-guru-bk-tingkat.store'), [
                'tahun_pelajaran_id' => $tahun->id,
                'pegawai_id' => $guruBk->id,
                'tingkat' => [7, 8],
            ])
            ->assertCreated()
            ->assertJsonPath('data.jumlah', 2)
            ->assertJsonCount(2, 'data.penugasan');

        $this->assertDatabaseHas('penugasan_guru_bk_tingkat', [
            'tahun_pelajaran_id' => $tahun->id,
            'pegawai_id' => $guruBk->id,
            'tingkat' => 7,
            'aktif' => true,
        ]);
        $this->assertDatabaseHas('penugasan_guru_bk_tingkat', [
            'tahun_pelajaran_id' => $tahun->id,
            'pegawai_id' => $guruBk->id,
            'tingkat' => 8,
            'aktif' => true,
        ]);

        $this->withToken($token)
            ->getJson(route('api.v1.penugasan-guru-bk-tingkat.index', [
                'tahun_pelajaran_id' => $tahun->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.jumlah_penugasan', 2)
            ->assertJsonPath('data.ringkasan.jumlah_guru_bk', 1)
            ->assertJsonPath('data.ringkasan.tingkat_terisi', 2)
            ->assertJsonPath('data.ringkasan.pembagian_aktif', true);

        $penugasan = PenugasanGuruBkTingkat::where('tingkat', 7)->firstOrFail();
        $this->withToken($token)
            ->deleteJson(route('api.v1.penugasan-guru-bk-tingkat.destroy', $penugasan))
            ->assertOk()
            ->assertJsonPath('data.id', $penugasan->id)
            ->assertJsonPath('data.aktif', false);

        $this->assertDatabaseHas('penugasan_guru_bk_tingkat', [
            'id' => $penugasan->id,
            'aktif' => false,
            'tanggal_selesai' => now()->startOfDay()->toDateTimeString(),
        ]);
    }

    public function test_guru_bk_tanpa_izin_kelola_tidak_dapat_membuka_pengaturan(): void
    {
        [, $akunBk] = $this->buatAkunPegawai(
            'Guru BK Tanpa Izin Kelola',
            '197801012008013333',
            'bk',
        );

        $this->withToken($this->token($akunBk))
            ->getJson(route('api.v1.penugasan-guru-bk-tingkat.index'))
            ->assertForbidden();
    }

    public function test_penugasan_hanya_menerima_pegawai_dengan_role_guru_bk_aktif(): void
    {
        $tahun = $this->buatTahun();
        [$pegawai] = $this->buatAkunPegawai(
            'Guru Biasa Mobile',
            '197901012009012222',
            'pegawai',
        );
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->withToken($this->token($administrator))
            ->postJson(route('api.v1.penugasan-guru-bk-tingkat.store'), [
                'tahun_pelajaran_id' => $tahun->id,
                'pegawai_id' => $pegawai->id,
                'tingkat' => [7],
            ])
            ->assertUnprocessable()
            ->assertJsonFragment([
                'message' => 'Pegawai yang dipilih harus memiliki akun aktif dengan role Guru BK.',
            ]);

        $this->assertDatabaseCount('penugasan_guru_bk_tingkat', 0);
    }

    private function buatTahun(): TahunPelajaran
    {
        return TahunPelajaran::create([
            'nama' => '2038/2039',
            'tanggal_mulai' => '2038-07-01',
            'tanggal_selesai' => '2039-06-30',
            'aktif' => true,
        ]);
    }

    private function buatAkunPegawai(string $nama, string $nip, string $kodePeran): array
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => $nama,
            'nip' => $nip,
            'jenis_pegawai' => 'Guru',
            'jabatan_utama' => $kodePeran === 'bk' ? 'Guru BK' : 'Guru',
            'aktif' => true,
        ]);
        $pengguna = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $nama,
            'username' => $nip,
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $pengguna->daftarPeran()->attach(
            Peran::where('kode', $kodePeran)->firstOrFail(),
        );

        return [$pegawai, $pengguna];
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
