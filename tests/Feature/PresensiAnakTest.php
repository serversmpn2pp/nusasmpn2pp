<?php

namespace Tests\Feature;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\JadwalKegiatanIbadah;
use App\Models\KegiatanIbadah;
use App\Models\Kelas;
use App\Models\PengaturanAbsensi;
use App\Models\Pengguna;
use App\Models\PeriodeBerhalanganIbadah;
use App\Models\PresensiBerhalanganIbadah;
use App\Models\PresensiKegiatanIbadah;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\AkunOrangTuaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresensiAnakTest extends TestCase
{
    use RefreshDatabase;

    public function test_orang_tua_hanya_melihat_presensi_sekolah_anak_yang_terhubung(): void
    {
        $this->travelTo(Carbon::parse('2031-08-20 10:00:00'));
        [$siswa, $siswaLain, $tahun, $kelas, $anggota] = $this->buatDataSiswa();
        $tanggal = Carbon::parse('2031-08-14');
        $this->buatPengaturanAbsensi($tanggal);

        AbsensiSiswa::create([
            'tanggal' => $tanggal->toDateString(),
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'anggota_kelas_id' => $anggota->id,
            'siswa_id' => $siswa->id,
            'jam_masuk' => '06:48:00',
            'status_masuk' => 'terlambat',
            'menit_terlambat' => 8,
            'jam_pulang' => '14:10:00',
            'status_pulang' => 'tepat_waktu',
            'status_kehadiran' => 'hadir',
            'sumber' => 'scan',
        ]);
        AbsensiSiswa::create([
            'tanggal' => $tanggal->toDateString(),
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'anggota_kelas_id' => $siswaLain->anggotaKelas()->firstOrFail()->id,
            'siswa_id' => $siswaLain->id,
            'jam_masuk' => '06:35:00',
            'status_kehadiran' => 'hadir',
            'sumber' => 'scan',
        ]);

        $akun = $this->akunOrangTua($siswa);
        $response = $this->actingAs($akun)
            ->get(route('presensi-anak.index', ['bulan' => '2031-08']));

        $response
            ->assertOk()
            ->assertViewIs('presensi-anak.index')
            ->assertViewHas('siswa', fn (?Siswa $siswaTampil) => $siswaTampil?->is($siswa) === true)
            ->assertViewHas('riwayatSekolah', function ($riwayat) use ($siswa) {
                return $riwayat->filter(fn (array $item) => $item['absensi'])
                    ->every(fn (array $item) => (int) $item['absensi']->siswa_id === (int) $siswa->id);
            })
            ->assertSee('Presensi Anak')
            ->assertSee($siswa->nama_lengkap)
            ->assertSee('06:48')
            ->assertSee('Terlambat 8 menit')
            ->assertDontSee($siswaLain->nama_lengkap);
    }

    public function test_tab_ibadah_menampilkan_presensi_dan_status_berhalangan_tanpa_catatan_privat(): void
    {
        $this->travelTo(Carbon::parse('2031-08-20 10:00:00'));
        [$siswa, , $tahun, $kelas, $anggota] = $this->buatDataSiswa();
        $tanggalHadir = Carbon::parse('2031-08-07');
        $tanggalBerhalangan = $tanggalHadir->copy()->addWeek();
        $kegiatan = KegiatanIbadah::create([
            'kode' => 'DUHUR',
            'nama' => 'Salat Duhur Berjamaah',
            'aktif' => true,
        ]);
        $jadwal = JadwalKegiatanIbadah::create([
            'kegiatan_ibadah_id' => $kegiatan->id,
            'tahun_pelajaran_id' => $tahun->id,
            'hari' => $this->kodeHari($tanggalHadir),
            'urutan_hari' => $tanggalHadir->isoWeekday(),
            'jam_scan_mulai' => '11:45:00',
            'jam_pelaksanaan' => '12:00:00',
            'jam_scan_selesai' => '12:30:00',
            'aktif' => true,
        ]);

        PresensiKegiatanIbadah::create([
            'jadwal_kegiatan_ibadah_id' => $jadwal->id,
            'kegiatan_ibadah_id' => $kegiatan->id,
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'anggota_kelas_id' => $anggota->id,
            'siswa_id' => $siswa->id,
            'tanggal' => $tanggalHadir->toDateString(),
            'waktu_scan' => '11:58:00',
            'sumber' => 'kamera',
        ]);
        $periode = PeriodeBerhalanganIbadah::create([
            'tahun_pelajaran_id' => $tahun->id,
            'siswa_id' => $siswa->id,
            'kelas_id' => $kelas->id,
            'anggota_kelas_id' => $anggota->id,
            'tanggal_mulai' => $tanggalBerhalangan->toDateString(),
            'status' => 'aktif',
            'batas_hari_konfirmasi' => 7,
            'catatan_privat' => 'Catatan kesehatan yang tidak boleh terlihat.',
        ]);
        PresensiBerhalanganIbadah::create([
            'periode_berhalangan_ibadah_id' => $periode->id,
            'jadwal_kegiatan_ibadah_id' => $jadwal->id,
            'kegiatan_ibadah_id' => $kegiatan->id,
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'anggota_kelas_id' => $anggota->id,
            'siswa_id' => $siswa->id,
            'tanggal' => $tanggalBerhalangan->toDateString(),
            'waktu_scan' => '11:55:00',
            'sumber' => 'kamera',
        ]);

        $akun = $this->akunOrangTua($siswa);

        $this->actingAs($akun)
            ->get(route('presensi-anak.index', ['tab' => 'ibadah', 'bulan' => '2031-08']))
            ->assertOk()
            ->assertSee('Presensi Ibadah')
            ->assertSee('Salat Duhur Berjamaah')
            ->assertSee('Tercatat')
            ->assertSee('Berhalangan')
            ->assertDontSee('Catatan kesehatan yang tidak boleh terlihat.');
    }

    public function test_akun_bukan_orang_tua_tidak_dapat_membuka_presensi_anak(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->get(route('presensi-anak.index'))
            ->assertForbidden();
    }

    private function buatDataSiswa(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2031/2032',
            'tanggal_mulai' => '2031-07-01',
            'tanggal_selesai' => '2032-06-30',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.A',
            'tingkat' => 7,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $siswa = Siswa::create([
            'nama_lengkap' => 'Anak Orang Tua Presensi',
            'nis' => '310001',
            'nisn' => '0310000001',
            'jenis_kelamin' => 'P',
            'aktif' => true,
        ]);
        $siswaLain = Siswa::create([
            'nama_lengkap' => 'Siswa Lain Rahasia',
            'nis' => '310002',
            'nisn' => '0310000002',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
        $anggota = AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => 1,
            'tanggal_masuk' => '2031-07-01',
            'status_keanggotaan' => 'aktif',
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswaLain->id,
            'nomor_absen' => 2,
            'tanggal_masuk' => '2031-07-01',
            'status_keanggotaan' => 'aktif',
        ]);

        return [$siswa, $siswaLain, $tahun, $kelas, $anggota];
    }

    private function akunOrangTua(Siswa $siswa): Pengguna
    {
        $akun = app(AkunOrangTuaService::class)->buat($siswa);
        $akun->update(['wajib_ganti_kata_sandi' => false]);

        return $akun;
    }

    private function buatPengaturanAbsensi(Carbon $tanggal): void
    {
        PengaturanAbsensi::create([
            'hari' => $this->kodeHari($tanggal),
            'urutan_hari' => $tanggal->isoWeekday(),
            'jam_scan_masuk_mulai' => '06:00:00',
            'jam_masuk' => '06:40:00',
            'jam_scan_masuk_selesai' => '07:30:00',
            'jam_scan_pulang_mulai' => '14:00:00',
            'jam_pulang' => '14:10:00',
            'jam_scan_pulang_selesai' => '15:00:00',
            'aktif' => true,
        ]);
    }

    private function kodeHari(Carbon $tanggal): string
    {
        return [
            1 => 'senin',
            2 => 'selasa',
            3 => 'rabu',
            4 => 'kamis',
            5 => 'jumat',
            6 => 'sabtu',
            7 => 'minggu',
        ][$tanggal->isoWeekday()];
    }
}
