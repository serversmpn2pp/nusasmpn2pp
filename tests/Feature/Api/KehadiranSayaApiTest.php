<?php

namespace Tests\Feature\Api;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\OrangTuaWali;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KehadiranSayaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_endpoint_memerlukan_token_dan_menolak_akun_pegawai(): void
    {
        $this->getJson(route('api.v1.kehadiran-saya'))->assertUnauthorized();

        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Pegawai Bukan Siswa',
            'nip' => '198001012026091001',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
        $pengguna = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip,
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.kehadiran-saya'))
            ->assertForbidden();
    }

    public function test_siswa_hanya_melihat_kehadiran_dan_rekap_milik_sendiri(): void
    {
        Carbon::setTestNow('2026-09-08 09:00:00');
        $data = $this->dataDasar();
        $pengguna = $this->akunSiswa($data['siswaSatu']);
        $pengguna->daftarPeran()->attach(Peran::where('kode', 'siswa')->firstOrFail());

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.kehadiran-saya', [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'bulan' => '2026-09',
            ]))
            ->assertOk()
            ->assertJsonPath('data.mode', 'siswa')
            ->assertJsonPath('data.siswa.id', $data['siswaSatu']->id)
            ->assertJsonPath('data.kelas.nama', 'VIII A Kehadiran')
            ->assertJsonPath('data.hari_ini.status', 'hadir')
            ->assertJsonPath('data.ringkasan.total_catatan', 3)
            ->assertJsonPath('data.ringkasan.hadir', 1)
            ->assertJsonPath('data.ringkasan.sakit', 1)
            ->assertJsonPath('data.ringkasan.izin', 1)
            ->assertJsonPath('data.ringkasan.alfa', 0)
            ->assertJsonPath('data.ringkasan.terlambat', 1)
            ->assertJsonPath('data.ringkasan.menit_terlambat', 7)
            ->assertJsonCount(3, 'data.riwayat')
            ->assertJsonMissing(['catatan' => 'Catatan siswa lain']);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.beranda'))
            ->assertOk()
            ->assertJsonPath('data.presensi.hari_ini.status', 'hadir')
            ->assertJsonPath('data.presensi.bulan_ini.hadir', 1);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.kehadiran-saya', [
                'siswa_id' => $data['siswaDua']->id,
            ]))
            ->assertForbidden();
    }

    public function test_orang_tua_dapat_memilih_anak_tertaut_tetapi_bukan_siswa_lain(): void
    {
        Carbon::setTestNow('2026-09-08 09:00:00');
        $data = $this->dataDasar();
        $anakKedua = $this->buatSiswa('Anak Kedua', '0099000022');
        $anggotaKedua = AnggotaKelas::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $data['kelas']->id,
            'siswa_id' => $anakKedua->id,
            'nomor_absen' => 3,
            'status_keanggotaan' => 'aktif',
        ]);
        AbsensiSiswa::create([
            'tanggal' => '2026-09-08',
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $data['kelas']->id,
            'anggota_kelas_id' => $anggotaKedua->id,
            'siswa_id' => $anakKedua->id,
            'status_kehadiran' => 'alfa',
            'sumber' => 'manual',
        ]);
        $pengguna = Pengguna::create([
            'nama' => 'Orang Tua Kehadiran',
            'username' => 'ORT-0099000011',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'orang_tua',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $orangTua = OrangTuaWali::create([
            'pengguna_id' => $pengguna->id,
            'siswa_acuan_username_id' => $data['siswaSatu']->id,
            'nama_lengkap' => $pengguna->nama,
        ]);
        $orangTua->siswa()->attach([
            $data['siswaSatu']->id => ['hubungan' => 'ayah', 'utama' => true],
            $anakKedua->id => ['hubungan' => 'ayah', 'utama' => false],
        ]);
        $pengguna->daftarPeran()->attach(Peran::where('kode', 'orang_tua')->firstOrFail());

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'kehadiran-saya',
                'status' => 'tersedia',
                'rute' => '/kehadiran-saya',
            ]);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.beranda'))
            ->assertOk()
            ->assertJsonPath('data.presensi.hari_ini.status', 'hadir')
            ->assertJsonPath('data.presensi.bulan_ini.hadir', 1);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.kehadiran-saya', [
                'siswa_id' => $anakKedua->id,
                'tahun_pelajaran_id' => $data['tahun']->id,
                'bulan' => '2026-09',
            ]))
            ->assertOk()
            ->assertJsonPath('data.mode', 'orang_tua')
            ->assertJsonPath('data.siswa.id', $anakKedua->id)
            ->assertJsonCount(2, 'data.pilihan_siswa')
            ->assertJsonPath('data.ringkasan.alfa', 1)
            ->assertJsonCount(1, 'data.riwayat');

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.kehadiran-saya', [
                'siswa_id' => $data['siswaDua']->id,
            ]))
            ->assertForbidden();
    }

    private function dataDasar(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VIII A Kehadiran',
            'tingkat' => 8,
            'aktif' => true,
        ]);
        $siswaSatu = $this->buatSiswa('Siswa Kehadiran Satu', '0099000011');
        $siswaDua = $this->buatSiswa('Siswa Kehadiran Lain', '0099000099');
        $anggotaSatu = AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswaSatu->id,
            'nomor_absen' => 1,
            'status_keanggotaan' => 'aktif',
        ]);
        $anggotaDua = AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswaDua->id,
            'nomor_absen' => 2,
            'status_keanggotaan' => 'aktif',
        ]);

        foreach ([
            ['2026-09-08', 'hadir', '06:57', 7, 'Terlambat karena hujan'],
            ['2026-09-07', 'sakit', null, 0, 'Surat sakit diterima'],
            ['2026-09-06', 'izin', null, 0, 'Izin keluarga'],
        ] as [$tanggal, $status, $jamMasuk, $terlambat, $catatan]) {
            AbsensiSiswa::create([
                'tanggal' => $tanggal,
                'tahun_pelajaran_id' => $tahun->id,
                'kelas_id' => $kelas->id,
                'anggota_kelas_id' => $anggotaSatu->id,
                'siswa_id' => $siswaSatu->id,
                'jam_masuk' => $jamMasuk,
                'status_masuk' => $jamMasuk ? 'terlambat' : null,
                'menit_terlambat' => $terlambat,
                'status_kehadiran' => $status,
                'sumber' => $jamMasuk ? 'scan' : 'manual',
                'catatan' => $catatan,
            ]);
        }
        AbsensiSiswa::create([
            'tanggal' => '2026-09-08',
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'anggota_kelas_id' => $anggotaDua->id,
            'siswa_id' => $siswaDua->id,
            'status_kehadiran' => 'alfa',
            'sumber' => 'manual',
            'catatan' => 'Catatan siswa lain',
        ]);

        return compact('tahun', 'kelas', 'siswaSatu', 'siswaDua');
    }

    private function buatSiswa(string $nama, string $nisn): Siswa
    {
        return Siswa::create([
            'nama_lengkap' => $nama,
            'nis' => 'NIS-'.$nisn,
            'nisn' => $nisn,
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
    }

    private function akunSiswa(Siswa $siswa): Pengguna
    {
        return Pengguna::create([
            'siswa_id' => $siswa->id,
            'nama' => $siswa->nama_lengkap,
            'username' => $siswa->nisn,
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'siswa',
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
