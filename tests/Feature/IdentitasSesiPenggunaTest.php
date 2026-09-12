<?php

namespace Tests\Feature;

use App\Http\Controllers\AksesUjianCbtController;
use App\Models\GuruMataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PesertaUjianCbt;
use App\Services\Survei\PengisianSurveiPembelajaranService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class IdentitasSesiPenggunaTest extends TestCase
{
    use RefreshDatabase;

    public function test_pegawai_yang_diberi_role_administrator_tetap_memakai_identitas_pegawai(): void
    {
        $akun = $this->buatAkunPegawai('Guru Administrator', '198601012010011001');

        $this->post(route('login.store'), [
            'username' => $akun->username,
            'password' => 'KataSandiPegawai123',
        ])->assertRedirect(route('beranda'));

        $akun->daftarPeran()->syncWithoutDetaching([
            Peran::where('kode', 'administrator')->value('id'),
        ]);

        $response = $this->get(route('beranda'));

        $response->assertOk();
        $response->assertSee('Guru Administrator');
        $response->assertSee('Username: '.$akun->username);
        $response->assertDontSee('Administrator NUSA');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $akun->refresh();
        $this->assertSame('Guru Administrator', $akun->nama);
        $this->assertSame('198601012010011001', $akun->username);
        $this->assertFalse($akun->akun_sistem);
        $this->assertSame($akun->pegawai_id, $akun->pegawai?->id);
    }

    public function test_header_memakai_nama_data_pegawai_sebagai_sumber_identitas(): void
    {
        $akun = $this->buatAkunPegawai('Nama Pegawai Sebenarnya', '198702022011022002');
        $akun->update(['nama' => 'Administrator NUSA']);

        $response = $this->actingAs($akun)->get(route('beranda'));

        $response->assertOk();
        $response->assertSee('Nama Pegawai Sebenarnya');
        $response->assertSee('Username: '.$akun->username);
        $response->assertDontSee('Administrator NUSA');
    }

    public function test_perubahan_identitas_di_tengah_sesi_memaksa_login_ulang(): void
    {
        $akun = $this->buatAkunPegawai('Pegawai Sesi Aman', '198803032012033003');
        $pesan = 'Identitas sesi berubah. Silakan masuk kembali untuk melindungi akun Anda.';

        $this->actingAs($akun)
            ->withSession([
                'nusa.identitas_pengguna' => [
                    'pengguna_id' => (string) $akun->id,
                    'sidik_jari' => str_repeat('0', 64),
                ],
            ])
            ->get(route('beranda'))
            ->assertRedirect(route('login'))
            ->assertSessionHas('gagal', $pesan);

        $this->assertGuest();
        $this->get(route('login'))
            ->assertOk()
            ->assertSee($pesan);
    }

    public function test_pemberian_role_administrator_tidak_mengubah_data_identitas_akun_pegawai(): void
    {
        $akun = $this->buatAkunPegawai('Pegawai Penerima Role', '198904042013044004');
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $pegawaiId = $akun->pegawai_id;

        $this->actingAs($administrator)
            ->patch(route('akun-pegawai.peran.update', $akun), [
                'peran_ids' => [Peran::where('kode', 'administrator')->value('id')],
            ])
            ->assertRedirect();

        $akun->refresh()->load('daftarPeran');

        $this->assertSame('Pegawai Penerima Role', $akun->nama);
        $this->assertSame('198904042013044004', $akun->username);
        $this->assertSame($pegawaiId, $akun->pegawai_id);
        $this->assertFalse($akun->akun_sistem);
        $this->assertTrue($akun->daftarPeran->contains('kode', 'pegawai'));
        $this->assertTrue($akun->daftarPeran->contains('kode', 'administrator'));
    }

    public function test_role_siswa_tidak_mengubah_pegawai_menjadi_identitas_siswa_di_web(): void
    {
        $akun = $this->buatAkunPegawai('Pegawai Dengan Role Siswa', '198905052014055005');
        $akun->daftarPeran()->syncWithoutDetaching([
            Peran::where('kode', 'siswa')->value('id'),
        ]);

        $this->assertTrue($akun->memilikiPeran('siswa'));
        $this->assertTrue($akun->akunPegawai());
        $this->assertFalse($akun->akunSiswa());

        $this->actingAs($akun)
            ->get(route('beranda'))
            ->assertOk()
            ->assertViewIs('beranda.index')
            ->assertSee('Dashboard Pegawai');

        foreach ([
            'profil-siswa.show',
            'progress-kasus-siswa.index',
            'nilai-saya.index',
            'ujian-saya.index',
        ] as $namaRute) {
            $this->get(route($namaRute))->assertForbidden();
        }

        $this->assertForbiddenCall(fn () => app(PengisianSurveiPembelajaranService::class)
            ->siapkan($akun, new GuruMataPelajaran, 'ganjil'));

        $request = Request::create('/ujian-saya/masuk', 'POST');
        $request->setUserResolver(fn () => $akun);
        $this->assertForbiddenCall(fn () => app(AksesUjianCbtController::class)
            ->masukDariAkunSiswa($request, new PesertaUjianCbt));
    }

    public function test_role_orang_tua_tidak_mengubah_pegawai_menjadi_identitas_orang_tua_di_web(): void
    {
        $akun = $this->buatAkunPegawai('Pegawai Dengan Role Orang Tua', '198906062015066006');
        $akun->daftarPeran()->syncWithoutDetaching([
            Peran::where('kode', 'orang_tua')->value('id'),
        ]);

        $this->assertTrue($akun->memilikiPeran('orang_tua'));
        $this->assertTrue($akun->akunPegawai());
        $this->assertFalse($akun->akunOrangTua());

        $this->actingAs($akun)
            ->get(route('beranda'))
            ->assertOk()
            ->assertViewIs('beranda.index')
            ->assertSee('Dashboard Pegawai');

        foreach ([
            'presensi-anak.index',
            'akademik-anak.index',
            'pembinaan-poin-anak.index',
            'profil-orang-tua.edit',
        ] as $namaRute) {
            $this->get(route($namaRute))->assertForbidden();
        }
    }

    private function assertForbiddenCall(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Aksi seharusnya ditolak dengan status 403.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
    }

    private function buatAkunPegawai(string $nama, string $nip): Pengguna
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => $nama,
            'nip' => $nip,
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $akun = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $nama,
            'username' => $nip,
            'kata_sandi' => 'KataSandiPegawai123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);
        $akun->daftarPeran()->attach(Peran::where('kode', 'pegawai')->firstOrFail());

        return $akun;
    }
}
