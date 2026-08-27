<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\Izin;
use App\Models\Kelas;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class KartuPelajarApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_kartu_pelajar_memerlukan_token_dan_izin(): void
    {
        $this->getJson(route('api.v1.kartu-pelajar.index'))->assertUnauthorized();

        $tanpaIzin = Pengguna::create([
            'nama' => 'Tanpa Izin Kartu Pelajar',
            'username' => 'tanpa.izin.kartu.pelajar',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);

        $this->withToken($this->token($tanpaIzin))
            ->getJson(route('api.v1.kartu-pelajar.index'))
            ->assertForbidden();
    }

    public function test_pembaca_dapat_memfilter_kartu_per_tahun_dan_kelas(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('siswa/foto/kartu-mobile.jpg', 'foto');
        $data = $this->dataKartu();
        $pembaca = $this->penggunaDenganIzin('kartu_pelajar.lihat');

        $this->withToken($this->token($pembaca))
            ->getJson(route('api.v1.kartu-pelajar.index', [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'kelas_id' => $data['kelas']->id,
                'siswa_id' => $data['siswa_siap']->id,
                'cari' => 'Anugrah',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.nama', 'Anugrah Kartu Pelajar Mobile')
            ->assertJsonPath('data.items.0.kelas', 'VIII.KP Mobile')
            ->assertJsonPath('data.items.0.nisn', '0131201150')
            ->assertJsonPath('data.items.0.qr_data', '0131201150')
            ->assertJsonPath('data.items.0.qr_bisa_dibuat', true)
            ->assertJsonPath('data.items.0.punya_foto', true)
            ->assertJsonPath('data.items.0.tempat_tanggal_lahir', 'Padang Panjang, 03 November 2012')
            ->assertJsonPath('data.ringkasan.total', 1)
            ->assertJsonPath('data.ringkasan.siap_qr', 1)
            ->assertJsonPath('data.ringkasan.dengan_foto', 1)
            ->assertJsonPath('data.filter.kelas_id', $data['kelas']->id)
            ->assertJsonPath('data.hak_akses.dapat_cetak', false)
            ->assertJsonPath('data.ukuran_kartu.lebar_mm', 53.98);
    }

    public function test_pencetak_mendapat_hak_ekspor_dan_status_data_yang_belum_siap(): void
    {
        Storage::fake('public');
        $data = $this->dataKartu();
        $pencetak = $this->penggunaDenganIzin('kartu_pelajar.cetak');

        $this->withToken($this->token($pencetak))
            ->getJson(route('api.v1.kartu-pelajar.index', [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'kelas_id' => $data['kelas']->id,
                'siswa_id' => $data['siswa_belum']->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.items.0.qr_data', null)
            ->assertJsonPath('data.items.0.qr_bisa_dibuat', false)
            ->assertJsonPath('data.items.0.punya_foto', false)
            ->assertJsonPath('data.ringkasan.siap_qr', 0)
            ->assertJsonPath('data.ringkasan.dengan_foto', 0)
            ->assertJsonPath('data.hak_akses.dapat_cetak', true);
    }

    private function dataKartu(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2030/2031 Kartu Pelajar',
            'tanggal_mulai' => '2030-07-15',
            'tanggal_selesai' => '2031-06-20',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VIII.KP Mobile',
            'tingkat' => 8,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $siswaSiap = Siswa::create([
            'nama_lengkap' => 'Anugrah Kartu Pelajar Mobile',
            'nis' => '300001',
            'nisn' => '0131201150',
            'foto' => 'siswa/foto/kartu-mobile.jpg',
            'jenis_kelamin' => 'L',
            'tempat_lahir' => 'Padang Panjang',
            'tanggal_lahir' => '2012-11-03',
            'aktif' => true,
        ]);
        $siswaBelum = Siswa::create([
            'nama_lengkap' => 'Siswa Kartu Belum Siap',
            'nis' => '300002',
            'nisn' => 'NISN-BELUM-VALID',
            'jenis_kelamin' => 'P',
            'aktif' => true,
        ]);
        foreach ([[$siswaSiap, 1], [$siswaBelum, 2]] as [$siswa, $nomor]) {
            AnggotaKelas::create([
                'tahun_pelajaran_id' => $tahun->id,
                'kelas_id' => $kelas->id,
                'siswa_id' => $siswa->id,
                'nomor_absen' => $nomor,
                'status_keanggotaan' => 'aktif',
            ]);
        }

        return [
            'tahun' => $tahun,
            'kelas' => $kelas,
            'siswa_siap' => $siswaSiap,
            'siswa_belum' => $siswaBelum,
        ];
    }

    private function penggunaDenganIzin(string $kodeIzin): Pengguna
    {
        $peran = Peran::create([
            'nama' => 'Petugas Kartu Pelajar API '.$kodeIzin,
            'kode' => 'petugas_kartu_pelajar_'.str_replace('.', '_', $kodeIzin),
            'aktif' => true,
            'sistem' => false,
        ]);
        $peran->izin()->attach(Izin::where('kode', $kodeIzin)->firstOrFail());
        $pengguna = Pengguna::create([
            'nama' => 'Petugas Kartu Pelajar API',
            'username' => 'petugas.kartu.pelajar.'.str_replace('.', '-', $kodeIzin),
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $pengguna->daftarPeran()->attach($peran);

        return $pengguna;
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
