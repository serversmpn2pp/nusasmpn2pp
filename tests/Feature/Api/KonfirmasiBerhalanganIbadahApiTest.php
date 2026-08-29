<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\JadwalKegiatanIbadah;
use App\Models\KegiatanIbadah;
use App\Models\Kelas;
use App\Models\KonfirmasiBerhalanganIbadah;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\PenugasanPendampingIbadahSiswi;
use App\Models\Peran;
use App\Models\PeriodeBerhalanganIbadah;
use App\Models\PresensiBerhalanganIbadah;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KonfirmasiBerhalanganIbadahApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_daftar_privat_hanya_dapat_dibuka_pendamping_dan_tidak_membocorkan_catatan(): void
    {
        Carbon::setTestNow('2026-08-24 13:00:00');
        $data = $this->dataDasar();
        $periode = $this->buatPeriode($data, 'Catatan awal yang bersifat privat.');
        $bukanPendamping = $this->buatGuruPerempuan('Guru Tanpa Penugasan', '198202022012022002');

        $this->getJson(route('api.v1.konfirmasi-berhalangan-ibadah.index'))
            ->assertUnauthorized();

        $this->withToken($this->token($bukanPendamping))
            ->getJson(route('api.v1.konfirmasi-berhalangan-ibadah.index'))
            ->assertForbidden();

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($data['pendamping']))
            ->getJson(route('api.v1.konfirmasi-berhalangan-ibadah.index', [
                'kelas_id' => $data['kelas']->id,
                'cari' => 'Privat Mobile',
            ]))
            ->assertOk()
            ->assertJsonPath('data.mode_privat', true)
            ->assertJsonPath('data.ringkasan.perlu_konfirmasi', 1)
            ->assertJsonPath('data.items.0.id', $periode->id)
            ->assertJsonPath('data.items.0.siswa.nama_lengkap', $data['siswi']->nama_lengkap)
            ->assertJsonPath('data.items.0.kelas.nama', $data['kelas']->nama)
            ->assertJsonPath('data.items.0.hari_ke', 8)
            ->assertJsonMissing(['catatan_privat_awal' => 'Catatan awal yang bersifat privat.'])
            ->assertJsonMissing(['catatan_privat' => 'Catatan awal yang bersifat privat.']);

        $this->withToken($this->token($data['pendamping']))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'konfirmasi-berhalangan-ibadah',
                'rute' => '/konfirmasi-berhalangan-ibadah',
            ]);
    }

    public function test_detail_privat_memuat_riwayat_scan_dan_hanya_bisa_diakses_dalam_cakupan(): void
    {
        Carbon::setTestNow('2026-08-24 13:00:00');
        $data = $this->dataDasar();
        $periode = $this->buatPeriode($data, 'Catatan awal privat.');
        PresensiBerhalanganIbadah::create([
            'periode_berhalangan_ibadah_id' => $periode->id,
            'jadwal_kegiatan_ibadah_id' => $data['jadwal']->id,
            'kegiatan_ibadah_id' => $data['kegiatan']->id,
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $data['kelas']->id,
            'anggota_kelas_id' => $data['anggota']->id,
            'siswa_id' => $data['siswi']->id,
            'dipindai_oleh_pengguna_id' => $data['pendamping']->id,
            'tanggal' => '2026-08-24',
            'waktu_scan' => '12:05:00',
            'sumber' => 'scanner_kartu',
        ]);
        $kelasLain = Kelas::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'nama' => 'VII.B',
            'tingkat' => 7,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $luarCakupan = $this->buatGuruPerempuan('Guru Kelas Lain', '198303032013032003');
        $penugasan = PenugasanPendampingIbadahSiswi::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'pegawai_id' => $luarCakupan->pegawai_id,
            'semua_kelas' => false,
            'aktif' => true,
        ]);
        $penugasan->kelas()->sync([$kelasLain->id]);

        $this->withToken($this->token($data['pendamping']))
            ->getJson(route('api.v1.konfirmasi-berhalangan-ibadah.show', $periode))
            ->assertOk()
            ->assertJsonPath('data.dapat_dikonfirmasi', true)
            ->assertJsonPath('data.periode.catatan_privat_awal', 'Catatan awal privat.')
            ->assertJsonPath('data.presensi_harian.0.kegiatan', $data['kegiatan']->nama)
            ->assertJsonPath('data.presensi_harian.0.waktu_scan', '12:05:00');

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($luarCakupan))
            ->getJson(route('api.v1.konfirmasi-berhalangan-ibadah.show', $periode))
            ->assertForbidden();
    }

    public function test_konfirmasi_masih_berhalangan_menyimpan_riwayat_dan_pengingat(): void
    {
        Carbon::setTestNow('2026-08-24 13:00:00');
        $data = $this->dataDasar();
        $periode = $this->buatPeriode($data);

        $this->withToken($this->token($data['pendamping']))
            ->putJson(route('api.v1.konfirmasi-berhalangan-ibadah.update', $periode), [
                'hasil' => KonfirmasiBerhalanganIbadah::HASIL_MASIH_BERHALANGAN,
                'jeda_konfirmasi_hari' => 2,
                'catatan_privat' => 'Sudah dikonfirmasi secara pribadi.',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Konfirmasi privat tersimpan. Periode tetap dipantau sampai pengingat berikutnya.')
            ->assertJsonPath('data.dapat_dikonfirmasi', false)
            ->assertJsonPath('data.periode.status', PeriodeBerhalanganIbadah::STATUS_AKTIF)
            ->assertJsonPath('data.periode.konfirmasi_berikutnya_pada', '2026-08-26')
            ->assertJsonPath('data.riwayat_konfirmasi.0.hasil', KonfirmasiBerhalanganIbadah::HASIL_MASIH_BERHALANGAN)
            ->assertJsonPath('data.riwayat_konfirmasi.0.catatan_privat', 'Sudah dikonfirmasi secara pribadi.');

        $this->assertDatabaseHas('konfirmasi_berhalangan_ibadah', [
            'periode_berhalangan_ibadah_id' => $periode->id,
            'dikonfirmasi_oleh_pengguna_id' => $data['pendamping']->id,
            'hasil' => KonfirmasiBerhalanganIbadah::HASIL_MASIH_BERHALANGAN,
        ]);
    }

    public function test_konfirmasi_selesai_menutup_periode_dan_validasi_pengingat_ditegakkan(): void
    {
        Carbon::setTestNow('2026-08-24 13:00:00');
        $data = $this->dataDasar();
        $periode = $this->buatPeriode($data);

        $this->withToken($this->token($data['pendamping']))
            ->putJson(route('api.v1.konfirmasi-berhalangan-ibadah.update', $periode), [
                'hasil' => KonfirmasiBerhalanganIbadah::HASIL_MASIH_BERHALANGAN,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('jeda_konfirmasi_hari');

        $this->withToken($this->token($data['pendamping']))
            ->putJson(route('api.v1.konfirmasi-berhalangan-ibadah.update', $periode), [
                'hasil' => KonfirmasiBerhalanganIbadah::HASIL_SELESAI,
                'catatan_privat' => null,
            ])
            ->assertOk()
            ->assertJsonPath('data.periode.status', PeriodeBerhalanganIbadah::STATUS_SELESAI)
            ->assertJsonPath('data.periode.tanggal_selesai', '2026-08-24');

        $this->assertDatabaseHas('periode_berhalangan_ibadah', [
            'id' => $periode->id,
            'status' => PeriodeBerhalanganIbadah::STATUS_SELESAI,
            'cara_selesai' => 'konfirmasi_privat',
            'diselesaikan_oleh_pengguna_id' => $data['pendamping']->id,
        ]);
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
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $siswi = Siswa::create([
            'nama_lengkap' => 'Siswi Privat Mobile',
            'nis' => '26001',
            'nisn' => '0131201150',
            'jenis_kelamin' => 'P',
            'aktif' => true,
        ]);
        $anggota = AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswi->id,
            'nomor_absen' => 1,
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => '2026-07-01',
        ]);
        $pendamping = $this->buatGuruPerempuan('Guru Konfirmasi Privat', '198101012011012001');
        $penugasan = PenugasanPendampingIbadahSiswi::create([
            'tahun_pelajaran_id' => $tahun->id,
            'pegawai_id' => $pendamping->pegawai_id,
            'semua_kelas' => false,
            'aktif' => true,
            'ditugaskan_oleh_pengguna_id' => Pengguna::where('username', 'administrator')->value('id'),
        ]);
        $penugasan->kelas()->sync([$kelas->id]);
        $kegiatan = KegiatanIbadah::where('kode', 'sholat_duhur')->firstOrFail();
        $jadwal = JadwalKegiatanIbadah::create([
            'kegiatan_ibadah_id' => $kegiatan->id,
            'tahun_pelajaran_id' => $tahun->id,
            'hari' => 'senin',
            'urutan_hari' => 1,
            'jam_scan_mulai' => '11:30',
            'jam_pelaksanaan' => '12:00',
            'jam_scan_selesai' => '13:00',
            'aktif' => true,
        ]);

        return compact('tahun', 'kelas', 'siswi', 'anggota', 'pendamping', 'kegiatan', 'jadwal');
    }

    private function buatPeriode(array $data, ?string $catatan = null): PeriodeBerhalanganIbadah
    {
        return PeriodeBerhalanganIbadah::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'siswa_id' => $data['siswi']->id,
            'kelas_id' => $data['kelas']->id,
            'anggota_kelas_id' => $data['anggota']->id,
            'tanggal_mulai' => '2026-08-17',
            'status' => PeriodeBerhalanganIbadah::STATUS_PERLU_KONFIRMASI,
            'batas_hari_konfirmasi' => 7,
            'perlu_konfirmasi_sejak' => '2026-08-24',
            'catatan_privat' => $catatan,
        ]);
    }

    private function buatGuruPerempuan(string $nama, string $nip): Pengguna
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => $nama,
            'nip' => $nip,
            'jenis_kelamin' => 'P',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $pengguna = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $nama,
            'username' => $nip,
            'kata_sandi' => 'KataSandi-Uji-2026',
            'wajib_ganti_kata_sandi' => false,
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);
        $pengguna->daftarPeran()->attach(Peran::where('kode', 'pegawai')->value('id'));

        return $pengguna;
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Perangkat Konfirmasi Privat', ['mobile'])->plainTextToken;
    }
}
