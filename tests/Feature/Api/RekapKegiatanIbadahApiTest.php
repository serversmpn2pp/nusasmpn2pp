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

class RekapKegiatanIbadahApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_rekap_harian_memerlukan_token_dan_mengirim_ringkasan_serta_siswa_per_kelas(): void
    {
        Carbon::setTestNow('2026-08-13 12:30:00');
        $data = $this->dataDasar();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->buatPresensi($data, $data['anggota_sudah'], $administrator);
        PeriodeBerhalanganIbadah::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'siswa_id' => $data['anggota_belum']->siswa_id,
            'kelas_id' => $data['kelas']->id,
            'anggota_kelas_id' => $data['anggota_belum']->id,
            'tanggal_mulai' => '2026-08-13',
            'status' => PeriodeBerhalanganIbadah::STATUS_AKTIF,
            'batas_hari_konfirmasi' => 7,
            'catatan_privat' => 'Rahasia berhalangan tidak boleh masuk rekap umum.',
        ]);
        $parameter = [
            'tanggal' => '2026-08-13',
            'kegiatan_ibadah_id' => $data['kegiatan']->id,
            'kelas_id' => $data['kelas']->id,
        ];

        $this->getJson(route('api.v1.rekap-kegiatan-ibadah.index', $parameter))
            ->assertUnauthorized();

        $response = $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.rekap-kegiatan-ibadah.index', $parameter))
            ->assertOk()
            ->assertJsonPath('data.tersedia', true)
            ->assertJsonPath('data.tanggal', '2026-08-13')
            ->assertJsonPath('data.kegiatan_dipilih.id', $data['kegiatan']->id)
            ->assertJsonPath('data.kelas_dipilih_id', $data['kelas']->id)
            ->assertJsonPath('data.jadwal.jam_pelaksanaan', '12:00')
            ->assertJsonPath('data.ringkasan.total', 2)
            ->assertJsonPath('data.ringkasan.sudah', 1)
            ->assertJsonPath('data.ringkasan.belum', 1)
            ->assertJsonPath('data.ringkasan.persentase', 50)
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.hak_akses.dapat_koreksi', true)
            ->assertJsonMissing(['catatan_privat' => 'Rahasia berhalangan tidak boleh masuk rekap umum.'])
            ->assertJsonMissingPath('data.items.1.status_berhalangan');

        $items = collect($response->json('data.items'));
        $sudah = $items->first(fn (array $item) => $item['siswa']['nama'] === 'Siswa Sudah Ibadah');
        $belum = $items->first(fn (array $item) => $item['siswa']['nama'] === 'Siswa Belum Ibadah');
        $this->assertSame('sudah', $sudah['status']);
        $this->assertSame('Kamera HP', $sudah['presensi']['sumber_label']);
        $this->assertSame('belum', $belum['status']);

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'rekap-ibadah-siswa',
                'rute' => '/rekap-kegiatan-ibadah',
            ]);
    }

    public function test_filter_belum_hanya_mengirim_siswa_yang_belum_presensi(): void
    {
        Carbon::setTestNow('2026-08-13 12:30:00');
        $data = $this->dataDasar();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->buatPresensi($data, $data['anggota_sudah'], $administrator);

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.rekap-kegiatan-ibadah.index', [
                'tanggal' => '2026-08-13',
                'kegiatan_ibadah_id' => $data['kegiatan']->id,
                'kelas_id' => $data['kelas']->id,
                'status' => 'belum',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.siswa.nama', 'Siswa Belum Ibadah')
            ->assertJsonPath('data.items.0.status', 'belum');
    }

    public function test_detail_koreksi_input_manual_dan_pembatalan_menyimpan_riwayat(): void
    {
        Carbon::setTestNow('2026-08-13 12:30:00');
        $data = $this->dataDasar();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $token = $this->token($administrator);
        $parameter = [
            'anggotaKelas' => $data['anggota_belum']->id,
            'tanggal' => '2026-08-13',
            'kegiatan_ibadah_id' => $data['kegiatan']->id,
        ];

        $this->withToken($token)
            ->getJson(route('api.v1.rekap-kegiatan-ibadah.koreksi.show', $parameter))
            ->assertOk()
            ->assertJsonPath('data.anggota_kelas.siswa.nama', 'Siswa Belum Ibadah')
            ->assertJsonPath('data.presensi', null)
            ->assertJsonPath('data.nilai_awal.status', 'belum')
            ->assertJsonPath('data.nilai_awal.waktu', '12:00')
            ->assertJsonPath('data.dapat_input_baru', true);

        $this->withToken($token)
            ->putJson(route('api.v1.rekap-kegiatan-ibadah.koreksi.update', ['anggotaKelas' => $data['anggota_belum']->id]), [
                'tanggal' => '2026-08-13',
                'kegiatan_ibadah_id' => $data['kegiatan']->id,
                'status_presensi' => 'sudah',
                'waktu_presensi' => '12:10',
                'alasan' => 'Siswa lupa kartu dan kehadiran dikonfirmasi guru piket.',
            ])
            ->assertOk()
            ->assertJsonPath('data.presensi.sumber', 'manual')
            ->assertJsonPath('data.presensi.waktu', '12:10')
            ->assertJsonPath('data.riwayat.0.tindakan', 'tambah');

        $this->assertDatabaseHas('riwayat_koreksi_kegiatan_ibadah', [
            'anggota_kelas_id' => $data['anggota_belum']->id,
            'tindakan' => 'tambah',
            'hadir_sebelum' => false,
            'hadir_sesudah' => true,
        ]);

        $this->withToken($token)
            ->putJson(route('api.v1.rekap-kegiatan-ibadah.koreksi.update', ['anggotaKelas' => $data['anggota_belum']->id]), [
                'tanggal' => '2026-08-13',
                'kegiatan_ibadah_id' => $data['kegiatan']->id,
                'status_presensi' => 'belum',
                'alasan' => 'Catatan ternyata milik siswa lain dan harus dibatalkan.',
            ])
            ->assertOk()
            ->assertJsonPath('data.presensi', null)
            ->assertJsonPath('data.riwayat.0.tindakan', 'hapus');

        $this->assertDatabaseMissing('presensi_kegiatan_ibadah', [
            'anggota_kelas_id' => $data['anggota_belum']->id,
            'tanggal' => '2026-08-13',
        ]);
    }

    public function test_koreksi_mewajibkan_waktu_dan_alasan(): void
    {
        Carbon::setTestNow('2026-08-13 12:30:00');
        $data = $this->dataDasar();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->withToken($this->token($administrator))
            ->putJson(route('api.v1.rekap-kegiatan-ibadah.koreksi.update', ['anggotaKelas' => $data['anggota_belum']->id]), [
                'tanggal' => '2026-08-13',
                'kegiatan_ibadah_id' => $data['kegiatan']->id,
                'status_presensi' => 'sudah',
                'alasan' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['waktu_presensi', 'alasan']);
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
            'nama' => 'VII.A',
            'tingkat' => 7,
            'aktif' => true,
        ]);
        $sudah = Siswa::create([
            'nama_lengkap' => 'Siswa Sudah Ibadah',
            'nis' => '26001',
            'nisn' => '0131201150',
            'aktif' => true,
        ]);
        $belum = Siswa::create([
            'nama_lengkap' => 'Siswa Belum Ibadah',
            'nis' => '26002',
            'nisn' => '0131201151',
            'aktif' => true,
        ]);
        $anggotaSudah = AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $sudah->id,
            'nomor_absen' => 1,
            'status_keanggotaan' => 'aktif',
        ]);
        $anggotaBelum = AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $belum->id,
            'nomor_absen' => 2,
            'status_keanggotaan' => 'aktif',
        ]);
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
            'kelas' => $kelas,
            'anggota_sudah' => $anggotaSudah,
            'anggota_belum' => $anggotaBelum,
            'kegiatan' => $kegiatan,
            'jadwal' => $jadwal,
        ];
    }

    private function buatPresensi(array $data, AnggotaKelas $anggota, Pengguna $petugas): PresensiKegiatanIbadah
    {
        return PresensiKegiatanIbadah::create([
            'jadwal_kegiatan_ibadah_id' => $data['jadwal']->id,
            'kegiatan_ibadah_id' => $data['kegiatan']->id,
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $anggota->kelas_id,
            'anggota_kelas_id' => $anggota->id,
            'siswa_id' => $anggota->siswa_id,
            'dipindai_oleh_pengguna_id' => $petugas->id,
            'tanggal' => '2026-08-13',
            'waktu_scan' => '12:05:00',
            'sumber' => 'kamera',
        ]);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Perangkat Rekap Ibadah', ['mobile'])->plainTextToken;
    }
}
