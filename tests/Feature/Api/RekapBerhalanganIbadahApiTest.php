<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\KonfirmasiBerhalanganIbadah;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\PenugasanPendampingIbadahSiswi;
use App\Models\Peran;
use App\Models\PeriodeBerhalanganIbadah;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RekapBerhalanganIbadahApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_rekap_mobile_mengikuti_cakupan_filter_dan_tidak_membocorkan_catatan_privat(): void
    {
        Carbon::setTestNow('2026-08-20 14:00:00');
        $data = $this->dataDasar();
        $aktif = $this->buatPeriode($data, 'Siswi Aktif Mobile', '0093000001', $data['kelasA'], '2026-08-10', null, PeriodeBerhalanganIbadah::STATUS_AKTIF);
        $perlu = $this->buatPeriode($data, 'Siswi Perlu Mobile', '0093000002', $data['kelasA'], '2026-08-01', null, PeriodeBerhalanganIbadah::STATUS_PERLU_KONFIRMASI);
        $selesai = $this->buatPeriode($data, 'Siswi Selesai Mobile', '0093000003', $data['kelasA'], '2026-07-28', '2026-08-03', PeriodeBerhalanganIbadah::STATUS_SELESAI);
        $this->buatPeriode($data, 'Siswi Rahasia Kelas Lain', '0093000004', $data['kelasB'], '2026-08-02', null, PeriodeBerhalanganIbadah::STATUS_PERLU_KONFIRMASI);
        $this->buatPeriode($data, 'Siswi Bulan Lama', '0093000005', $data['kelasA'], '2026-07-01', '2026-07-05', PeriodeBerhalanganIbadah::STATUS_SELESAI);
        KonfirmasiBerhalanganIbadah::create([
            'periode_berhalangan_ibadah_id' => $perlu->id,
            'dikonfirmasi_oleh_pengguna_id' => $data['pendamping']->id,
            'hasil' => KonfirmasiBerhalanganIbadah::HASIL_MASIH_BERHALANGAN,
            'dikonfirmasi_pada' => '2026-08-08 13:00:00',
            'konfirmasi_berikutnya_pada' => '2026-08-10',
            'catatan_privat' => 'RAHASIA REKAP MOBILE',
        ]);

        $response = $this->withToken($this->token($data['pendamping']))
            ->getJson(route('api.v1.rekap-berhalangan-ibadah.index', [
                'bulan' => '2026-08',
                'per_halaman' => 2,
            ]));

        $response->assertOk()
            ->assertJsonPath('data.mode_privat', true)
            ->assertJsonPath('data.bulan', '2026-08')
            ->assertJsonPath('data.ringkasan.periode', 3)
            ->assertJsonPath('data.ringkasan.siswi', 3)
            ->assertJsonPath('data.ringkasan.aktif', 1)
            ->assertJsonPath('data.ringkasan.perlu_konfirmasi', 1)
            ->assertJsonPath('data.ringkasan.selesai', 1)
            ->assertJsonPath('data.paginasi.total', 3)
            ->assertJsonPath('data.paginasi.ada_halaman_berikutnya', true)
            ->assertJsonMissing(['nama' => 'Siswi Rahasia Kelas Lain'])
            ->assertJsonMissing(['nama' => 'Siswi Bulan Lama'])
            ->assertJsonMissing(['catatan_privat' => 'RAHASIA REKAP MOBILE']);

        $this->withToken($this->token($data['pendamping']))
            ->getJson(route('api.v1.rekap-berhalangan-ibadah.index', [
                'bulan' => '2026-08',
                'status' => PeriodeBerhalanganIbadah::STATUS_SELESAI,
                'cari' => 'Selesai Mobile',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $selesai->id)
            ->assertJsonPath('data.items.0.siswa.nama', 'Siswi Selesai Mobile');
    }

    public function test_export_memuat_semua_baris_aman_dan_menu_hanya_untuk_pendamping(): void
    {
        Carbon::setTestNow('2026-08-20 14:00:00');
        $data = $this->dataDasar();
        $this->buatPeriode($data, 'Siswi Export Satu', '0094000001', $data['kelasA'], '2026-08-01', null, PeriodeBerhalanganIbadah::STATUS_AKTIF);
        $this->buatPeriode($data, 'Siswi Export Dua', '0094000002', $data['kelasA'], '2026-08-03', null, PeriodeBerhalanganIbadah::STATUS_PERLU_KONFIRMASI);
        $tanpaPenugasan = $this->buatGuruPerempuan('Guru Tanpa Penugasan Rekap API', '198606062016062006');

        $this->withToken($this->token($data['pendamping']))
            ->getJson(route('api.v1.rekap-berhalangan-ibadah.export', [
                'bulan' => '2026-08',
                'per_halaman' => 1,
            ]))
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.paginasi.total', 2)
            ->assertJsonPath('data.paginasi.ada_halaman_berikutnya', false);

        $this->withToken($this->token($data['pendamping']))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'rekap-berhalangan-ibadah',
                'rute' => '/rekap-berhalangan-ibadah',
            ]);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($tanpaPenugasan))
            ->getJson(route('api.v1.rekap-berhalangan-ibadah.index', ['bulan' => '2026-08']))
            ->assertForbidden();

        $this->withToken($this->token($tanpaPenugasan))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonMissing(['kode' => 'rekap-berhalangan-ibadah']);
    }

    private function dataDasar(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelasA = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'VII.A', 'tingkat' => 7, 'kapasitas' => 32, 'aktif' => true]);
        $kelasB = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'VII.B', 'tingkat' => 7, 'kapasitas' => 32, 'aktif' => true]);
        $pendamping = $this->buatGuruPerempuan('Guru Pendamping Rekap API', '198707072017072007');
        $penugasan = PenugasanPendampingIbadahSiswi::create([
            'tahun_pelajaran_id' => $tahun->id,
            'pegawai_id' => $pendamping->pegawai_id,
            'semua_kelas' => false,
            'aktif' => true,
        ]);
        $penugasan->kelas()->sync([$kelasA->id]);

        return compact('tahun', 'kelasA', 'kelasB', 'pendamping');
    }

    private function buatPeriode(
        array $data,
        string $nama,
        string $nisn,
        Kelas $kelas,
        string $tanggalMulai,
        ?string $tanggalSelesai,
        string $status,
    ): PeriodeBerhalanganIbadah {
        $siswa = Siswa::create([
            'nama_lengkap' => $nama,
            'nis' => substr($nisn, -6),
            'nisn' => $nisn,
            'jenis_kelamin' => 'P',
            'aktif' => true,
        ]);
        $anggota = AnggotaKelas::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => $siswa->id,
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => '2026-07-01',
        ]);

        return PeriodeBerhalanganIbadah::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'siswa_id' => $siswa->id,
            'kelas_id' => $kelas->id,
            'anggota_kelas_id' => $anggota->id,
            'tanggal_mulai' => $tanggalMulai,
            'tanggal_selesai' => $tanggalSelesai,
            'status' => $status,
            'batas_hari_konfirmasi' => 7,
            'perlu_konfirmasi_sejak' => $status === PeriodeBerhalanganIbadah::STATUS_PERLU_KONFIRMASI ? '2026-08-08' : null,
            'cara_selesai' => $status === PeriodeBerhalanganIbadah::STATUS_SELESAI ? 'scan_ibadah' : null,
            'catatan_privat' => 'CATATAN INTERNAL TIDAK BOLEH KELUAR',
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
        return $pengguna->createToken('Perangkat Rekap Berhalangan', ['mobile'])->plainTextToken;
    }
}
