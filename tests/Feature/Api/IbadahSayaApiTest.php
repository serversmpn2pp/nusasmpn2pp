<?php

namespace Tests\Feature\Api;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\JadwalKegiatanIbadah;
use App\Models\KegiatanIbadah;
use App\Models\Kelas;
use App\Models\OrangTuaWali;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\PeriodeBerhalanganIbadah;
use App\Models\PresensiBerhalanganIbadah;
use App\Models\PresensiKegiatanIbadah;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IbadahSayaApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_endpoint_memerlukan_token_dan_menolak_akun_pegawai(): void
    {
        $this->getJson(route('api.v1.ibadah-saya'))->assertUnauthorized();

        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Pegawai Bukan Siswa Ibadah',
            'nip' => '198001012026091111',
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
            ->getJson(route('api.v1.ibadah-saya'))
            ->assertForbidden();
    }

    public function test_siswa_melihat_ibadah_sendiri_dan_status_berhalangan_tanpa_catatan_privat(): void
    {
        Carbon::setTestNow('2026-09-10 13:00:00');
        $data = $this->dataDasar('L');
        $pengguna = $this->akunSiswa($data['siswa']);
        $this->buatKehadiran($data, '2026-09-03');
        $this->buatKehadiran($data, '2026-09-10');
        PresensiKegiatanIbadah::create([
            'jadwal_kegiatan_ibadah_id' => $data['jadwal']->id,
            'kegiatan_ibadah_id' => $data['kegiatan']->id,
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $data['kelas']->id,
            'anggota_kelas_id' => $data['anggota']->id,
            'siswa_id' => $data['siswa']->id,
            'tanggal' => '2026-09-03',
            'waktu_scan' => '12:04:00',
            'sumber' => 'kamera',
        ]);
        $periode = PeriodeBerhalanganIbadah::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'siswa_id' => $data['siswa']->id,
            'kelas_id' => $data['kelas']->id,
            'anggota_kelas_id' => $data['anggota']->id,
            'tanggal_mulai' => '2026-09-10',
            'status' => PeriodeBerhalanganIbadah::STATUS_AKTIF,
            'batas_hari_konfirmasi' => 7,
            'catatan_privat' => 'Catatan sangat privat tidak boleh dikirim.',
        ]);
        PresensiBerhalanganIbadah::create([
            'periode_berhalangan_ibadah_id' => $periode->id,
            'jadwal_kegiatan_ibadah_id' => $data['jadwal']->id,
            'kegiatan_ibadah_id' => $data['kegiatan']->id,
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $data['kelas']->id,
            'anggota_kelas_id' => $data['anggota']->id,
            'siswa_id' => $data['siswa']->id,
            'tanggal' => '2026-09-10',
            'waktu_scan' => '12:06:00',
            'sumber' => 'kamera',
        ]);

        $response = $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.ibadah-saya', [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'bulan' => '2026-09',
            ]))
            ->assertOk()
            ->assertJsonPath('data.mode', 'siswa')
            ->assertJsonPath('data.siswa.id', $data['siswa']->id)
            ->assertJsonPath('data.kelas.nama', 'VIII A Ibadah Personal')
            ->assertJsonPath('data.ringkasan.total', 2)
            ->assertJsonPath('data.ringkasan.sudah', 1)
            ->assertJsonPath('data.ringkasan.berhalangan', 1)
            ->assertJsonPath('data.ringkasan.belum', 0)
            ->assertJsonPath('data.ringkasan.wajib', 1)
            ->assertJsonPath('data.ringkasan.persentase', 100)
            ->assertJsonCount(2, 'data.riwayat');

        $items = collect($response->json('data.riwayat'))->keyBy('status');
        $this->assertSame('12:04', $items['sudah']['waktu_tercatat']);
        $this->assertSame('12:06', $items['berhalangan']['waktu_tercatat']);
        $response->assertDontSee('Catatan sangat privat tidak boleh dikirim.');

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'ibadah-saya',
                'label' => 'Ibadah Saya',
                'rute' => '/ibadah-saya',
            ])
            ->assertJsonMissing(['kode' => 'ibadah-anak-saya']);
    }

    public function test_orang_tua_dapat_memilih_anak_dan_siswi_jumat_tidak_dihitung_belum(): void
    {
        Carbon::setTestNow('2026-09-11 13:00:00');
        $data = $this->dataDasar('P', hari: 'jumat', sholatJumat: true);
        $anakLakiLaki = $this->buatSiswa('Anak Laki-laki Ibadah', '0097111002', 'L');
        $anggotaLakiLaki = AnggotaKelas::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $data['kelas']->id,
            'siswa_id' => $anakLakiLaki->id,
            'nomor_absen' => 2,
            'status_keanggotaan' => 'aktif',
        ]);
        foreach ([[$data['siswa'], $data['anggota']], [$anakLakiLaki, $anggotaLakiLaki]] as [$siswa, $anggota]) {
            AbsensiSiswa::create([
                'tanggal' => '2026-09-11',
                'tahun_pelajaran_id' => $data['tahun']->id,
                'kelas_id' => $data['kelas']->id,
                'anggota_kelas_id' => $anggota->id,
                'siswa_id' => $siswa->id,
                'jam_masuk' => '06:40:00',
                'status_masuk' => 'tepat_waktu',
                'status_kehadiran' => 'hadir',
                'sumber' => 'scan',
            ]);
        }
        $orangLain = $this->buatSiswa('Siswa Tidak Tertaut', '0097111099', 'L');
        $pengguna = Pengguna::create([
            'nama' => 'Orang Tua Ibadah',
            'username' => 'ORT-0097111001',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'orang_tua',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $orangTua = OrangTuaWali::create([
            'pengguna_id' => $pengguna->id,
            'siswa_acuan_username_id' => $data['siswa']->id,
            'nama_lengkap' => $pengguna->nama,
        ]);
        $orangTua->siswa()->attach([
            $data['siswa']->id => ['hubungan' => 'ibu', 'utama' => true],
            $anakLakiLaki->id => ['hubungan' => 'ibu', 'utama' => false],
        ]);
        $token = $this->token($pengguna);

        $this->withToken($token)
            ->getJson(route('api.v1.ibadah-saya', ['bulan' => '2026-09']))
            ->assertOk()
            ->assertJsonPath('data.mode', 'orang_tua')
            ->assertJsonPath('data.siswa.id', $data['siswa']->id)
            ->assertJsonCount(2, 'data.pilihan_siswa')
            ->assertJsonPath('data.ringkasan.tidak_wajib', 1)
            ->assertJsonPath('data.ringkasan.belum', 0)
            ->assertJsonPath('data.riwayat.0.status', 'tidak_wajib');

        $this->withToken($token)
            ->getJson(route('api.v1.ibadah-saya', [
                'siswa_id' => $anakLakiLaki->id,
                'bulan' => '2026-09',
            ]))
            ->assertOk()
            ->assertJsonPath('data.siswa.id', $anakLakiLaki->id)
            ->assertJsonPath('data.ringkasan.belum', 1)
            ->assertJsonPath('data.riwayat.0.status', 'belum');

        $this->withToken($token)
            ->getJson(route('api.v1.ibadah-saya', ['siswa_id' => $orangLain->id]))
            ->assertForbidden();

        $this->withToken($token)
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'ibadah-anak-saya',
                'label' => 'Ibadah Anak Saya',
                'rute' => '/ibadah-anak-saya',
            ])
            ->assertJsonMissing(['kode' => 'ibadah-saya']);
    }

    private function dataDasar(string $jenisKelamin, string $hari = 'kamis', bool $sholatJumat = false): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VIII A Ibadah Personal',
            'tingkat' => 8,
            'aktif' => true,
        ]);
        $siswa = $this->buatSiswa('Siswa Ibadah Personal', '0097111001', $jenisKelamin);
        $anggota = AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => 1,
            'status_keanggotaan' => 'aktif',
        ]);
        $kegiatan = $sholatJumat
            ? KegiatanIbadah::create([
                'kode' => KegiatanIbadah::KODE_SHOLAT_JUMAT,
                'nama' => 'Sholat Jumat',
                'aktif' => true,
            ])
            : KegiatanIbadah::where('kode', 'sholat_duhur')->firstOrFail();
        $jadwal = JadwalKegiatanIbadah::create([
            'kegiatan_ibadah_id' => $kegiatan->id,
            'tahun_pelajaran_id' => $tahun->id,
            'hari' => $hari,
            'urutan_hari' => $hari === 'jumat' ? 5 : 4,
            'jam_scan_mulai' => '11:30',
            'jam_pelaksanaan' => '12:00',
            'jam_scan_selesai' => '13:00',
            'aktif' => true,
        ]);

        return compact('tahun', 'kelas', 'siswa', 'anggota', 'kegiatan', 'jadwal');
    }

    private function buatKehadiran(array $data, string $tanggal): void
    {
        AbsensiSiswa::create([
            'tanggal' => $tanggal,
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $data['kelas']->id,
            'anggota_kelas_id' => $data['anggota']->id,
            'siswa_id' => $data['siswa']->id,
            'jam_masuk' => '06:40:00',
            'status_masuk' => 'tepat_waktu',
            'status_kehadiran' => 'hadir',
            'sumber' => 'scan',
        ]);
    }

    private function buatSiswa(string $nama, string $nisn, string $jenisKelamin): Siswa
    {
        return Siswa::create([
            'nama_lengkap' => $nama,
            'nis' => 'NIS-'.$nisn,
            'nisn' => $nisn,
            'jenis_kelamin' => $jenisKelamin,
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
        return $pengguna->createToken('Perangkat Ibadah Personal', ['mobile'])->plainTextToken;
    }
}
