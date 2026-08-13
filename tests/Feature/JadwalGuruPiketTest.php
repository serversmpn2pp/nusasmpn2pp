<?php

namespace Tests\Feature;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\JadwalPiketGuru;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\PengaturanAbsensi;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Absensi\ProsesScanAbsensi;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JadwalGuruPiketTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_administrator_dapat_menjadwalkan_beberapa_guru_mapel_sekaligus(): void
    {
        $data = $this->dataDasar();
        $guruKedua = $this->buatGuruMapel($data['tahun'], $data['kelas'], $data['mata_pelajaran'], 'Guru Piket Kedua', '198002022010021002');
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->post(route('jadwal-piket-guru.store'), [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'hari' => 'kamis',
                'pegawai_ids' => [$data['pegawai']->id, $guruKedua['pegawai']->id],
                'aktif' => 1,
                'keterangan' => 'Tim piket Kamis',
            ])
            ->assertRedirect(route('jadwal-piket-guru.index', ['tahun_pelajaran_id' => $data['tahun']->id]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('jadwal_piket_guru', 2);
        $this->assertDatabaseHas('jadwal_piket_guru', [
            'pegawai_id' => $data['pegawai']->id,
            'hari' => 'kamis',
            'aktif' => true,
        ]);

        $bukanGuru = Pegawai::create([
            'nama_lengkap' => 'Pegawai Bukan Guru Mapel',
            'nip' => '198505052015051005',
            'aktif' => true,
        ]);

        $this->post(route('jadwal-piket-guru.store'), [
            'tahun_pelajaran_id' => $data['tahun']->id,
            'hari' => 'jumat',
            'pegawai_ids' => [$bukanGuru->id],
            'aktif' => 1,
        ])->assertSessionHasErrors('pegawai_ids');
    }

    public function test_guru_yang_sedang_piket_dapat_mencatat_sakit_atau_izin_dengan_riwayat(): void
    {
        Carbon::setTestNow('2026-08-13 08:00:00');
        $data = $this->dataDasar();
        JadwalPiketGuru::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'pegawai_id' => $data['pegawai']->id,
            'hari' => 'kamis',
            'aktif' => true,
        ]);

        $this->actingAs($data['akun'])
            ->get(route('jadwal-piket-saya.index'))
            ->assertOk()
            ->assertSee('Anda bertugas sebagai Guru Piket');

        $this->get(route('piket-kehadiran-siswa.index'))
            ->assertOk()
            ->assertSee($data['siswa']->nama_lengkap)
            ->assertSee('Catat sakit/izin');

        $this->put(route('piket-kehadiran-siswa.update', $data['anggota']), [
            'status_kehadiran' => 'sakit',
            'catatan' => 'Orang tua menyampaikan siswa sedang demam.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $absensi = AbsensiSiswa::where('siswa_id', $data['siswa']->id)->firstOrFail();
        $this->assertSame('sakit', $absensi->status_kehadiran);
        $this->assertSame('guru_piket', $absensi->sumber);
        $this->assertDatabaseHas('riwayat_perubahan_absensi_siswa', [
            'absensi_siswa_id' => $absensi->id,
            'status_sebelum' => null,
            'status_sesudah' => 'sakit',
            'sumber' => 'guru_piket',
            'dibuat_oleh_pengguna_id' => $data['akun']->id,
        ]);
    }

    public function test_guru_tidak_dapat_mencatat_kehadiran_di_luar_hari_piket(): void
    {
        Carbon::setTestNow('2026-08-14 08:00:00');
        $data = $this->dataDasar();
        JadwalPiketGuru::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'pegawai_id' => $data['pegawai']->id,
            'hari' => 'kamis',
            'aktif' => true,
        ]);

        $this->actingAs($data['akun'])
            ->get(route('piket-kehadiran-siswa.index'))
            ->assertForbidden();

        $this->put(route('piket-kehadiran-siswa.update', $data['anggota']), [
            'status_kehadiran' => 'izin',
            'catatan' => 'Izin dari orang tua.',
        ])->assertForbidden();

        $this->assertDatabaseMissing('absensi_siswa', ['siswa_id' => $data['siswa']->id]);
    }

    public function test_scan_tidak_menimpa_catatan_sakit_atau_izin_dari_guru_piket(): void
    {
        $data = $this->dataDasar();
        PengaturanAbsensi::create([
            'hari' => 'kamis',
            'urutan_hari' => 4,
            'jam_scan_masuk_mulai' => '06:00',
            'jam_masuk' => '07:00',
            'jam_scan_masuk_selesai' => '07:30',
            'jam_scan_pulang_mulai' => '14:00',
            'jam_pulang' => '14:10',
            'jam_scan_pulang_selesai' => '15:00',
            'aktif' => true,
        ]);
        AbsensiSiswa::create([
            'tanggal' => '2026-08-13',
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $data['kelas']->id,
            'anggota_kelas_id' => $data['anggota']->id,
            'siswa_id' => $data['siswa']->id,
            'status_kehadiran' => 'izin',
            'sumber' => 'guru_piket',
            'catatan' => 'Izin keluarga.',
        ]);

        $hasil = app(ProsesScanAbsensi::class)->proses(
            $data['siswa']->nisn,
            Carbon::parse('2026-08-13 06:30:00'),
            'masuk',
        );

        $this->assertFalse($hasil['berhasil']);
        $this->assertSame('kehadiran_manual_aktif', $hasil['status']);
        $this->assertStringContainsString('sudah dicatat Izin', $hasil['pesan']);
        $this->assertDatabaseHas('absensi_siswa', [
            'siswa_id' => $data['siswa']->id,
            'status_kehadiran' => 'izin',
            'sumber' => 'guru_piket',
            'jam_masuk' => null,
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
            'aktif' => true,
        ]);
        $mataPelajaran = MataPelajaran::create([
            'kode' => 'MTK7',
            'nama' => 'Matematika',
            'kelompok' => 'Umum',
            'aktif' => true,
        ]);
        $guru = $this->buatGuruMapel($tahun, $kelas, $mataPelajaran, 'Guru Piket Uji', '197901012009011001');
        $siswa = Siswa::create([
            'nama_lengkap' => 'Siswa Piket Uji',
            'nis' => '26001',
            'nisn' => '0123456789',
            'aktif' => true,
        ]);
        $anggota = AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => 1,
            'status_keanggotaan' => 'aktif',
        ]);

        return [
            'tahun' => $tahun,
            'kelas' => $kelas,
            'mata_pelajaran' => $mataPelajaran,
            'pegawai' => $guru['pegawai'],
            'akun' => $guru['akun'],
            'siswa' => $siswa,
            'anggota' => $anggota,
        ];
    }

    private function buatGuruMapel(
        TahunPelajaran $tahun,
        Kelas $kelas,
        MataPelajaran $mataPelajaran,
        string $nama,
        string $nip,
    ): array {
        $pegawai = Pegawai::create([
            'nama_lengkap' => $nama,
            'nip' => $nip,
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'pegawai_id' => $pegawai->id,
            'jenis_penugasan' => 'pengampu',
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
        $akun->daftarPeran()->attach(Peran::whereIn('kode', ['pegawai', 'guru_mapel'])->pluck('id'));

        return compact('pegawai', 'akun');
    }
}
