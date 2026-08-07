<?php

namespace Tests\Feature;

use App\Models\Pegawai;
use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbsensiPegawaiPribadiUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_rekap_saya_hanya_memiliki_filter_tanggal_dan_memaksa_pegawai_login(): void
    {
        [$akun, $pegawaiSendiri, $pegawaiLain] = $this->siapkanAkunAdministratorSebagaiPegawai();

        $this->actingAs($akun)
            ->get(route('absensi-pegawai-saya.rekap', [
                'tanggal' => now()->toDateString(),
                'pegawai_id' => $pegawaiLain->id,
                'kata_kunci' => $pegawaiLain->nama_lengkap,
            ]))
            ->assertOk()
            ->assertSee('Rekap absensi saya')
            ->assertSee($pegawaiSendiri->nama_lengkap)
            ->assertDontSee($pegawaiLain->nama_lengkap)
            ->assertSee('name="tanggal"', false)
            ->assertDontSee('name="kata_kunci"', false)
            ->assertDontSee('name="jenis_pegawai"', false)
            ->assertDontSee('name="pegawai_id"', false)
            ->assertDontSee('name="status_pegawai"', false)
            ->assertDontSee('name="status_kehadiran"', false)
            ->assertDontSee('Koreksi');
    }

    public function test_laporan_saya_hanya_memiliki_filter_bulan_dan_cetak_pribadi(): void
    {
        [$akun, $pegawaiSendiri, $pegawaiLain] = $this->siapkanAkunAdministratorSebagaiPegawai();

        $this->actingAs($akun)
            ->get(route('absensi-pegawai-saya.laporan', [
                'bulan' => now()->format('Y-m'),
                'pegawai_id' => $pegawaiLain->id,
                'kata_kunci' => $pegawaiLain->nama_lengkap,
            ]))
            ->assertOk()
            ->assertSee('Laporan absensi saya')
            ->assertSee('Cetak laporan saya')
            ->assertSee($pegawaiSendiri->nama_lengkap)
            ->assertDontSee($pegawaiLain->nama_lengkap)
            ->assertSee('name="bulan"', false)
            ->assertDontSee('name="kata_kunci"', false)
            ->assertDontSee('name="jenis_pegawai"', false)
            ->assertDontSee('name="pegawai_id"', false)
            ->assertDontSee('name="status_pegawai"', false)
            ->assertDontSee('Cetak semua');

        $this->actingAs($akun)
            ->get(route('absensi-pegawai-saya.cetak', [
                'bulan' => now()->format('Y-m'),
                'pegawai_id' => $pegawaiLain->id,
            ]))
            ->assertOk()
            ->assertSee($pegawaiSendiri->nama_lengkap)
            ->assertDontSee($pegawaiLain->nama_lengkap);
    }

    public function test_halaman_operasional_memakai_pilihan_pegawai_tanpa_pencarian_duplikat(): void
    {
        [$akun, $pegawaiSendiri, $pegawaiLain] = $this->siapkanAkunAdministratorSebagaiPegawai();

        $this->actingAs($akun)
            ->get(route('rekap-absensi-pegawai-harian.index', [
                'kata_kunci' => 'parameter-lama-yang-tidak-dipakai',
            ]))
            ->assertOk()
            ->assertDontSee('name="kata_kunci"', false)
            ->assertSee('name="jenis_pegawai"', false)
            ->assertSee('name="pegawai_id"', false)
            ->assertSee('name="status_pegawai"', false)
            ->assertSee($pegawaiSendiri->nama_lengkap)
            ->assertSee($pegawaiLain->nama_lengkap);

        $this->actingAs($akun)
            ->get(route('laporan-absensi-pegawai-bulanan.index', [
                'kata_kunci' => 'parameter-lama-yang-tidak-dipakai',
            ]))
            ->assertOk()
            ->assertDontSee('name="kata_kunci"', false)
            ->assertSee('name="jenis_pegawai"', false)
            ->assertSee('name="pegawai_id"', false)
            ->assertSee('name="status_pegawai"', false)
            ->assertSee($pegawaiSendiri->nama_lengkap)
            ->assertSee($pegawaiLain->nama_lengkap);
    }

    public function test_kartu_pegawai_memakai_pilihan_pegawai_tanpa_pencarian_duplikat(): void
    {
        [$akun, $pegawaiSendiri, $pegawaiLain] = $this->siapkanAkunAdministratorSebagaiPegawai();

        $this->actingAs($akun)
            ->get(route('kartu-pegawai.index', [
                'kata_kunci' => 'parameter-lama-yang-tidak-dipakai',
            ]))
            ->assertOk()
            ->assertDontSee('name="kata_kunci"', false)
            ->assertSee('name="jenis_pegawai"', false)
            ->assertSee('name="pegawai_id"', false)
            ->assertSee('name="status"', false)
            ->assertSee($pegawaiSendiri->nama_lengkap)
            ->assertSee($pegawaiLain->nama_lengkap);
    }

    private function siapkanAkunAdministratorSebagaiPegawai(): array
    {
        $pegawaiSendiri = Pegawai::create([
            'nama_lengkap' => 'Pegawai Pemilik Akun',
            'nip' => '198001012010017777',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $pegawaiLain = Pegawai::create([
            'nama_lengkap' => 'Pegawai Lain Rahasia',
            'nip' => '198001012010018888',
            'jenis_pegawai' => 'Tenaga Kependidikan',
            'aktif' => true,
        ]);
        $akun = Pengguna::where('username', 'administrator')->firstOrFail();
        $akun->update([
            'pegawai_id' => $pegawaiSendiri->id,
            'nama' => $pegawaiSendiri->nama_lengkap,
        ]);

        return [$akun, $pegawaiSendiri, $pegawaiLain];
    }
}
