<?php

namespace Tests\Feature;

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

class RekapBerhalanganIbadahTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_rekap_bulanan_hanya_menampilkan_periode_dalam_cakupan_tanpa_catatan_privat(): void
    {
        Carbon::setTestNow('2026-08-20 14:00:00');
        $data = $this->buatDataDasar();
        $aktif = $this->buatPeriode($data, 'Siswi Aktif Rekap', '0091000001', $data['kelasA'], '2026-08-10', null, PeriodeBerhalanganIbadah::STATUS_AKTIF);
        $perlu = $this->buatPeriode($data, 'Siswi Perlu Rekap', '0091000002', $data['kelasA'], '2026-08-01', null, PeriodeBerhalanganIbadah::STATUS_PERLU_KONFIRMASI);
        $selesai = $this->buatPeriode($data, 'Siswi Selesai Rekap', '0091000003', $data['kelasA'], '2026-07-28', '2026-08-03', PeriodeBerhalanganIbadah::STATUS_SELESAI);
        $luarCakupan = $this->buatPeriode($data, 'Siswi Rahasia Kelas Lain', '0091000004', $data['kelasB'], '2026-08-02', null, PeriodeBerhalanganIbadah::STATUS_PERLU_KONFIRMASI);
        $diLuarBulan = $this->buatPeriode($data, 'Siswi Bulan Lama', '0091000005', $data['kelasA'], '2026-07-01', '2026-07-05', PeriodeBerhalanganIbadah::STATUS_SELESAI);
        KonfirmasiBerhalanganIbadah::create([
            'periode_berhalangan_ibadah_id' => $perlu->id,
            'dikonfirmasi_oleh_pengguna_id' => $data['akunPendamping']->id,
            'hasil' => KonfirmasiBerhalanganIbadah::HASIL_MASIH_BERHALANGAN,
            'dikonfirmasi_pada' => '2026-08-08 13:00:00',
            'konfirmasi_berikutnya_pada' => '2026-08-10',
            'catatan_privat' => 'CATATAN RAHASIA TIDAK BOLEH TAMPIL',
        ]);

        $response = $this->actingAs($data['akunPendamping'])
            ->get(route('rekap-berhalangan-ibadah.index', ['bulan' => '2026-08']));

        $response->assertOk()
            ->assertSee('Rekap Berhalangan')
            ->assertSee($aktif->siswa->nama_lengkap)
            ->assertSee($perlu->siswa->nama_lengkap)
            ->assertSee($selesai->siswa->nama_lengkap)
            ->assertDontSee($luarCakupan->siswa->nama_lengkap)
            ->assertDontSee($diLuarBulan->siswa->nama_lengkap)
            ->assertDontSee('CATATAN RAHASIA TIDAK BOLEH TAMPIL');

        $this->get(route('rekap-berhalangan-ibadah.index', [
            'bulan' => '2026-08',
            'status' => PeriodeBerhalanganIbadah::STATUS_SELESAI,
            'cari' => 'Selesai Rekap',
        ]))->assertOk()
            ->assertSee($selesai->siswa->nama_lengkap)
            ->assertDontSee($aktif->siswa->nama_lengkap);
    }

    public function test_rekap_dapat_dicetak_dan_pengguna_tanpa_penugasan_ditolak(): void
    {
        Carbon::setTestNow('2026-08-20 14:00:00');
        $data = $this->buatDataDasar();
        $periode = $this->buatPeriode($data, 'Siswi Cetak Rekap', '0092000001', $data['kelasA'], '2026-08-01', '2026-08-06', PeriodeBerhalanganIbadah::STATUS_SELESAI);

        $this->actingAs($data['akunPendamping'])
            ->get(route('rekap-berhalangan-ibadah.cetak', ['bulan' => '2026-08']))
            ->assertOk()
            ->assertSee('DOKUMEN INTERNAL')
            ->assertSee($periode->siswa->nama_lengkap)
            ->assertSee('Cetak / Simpan PDF');

        $akunTanpaPenugasan = $this->buatAkunGuruPerempuan('Guru Tanpa Penugasan Rekap', '198505052015052005');
        $this->actingAs($akunTanpaPenugasan)
            ->get(route('rekap-berhalangan-ibadah.index', ['bulan' => '2026-08']))
            ->assertForbidden();
    }

    private function buatDataDasar(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelasA = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'VII.A', 'tingkat' => 7, 'kapasitas' => 32, 'aktif' => true]);
        $kelasB = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'VII.B', 'tingkat' => 7, 'kapasitas' => 32, 'aktif' => true]);
        $akunPendamping = $this->buatAkunGuruPerempuan('Guru Pendamping Rekap', '198404042014042004');
        $penugasan = PenugasanPendampingIbadahSiswi::create([
            'tahun_pelajaran_id' => $tahun->id,
            'pegawai_id' => $akunPendamping->pegawai_id,
            'semua_kelas' => false,
            'aktif' => true,
        ]);
        $penugasan->kelas()->sync([$kelasA->id]);

        return compact('tahun', 'kelasA', 'kelasB', 'akunPendamping');
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
        ])->load('siswa');
    }

    private function buatAkunGuruPerempuan(string $nama, string $nip): Pengguna
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => $nama,
            'nip' => $nip,
            'jenis_kelamin' => 'P',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $akun = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $nama,
            'username' => $nip,
            'kata_sandi' => 'KataSandi-Uji-2026',
            'wajib_ganti_kata_sandi' => false,
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);
        $akun->daftarPeran()->attach(Peran::where('kode', 'pegawai')->value('id'));

        return $akun;
    }
}
