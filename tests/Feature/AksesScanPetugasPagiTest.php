<?php

namespace Tests\Feature;

use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AksesScanPetugasPagiTest extends TestCase
{
    use RefreshDatabase;

    public function test_satpam_dan_petugas_kebersihan_memiliki_izin_scan_absensi(): void
    {
        $this->assertTrue(Peran::where('kode', 'satpam')->firstOrFail()->memilikiIzin('absensi.scan'));
        $this->assertTrue(Peran::where('kode', 'petugas_kebersihan')->firstOrFail()->memilikiIzin('absensi.scan'));
    }

    public function test_satpam_dapat_membuka_scan_siswa_dan_pegawai(): void
    {
        $akun = $this->buatAkunPetugas('Satpam Penyiap Scanner', '199001012015011001', 'satpam');

        $this->actingAs($akun)
            ->get(route('scan-absensi.index'))
            ->assertOk();

        $this->get(route('scan-absensi-pegawai.index'))
            ->assertOk();
    }

    public function test_petugas_kebersihan_dapat_membuka_scan_siswa_dan_pegawai(): void
    {
        $akun = $this->buatAkunPetugas('Petugas Kebersihan Penyiap Scanner', '199101012016011002', 'petugas_kebersihan');

        $this->actingAs($akun)
            ->get(route('scan-absensi.index'))
            ->assertOk();

        $this->get(route('scan-absensi-pegawai.index'))
            ->assertOk();
    }

    private function buatAkunPetugas(string $nama, string $nip, string $kodePeran): Pengguna
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => $nama,
            'nip' => $nip,
            'jenis_pegawai' => 'Tenaga Kependidikan',
            'aktif' => true,
        ]);
        $akun = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip,
            'kata_sandi' => 'KataSandi-Uji-2026',
            'wajib_ganti_kata_sandi' => false,
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);
        $akun->daftarPeran()->attach(Peran::whereIn('kode', ['pegawai', $kodePeran])->pluck('id'));

        return $akun;
    }
}
