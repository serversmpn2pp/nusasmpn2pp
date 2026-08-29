<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\JadwalKegiatanIbadah;
use App\Models\KegiatanIbadah;
use App\Models\Kelas;
use App\Models\Pengguna;
use App\Models\PeriodeBerhalanganIbadah;
use App\Models\PresensiKegiatanIbadah;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RingkasanKegiatanIbadahBulananApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_ringkasan_bulanan_memerlukan_token_dan_mengirim_capaian_kelas(): void
    {
        Carbon::setTestNow('2026-08-13 15:00:00');
        $data = $this->dataDasar();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->buatPresensi($data, $data['anggota_a1'], $administrator, '2026-08-06', 'manual');
        $this->buatPresensi($data, $data['anggota_a1'], $administrator, '2026-08-13');
        $this->buatPresensi($data, $data['anggota_b1'], $administrator, '2026-08-13');
        PeriodeBerhalanganIbadah::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'siswa_id' => $data['anggota_a2']->siswa_id,
            'kelas_id' => $data['kelas_a']->id,
            'anggota_kelas_id' => $data['anggota_a2']->id,
            'tanggal_mulai' => '2026-08-05',
            'status' => PeriodeBerhalanganIbadah::STATUS_AKTIF,
            'batas_hari_konfirmasi' => 7,
            'catatan_privat' => 'Informasi privat tidak boleh masuk ringkasan bulanan.',
        ]);
        $parameter = [
            'bulan' => '2026-08',
            'kegiatan_ibadah_id' => $data['kegiatan']->id,
        ];

        $this->getJson(route('api.v1.ringkasan-kegiatan-ibadah-bulanan', $parameter))
            ->assertUnauthorized();

        $response = $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.ringkasan-kegiatan-ibadah-bulanan', $parameter))
            ->assertOk()
            ->assertJsonPath('data.tersedia', true)
            ->assertJsonPath('data.bulan', '2026-08')
            ->assertJsonPath('data.bulan_label', 'Agustus 2026')
            ->assertJsonPath('data.tahun_pelajaran.nama', '2026/2027')
            ->assertJsonCount(2, 'data.tanggal_kegiatan')
            ->assertJsonPath('data.tanggal_kegiatan.0.tanggal', '2026-08-06')
            ->assertJsonPath('data.tanggal_kegiatan.1.tanggal', '2026-08-13')
            ->assertJsonPath('data.ringkasan.kelas', 2)
            ->assertJsonPath('data.ringkasan.siswa', 3)
            ->assertJsonPath('data.ringkasan.hari_kegiatan', 2)
            ->assertJsonPath('data.ringkasan.target', 6)
            ->assertJsonPath('data.ringkasan.tercatat', 3)
            ->assertJsonPath('data.ringkasan.belum', 3)
            ->assertJsonPath('data.ringkasan.persentase', 50)
            ->assertJsonCount(2, 'data.ringkasan_kelas')
            ->assertJsonCount(0, 'data.items')
            ->assertJsonMissing(['catatan_privat' => 'Informasi privat tidak boleh masuk ringkasan bulanan.']);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_kelas_dipilih_mengirim_capaian_setiap_siswa_dan_input_manual(): void
    {
        Carbon::setTestNow('2026-08-13 15:00:00');
        $data = $this->dataDasar();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->buatPresensi($data, $data['anggota_a1'], $administrator, '2026-08-06', 'manual');
        $this->buatPresensi($data, $data['anggota_a1'], $administrator, '2026-08-13');

        $response = $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.ringkasan-kegiatan-ibadah-bulanan', [
                'bulan' => '2026-08',
                'kegiatan_ibadah_id' => $data['kegiatan']->id,
                'kelas_id' => $data['kelas_a']->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.kelas_dipilih.nama', 'VII.A')
            ->assertJsonPath('data.ringkasan.kelas', 1)
            ->assertJsonPath('data.ringkasan.siswa', 2)
            ->assertJsonPath('data.ringkasan.target', 4)
            ->assertJsonPath('data.ringkasan.tercatat', 2)
            ->assertJsonCount(2, 'data.items');

        $items = collect($response->json('data.items'));
        $sudah = $items->first(fn (array $item) => $item['siswa']['nama'] === 'Siswa A Hadir');
        $belum = $items->first(fn (array $item) => $item['siswa']['nama'] === 'Siswa A Belum');
        $this->assertSame(2, $sudah['target']);
        $this->assertSame(2, $sudah['tercatat']);
        $this->assertSame(1, $sudah['manual']);
        $this->assertSame(100, $sudah['persentase']);
        $this->assertSame('13 Agt 2026', $sudah['terakhir_label']);
        $this->assertSame(2, $belum['belum']);
        $this->assertSame(0, $belum['persentase']);
    }

    public function test_ringkasan_bulanan_menolak_bulan_masa_depan(): void
    {
        Carbon::setTestNow('2026-08-13 15:00:00');
        $this->dataDasar();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.ringkasan-kegiatan-ibadah-bulanan', ['bulan' => '2026-09']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('bulan');
    }

    private function dataDasar(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelasA = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'VII.A', 'tingkat' => 7, 'aktif' => true]);
        $kelasB = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'VII.B', 'tingkat' => 7, 'aktif' => true]);
        $siswaA1 = Siswa::create(['nama_lengkap' => 'Siswa A Hadir', 'nis' => '26001', 'nisn' => '0131201150', 'aktif' => true]);
        $siswaA2 = Siswa::create(['nama_lengkap' => 'Siswa A Belum', 'nis' => '26002', 'nisn' => '0131201151', 'aktif' => true]);
        $siswaB1 = Siswa::create(['nama_lengkap' => 'Siswa B Hadir', 'nis' => '26003', 'nisn' => '0131201152', 'aktif' => true]);
        $anggotaA1 = AnggotaKelas::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelasA->id, 'siswa_id' => $siswaA1->id, 'nomor_absen' => 1, 'status_keanggotaan' => 'aktif']);
        $anggotaA2 = AnggotaKelas::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelasA->id, 'siswa_id' => $siswaA2->id, 'nomor_absen' => 2, 'status_keanggotaan' => 'aktif']);
        $anggotaB1 = AnggotaKelas::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelasB->id, 'siswa_id' => $siswaB1->id, 'nomor_absen' => 1, 'status_keanggotaan' => 'aktif']);
        $kegiatan = KegiatanIbadah::where('kode', 'sholat_duhur')->firstOrFail();
        $jadwal = JadwalKegiatanIbadah::create([
            'kegiatan_ibadah_id' => $kegiatan->id,
            'tahun_pelajaran_id' => $tahun->id,
            'hari' => 'kamis',
            'urutan_hari' => 4,
            'jam_scan_mulai' => '11:30',
            'jam_pelaksanaan' => '12:00',
            'jam_scan_selesai' => '13:00',
            'aktif' => true,
        ]);

        return [
            'tahun' => $tahun,
            'kelas_a' => $kelasA,
            'kelas_b' => $kelasB,
            'anggota_a1' => $anggotaA1,
            'anggota_a2' => $anggotaA2,
            'anggota_b1' => $anggotaB1,
            'kegiatan' => $kegiatan,
            'jadwal' => $jadwal,
        ];
    }

    private function buatPresensi(
        array $data,
        AnggotaKelas $anggota,
        Pengguna $petugas,
        string $tanggal,
        string $sumber = 'kamera',
    ): PresensiKegiatanIbadah {
        return PresensiKegiatanIbadah::create([
            'jadwal_kegiatan_ibadah_id' => $data['jadwal']->id,
            'kegiatan_ibadah_id' => $data['kegiatan']->id,
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $anggota->kelas_id,
            'anggota_kelas_id' => $anggota->id,
            'siswa_id' => $anggota->siswa_id,
            'dipindai_oleh_pengguna_id' => $petugas->id,
            'tanggal' => $tanggal,
            'waktu_scan' => '12:05:00',
            'sumber' => $sumber,
        ]);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Perangkat Ringkasan Ibadah', ['mobile'])->plainTextToken;
    }
}
