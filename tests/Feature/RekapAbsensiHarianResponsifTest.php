<?php

namespace Tests\Feature;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RekapAbsensiHarianResponsifTest extends TestCase
{
    use RefreshDatabase;

    public function test_modal_pesan_whatsapp_menjaga_tombol_salin_tetap_terlihat_di_hp(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->get(route('rekap-absensi-harian.index'))
            ->assertOk()
            ->assertSee('data-wa-summary-copy', false)
            ->assertSee('Salin Pesan')
            ->assertSee('grid-template-rows: auto minmax(0, 1fr) auto;', false)
            ->assertSee('max-height: calc(100svh - 20px);', false)
            ->assertSee('Tekan lama pada teks lalu pilih Salin.');
    }

    public function test_rekap_memakai_tahun_aktif_dan_dapat_dicari_berdasarkan_nama_siswa(): void
    {
        TahunPelajaran::query()->update(['aktif' => false]);
        $tahunLama = TahunPelajaran::create([
            'nama' => '2025/2026',
            'tanggal_mulai' => '2025-07-01',
            'tanggal_selesai' => '2026-06-30',
            'aktif' => false,
        ]);
        $tahunAktif = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelasLama = Kelas::create([
            'tahun_pelajaran_id' => $tahunLama->id,
            'nama' => 'VII.Lama',
            'tingkat' => 7,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $kelasAktif = Kelas::create([
            'tahun_pelajaran_id' => $tahunAktif->id,
            'nama' => 'VII.A',
            'tingkat' => 7,
            'kapasitas' => 32,
            'aktif' => true,
        ]);

        $siswaDicari = $this->buatAnggotaKelas($tahunAktif, $kelasAktif, 'ANNISA PUTRI', '0010000001', 1);
        $this->buatAnggotaKelas($tahunAktif, $kelasAktif, 'BUDI AKTIF', '0010000002', 2);
        $this->buatAnggotaKelas($tahunLama, $kelasLama, 'ANNISA TAHUN LAMA', '0010000003', 1);
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->get(route('rekap-absensi-harian.index', [
                'tahun_pelajaran_id' => $tahunLama->id,
                'cari' => 'annisa putri',
            ]))
            ->assertOk()
            ->assertSee($siswaDicari->nama_lengkap)
            ->assertDontSee('BUDI AKTIF')
            ->assertDontSee('ANNISA TAHUN LAMA')
            ->assertSee('id="cari"', false)
            ->assertDontSee('id="tahun_pelajaran_id"', false);
    }

    public function test_rekap_dapat_disaring_berdasarkan_status_presensi_siswa(): void
    {
        TahunPelajaran::query()->update(['aktif' => false]);
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
        $siswaTerlambat = $this->buatAnggotaKelas($tahun, $kelas, 'ALDI TERLAMBAT', '0010000011', 1);
        $siswaSakit = $this->buatAnggotaKelas($tahun, $kelas, 'BUNGA SAKIT', '0010000012', 2);
        $anggotaTerlambat = AnggotaKelas::where('siswa_id', $siswaTerlambat->id)->firstOrFail();
        $anggotaSakit = AnggotaKelas::where('siswa_id', $siswaSakit->id)->firstOrFail();
        $tanggal = '2026-08-21';

        AbsensiSiswa::create([
            'tanggal' => $tanggal,
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'anggota_kelas_id' => $anggotaTerlambat->id,
            'siswa_id' => $siswaTerlambat->id,
            'jam_masuk' => '07:12',
            'status_masuk' => 'terlambat',
            'menit_terlambat' => 12,
            'status_kehadiran' => 'hadir',
            'sumber' => 'scan',
        ]);
        AbsensiSiswa::create([
            'tanggal' => $tanggal,
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'anggota_kelas_id' => $anggotaSakit->id,
            'siswa_id' => $siswaSakit->id,
            'status_kehadiran' => 'sakit',
            'sumber' => 'manual',
        ]);

        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->get(route('rekap-absensi-harian.index', [
                'tanggal' => $tanggal,
                'status' => 'terlambat',
            ]))
            ->assertOk()
            ->assertSee('Status siswa')
            ->assertSee('id="status"', false)
            ->assertSee('value="terlambat" selected', false)
            ->assertSee($siswaTerlambat->nama_lengkap)
            ->assertDontSee($siswaSakit->nama_lengkap);

        $this->get(route('rekap-absensi-harian.index', [
            'tanggal' => $tanggal,
            'status' => 'sakit',
        ]))
            ->assertOk()
            ->assertSee($siswaSakit->nama_lengkap)
            ->assertDontSee($siswaTerlambat->nama_lengkap);
    }

    private function buatAnggotaKelas(
        TahunPelajaran $tahun,
        Kelas $kelas,
        string $nama,
        string $nisn,
        int $nomorAbsen,
    ): Siswa {
        $siswa = Siswa::create([
            'nama_lengkap' => $nama,
            'nis' => $nisn,
            'nisn' => $nisn,
            'jenis_kelamin' => 'P',
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => $nomorAbsen,
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => $tahun->tanggal_mulai,
        ]);

        return $siswa;
    }
}
