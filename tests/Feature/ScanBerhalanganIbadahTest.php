<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\JadwalKegiatanIbadah;
use App\Models\KegiatanIbadah;
use App\Models\Kelas;
use App\Models\KonfirmasiBerhalanganIbadah;
use App\Models\Pegawai;
use App\Models\PengaturanBerhalanganIbadah;
use App\Models\Pengguna;
use App\Models\PenugasanPendampingIbadahSiswi;
use App\Models\Peran;
use App\Models\PeriodeBerhalanganIbadah;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Ibadah\ProsesScanBerhalanganIbadah;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScanBerhalanganIbadahTest extends TestCase
{
    use RefreshDatabase;

    public function test_scan_harian_memakai_satu_periode_dan_menandai_batas_konfirmasi(): void
    {
        $data = $this->buatDataDasar();
        PengaturanBerhalanganIbadah::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'batas_hari_konfirmasi' => 2,
            'aktif' => true,
        ]);
        $proses = app(ProsesScanBerhalanganIbadah::class);

        $hasilPertama = $proses->proses(
            $this->buatJadwal($data, '2026-08-20'),
            $data['siswi']->nisn,
            $data['akunPendamping'],
            Carbon::parse('2026-08-20 12:00:00'),
        );
        $hasilDuplikat = $proses->proses(
            $this->buatJadwal($data, '2026-08-20'),
            $data['siswi']->nisn,
            $data['akunPendamping'],
            Carbon::parse('2026-08-20 12:05:00'),
        );
        $proses->proses(
            $this->buatJadwal($data, '2026-08-21'),
            $data['siswi']->nisn,
            $data['akunPendamping'],
            Carbon::parse('2026-08-21 12:00:00'),
        );
        $hasilMelewatiBatas = $proses->proses(
            $this->buatJadwal($data, '2026-08-24'),
            $data['siswi']->nisn,
            $data['akunPendamping'],
            Carbon::parse('2026-08-24 12:00:00'),
        );

        $this->assertTrue($hasilPertama['berhasil']);
        $this->assertTrue($hasilPertama['baru']);
        $this->assertSame(1, $hasilPertama['hari_ke']);
        $this->assertTrue($hasilDuplikat['berhasil']);
        $this->assertFalse($hasilDuplikat['baru']);
        $this->assertSame(5, $hasilMelewatiBatas['hari_ke']);
        $this->assertDatabaseCount('periode_berhalangan_ibadah', 1);
        $this->assertDatabaseCount('presensi_berhalangan_ibadah', 3);
        $this->assertDatabaseCount('log_scan_berhalangan_ibadah', 4);
        $periode = PeriodeBerhalanganIbadah::query()->firstOrFail();
        $this->assertSame($data['siswi']->id, $periode->siswa_id);
        $this->assertSame(PeriodeBerhalanganIbadah::STATUS_PERLU_KONFIRMASI, $periode->status);
        $this->assertSame(2, $periode->batas_hari_konfirmasi);
        $this->assertSame('2026-08-24', $periode->perlu_konfirmasi_sejak->toDateString());
    }

    public function test_siswa_laki_laki_dan_kelas_di_luar_cakupan_ditolak(): void
    {
        $data = $this->buatDataDasar(semuaKelas: false);
        $proses = app(ProsesScanBerhalanganIbadah::class);
        $jadwal = $this->buatJadwal($data, '2026-08-20');
        $siswaLakiLaki = Siswa::create([
            'nama_lengkap' => 'Siswa Laki-laki',
            'nis' => '9002',
            'nisn' => '0090000002',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $data['kelasA']->id,
            'siswa_id' => $siswaLakiLaki->id,
            'nomor_absen' => 2,
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => '2026-07-01',
        ]);
        $siswiKelasLain = Siswa::create([
            'nama_lengkap' => 'Siswi Kelas Lain',
            'nis' => '9003',
            'nisn' => '0090000003',
            'jenis_kelamin' => 'P',
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $data['kelasB']->id,
            'siswa_id' => $siswiKelasLain->id,
            'nomor_absen' => 1,
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => '2026-07-01',
        ]);

        $hasilLakiLaki = $proses->proses($jadwal, $siswaLakiLaki->nisn, $data['akunPendamping'], Carbon::parse('2026-08-20 12:00:00'));
        $hasilKelasLain = $proses->proses($jadwal, $siswiKelasLain->nisn, $data['akunPendamping'], Carbon::parse('2026-08-20 12:01:00'));

        $this->assertFalse($hasilLakiLaki['berhasil']);
        $this->assertSame('bukan_siswi', $hasilLakiLaki['status']);
        $this->assertFalse($hasilKelasLain['berhasil']);
        $this->assertSame('di_luar_cakupan', $hasilKelasLain['status']);
        $this->assertDatabaseCount('presensi_berhalangan_ibadah', 0);
        $this->assertDatabaseCount('log_scan_berhalangan_ibadah', 2);
    }

    public function test_halaman_privat_hanya_dapat_dibuka_pendamping_yang_ditugaskan(): void
    {
        $data = $this->buatDataDasar();
        $this->buatJadwal($data, now()->toDateString(), '00:00', '23:59');
        $akunBukanPendamping = $this->buatAkunGuruPerempuan('Guru Perempuan Bukan Pendamping', '198002022010022002');

        $this->actingAs($data['akunPendamping'])
            ->get(route('scan-berhalangan-ibadah.index'))
            ->assertOk()
            ->assertSee('MODE PRIVAT')
            ->assertSee('Scan Berhalangan Ibadah')
            ->assertSee('data-scan-active="1"', false)
            ->assertSee('>Mulai kamera</button>', false)
            ->assertDontSee('Presensi Terbaru');

        $this->actingAs($akunBukanPendamping)
            ->get(route('scan-berhalangan-ibadah.index'))
            ->assertForbidden();
    }

    public function test_pendamping_dapat_mengonfirmasi_masih_berhalangan_dan_mendapat_pengingat_ulang(): void
    {
        Carbon::setTestNow('2026-08-24 13:00:00');
        $data = $this->buatDataDasar();
        PengaturanBerhalanganIbadah::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'batas_hari_konfirmasi' => 2,
            'aktif' => true,
        ]);
        $proses = app(ProsesScanBerhalanganIbadah::class);
        $proses->proses(
            $this->buatJadwal($data, '2026-08-20'),
            $data['siswi']->nisn,
            $data['akunPendamping'],
            Carbon::parse('2026-08-20 12:00:00'),
        );
        $hasilMelewatiBatas = $proses->proses(
            $this->buatJadwal($data, '2026-08-24'),
            $data['siswi']->nisn,
            $data['akunPendamping'],
            Carbon::parse('2026-08-24 12:00:00'),
        );
        $periode = $hasilMelewatiBatas['periode'];

        $this->assertDatabaseHas('notifikasi_pengguna', [
            'pengguna_id' => $data['akunPendamping']->id,
            'judul' => 'Konfirmasi privat diperlukan',
        ]);
        $notifikasi = $data['akunPendamping']->notifikasiPengguna()->latest('id')->firstOrFail();
        $this->assertStringNotContainsString($data['siswi']->nama_lengkap, $notifikasi->pesan);
        $this->actingAs($data['akunPendamping'])
            ->get(route('konfirmasi-berhalangan-ibadah.index', [
                'kelas_id' => $data['kelasA']->id,
                'cari' => 'Siswi Berhalangan',
            ]))
            ->assertOk()
            ->assertSee('Konfirmasi Privat')
            ->assertSee($data['siswi']->nama_lengkap);
        $this->get(route('konfirmasi-berhalangan-ibadah.show', $periode))
            ->assertOk()
            ->assertSee('Percakapan privat, bukan pemeriksaan.');
        $this->put(route('konfirmasi-berhalangan-ibadah.update', $periode), [
            'hasil' => KonfirmasiBerhalanganIbadah::HASIL_MASIH_BERHALANGAN,
            'jeda_konfirmasi_hari' => 2,
            'catatan_privat' => 'Sudah dikonfirmasi secara pribadi.',
        ])->assertRedirect(route('konfirmasi-berhalangan-ibadah.index'));

        $this->assertDatabaseHas('konfirmasi_berhalangan_ibadah', [
            'periode_berhalangan_ibadah_id' => $periode->id,
            'dikonfirmasi_oleh_pengguna_id' => $data['akunPendamping']->id,
            'hasil' => KonfirmasiBerhalanganIbadah::HASIL_MASIH_BERHALANGAN,
            'catatan_privat' => 'Sudah dikonfirmasi secara pribadi.',
        ]);
        $this->assertDatabaseHas('periode_berhalangan_ibadah', [
            'id' => $periode->id,
            'status' => PeriodeBerhalanganIbadah::STATUS_AKTIF,
        ]);
        $this->assertSame(
            '2026-08-26',
            KonfirmasiBerhalanganIbadah::query()->firstOrFail()->konfirmasi_berikutnya_pada->toDateString()
        );
        $this->assertSame(
            '2026-08-26',
            PeriodeBerhalanganIbadah::query()->findOrFail($periode->id)->konfirmasi_berikutnya_pada->toDateString()
        );

        $hasilSebelumPengingat = $proses->proses(
            $this->buatJadwal($data, '2026-08-25'),
            $data['siswi']->nisn,
            $data['akunPendamping'],
            Carbon::parse('2026-08-25 12:00:00'),
        );
        $hasilSaatPengingat = $proses->proses(
            $this->buatJadwal($data, '2026-08-26'),
            $data['siswi']->nisn,
            $data['akunPendamping'],
            Carbon::parse('2026-08-26 12:00:00'),
        );

        $this->assertSame(PeriodeBerhalanganIbadah::STATUS_AKTIF, $hasilSebelumPengingat['periode']->status);
        $this->assertSame(PeriodeBerhalanganIbadah::STATUS_PERLU_KONFIRMASI, $hasilSaatPengingat['periode']->status);

        Carbon::setTestNow();
    }

    public function test_konfirmasi_selesai_menutup_periode_dan_pendamping_di_luar_cakupan_ditolak(): void
    {
        Carbon::setTestNow('2026-08-24 13:00:00');
        $data = $this->buatDataDasar(semuaKelas: false);
        $periode = PeriodeBerhalanganIbadah::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'siswa_id' => $data['siswi']->id,
            'kelas_id' => $data['kelasA']->id,
            'anggota_kelas_id' => AnggotaKelas::where('siswa_id', $data['siswi']->id)->value('id'),
            'tanggal_mulai' => '2026-08-17',
            'status' => PeriodeBerhalanganIbadah::STATUS_PERLU_KONFIRMASI,
            'batas_hari_konfirmasi' => 7,
            'perlu_konfirmasi_sejak' => '2026-08-24',
        ]);
        $akunDiLuarCakupan = $this->buatAkunGuruPerempuan('Pendamping Kelas Lain', '198303032013032003');
        $penugasan = PenugasanPendampingIbadahSiswi::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'pegawai_id' => $akunDiLuarCakupan->pegawai_id,
            'semua_kelas' => false,
            'aktif' => true,
        ]);
        $penugasan->kelas()->sync([$data['kelasB']->id]);

        $this->actingAs($akunDiLuarCakupan)
            ->get(route('konfirmasi-berhalangan-ibadah.show', $periode))
            ->assertForbidden();

        $this->actingAs($data['akunPendamping'])
            ->put(route('konfirmasi-berhalangan-ibadah.update', $periode), [
                'hasil' => KonfirmasiBerhalanganIbadah::HASIL_SELESAI,
                'catatan_privat' => null,
            ])->assertRedirect(route('konfirmasi-berhalangan-ibadah.index'));

        $this->assertDatabaseHas('periode_berhalangan_ibadah', [
            'id' => $periode->id,
            'status' => PeriodeBerhalanganIbadah::STATUS_SELESAI,
            'cara_selesai' => 'konfirmasi_privat',
            'diselesaikan_oleh_pengguna_id' => $data['akunPendamping']->id,
        ]);
        $this->assertSame(
            '2026-08-24',
            PeriodeBerhalanganIbadah::query()->findOrFail($periode->id)->tanggal_selesai->toDateString()
        );

        Carbon::setTestNow();
    }

    private function buatDataDasar(bool $semuaKelas = true): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelasA = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'VII.A', 'tingkat' => 7, 'kapasitas' => 32, 'aktif' => true]);
        $kelasB = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'VII.B', 'tingkat' => 7, 'kapasitas' => 32, 'aktif' => true]);
        $siswi = Siswa::create([
            'nama_lengkap' => 'Siswi Berhalangan Uji',
            'nis' => '9001',
            'nisn' => '0090000001',
            'jenis_kelamin' => 'P',
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelasA->id,
            'siswa_id' => $siswi->id,
            'nomor_absen' => 1,
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => '2026-07-01',
        ]);
        $akunPendamping = $this->buatAkunGuruPerempuan('Guru Pendamping Scan', '198001012010012001');
        $penugasan = PenugasanPendampingIbadahSiswi::create([
            'tahun_pelajaran_id' => $tahun->id,
            'pegawai_id' => $akunPendamping->pegawai_id,
            'semua_kelas' => $semuaKelas,
            'aktif' => true,
            'ditugaskan_oleh_pengguna_id' => Pengguna::where('username', 'administrator')->value('id'),
        ]);
        if (! $semuaKelas) {
            $penugasan->kelas()->sync([$kelasA->id]);
        }

        return compact('tahun', 'kelasA', 'kelasB', 'siswi', 'akunPendamping') + [
            'kegiatan' => KegiatanIbadah::where('kode', 'sholat_duhur')->firstOrFail(),
        ];
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

    private function buatJadwal(array $data, string $tanggal, string $mulai = '11:00', string $selesai = '13:00'): JadwalKegiatanIbadah
    {
        $waktu = Carbon::parse($tanggal);
        $hari = array_keys(JadwalKegiatanIbadah::DAFTAR_HARI)[$waktu->dayOfWeekIso - 1];

        return JadwalKegiatanIbadah::query()->updateOrCreate(
            [
                'kegiatan_ibadah_id' => $data['kegiatan']->id,
                'tahun_pelajaran_id' => $data['tahun']->id,
                'hari' => $hari,
            ],
            [
                'urutan_hari' => JadwalKegiatanIbadah::DAFTAR_HARI[$hari]['urutan'],
                'jam_scan_mulai' => $mulai,
                'jam_pelaksanaan' => '12:00',
                'jam_scan_selesai' => $selesai,
                'aktif' => true,
            ],
        );
    }
}
