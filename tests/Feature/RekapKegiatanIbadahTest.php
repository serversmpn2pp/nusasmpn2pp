<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\JadwalKegiatanIbadah;
use App\Models\JadwalPiketGuru;
use App\Models\Kelas;
use App\Models\KegiatanIbadah;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PresensiKegiatanIbadah;
use App\Models\RiwayatKoreksiKegiatanIbadah;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RekapKegiatanIbadahTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_administrator_dapat_melihat_ringkasan_dan_detail_per_kelas(): void
    {
        Carbon::setTestNow('2026-08-13 12:30:00');
        $data = $this->dataDasar();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->buatPresensi($data, $data['anggota_a1'], $administrator);

        $this->actingAs($administrator)
            ->get(route('rekap-kegiatan-ibadah.index', [
                'tanggal' => '2026-08-13',
                'kegiatan_ibadah_id' => $data['kegiatan']->id,
            ]))
            ->assertOk()
            ->assertSee('Ringkasan per kelas')
            ->assertSee('VII.A')
            ->assertSee('VII.B')
            ->assertSee('dari 2 siswa')
            ->assertSee('dari 1 siswa');

        $this->get(route('rekap-kegiatan-ibadah.index', [
            'tanggal' => '2026-08-13',
            'kegiatan_ibadah_id' => $data['kegiatan']->id,
            'kelas_id' => $data['kelas_a']->id,
        ]))
            ->assertOk()
            ->assertSee('Siswa Kelas VII.A')
            ->assertSee('Siswa Sudah Presensi')
            ->assertSee('Siswa Belum Presensi')
            ->assertSee('Sudah presensi')
            ->assertSee('Belum presensi');
    }

    public function test_filter_belum_presensi_hanya_menampilkan_siswa_yang_belum_scan(): void
    {
        Carbon::setTestNow('2026-08-13 12:30:00');
        $data = $this->dataDasar();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->buatPresensi($data, $data['anggota_a1'], $administrator);

        $this->actingAs($administrator)
            ->get(route('rekap-kegiatan-ibadah.index', [
                'tanggal' => '2026-08-13',
                'kegiatan_ibadah_id' => $data['kegiatan']->id,
                'kelas_id' => $data['kelas_a']->id,
                'status' => 'belum',
            ]))
            ->assertOk()
            ->assertSee('Siswa Belum Presensi')
            ->assertDontSee('Siswa Sudah Presensi');
    }

    public function test_guru_pai_dan_guru_piket_pada_hari_terkait_dapat_melihat_rekap(): void
    {
        Carbon::setTestNow('2026-08-13 12:30:00');
        $data = $this->dataDasar();
        $pai = MataPelajaran::create([
            'kode' => 'PAI7',
            'nama' => 'Pendidikan Agama Islam',
            'kelompok' => 'Agama dan Budi Pekerti',
            'aktif' => true,
        ]);
        $matematika = MataPelajaran::create([
            'kode' => 'MTK7',
            'nama' => 'Matematika',
            'kelompok' => 'Umum',
            'aktif' => true,
        ]);
        $guruPai = $this->buatGuru($data['tahun'], $data['kelas_a'], $pai, 'Guru PAI', '197701012007011001');
        $guruPiket = $this->buatGuru($data['tahun'], $data['kelas_a'], $matematika, 'Guru Piket', '197801012008011002');

        $this->actingAs($guruPai['akun'])
            ->get(route('rekap-kegiatan-ibadah.index', ['tanggal' => '2026-08-13']))
            ->assertOk();
        $this->get(route('rekap-kegiatan-ibadah.bulanan', ['bulan' => '2026-08']))
            ->assertOk();
        $this->get(route('rekap-kegiatan-ibadah.koreksi.edit', [
            'anggotaKelas' => $data['anggota_a1'],
            'tanggal' => '2026-08-13',
            'kegiatan_ibadah_id' => $data['kegiatan']->id,
        ]))->assertOk();

        $this->actingAs($guruPiket['akun'])
            ->get(route('rekap-kegiatan-ibadah.index', ['tanggal' => '2026-08-13']))
            ->assertForbidden();
        $this->get(route('rekap-kegiatan-ibadah.bulanan', ['bulan' => '2026-08']))
            ->assertForbidden();
        $this->get(route('rekap-kegiatan-ibadah.koreksi.edit', [
            'anggotaKelas' => $data['anggota_a1'],
            'tanggal' => '2026-08-13',
            'kegiatan_ibadah_id' => $data['kegiatan']->id,
        ]))->assertForbidden();

        JadwalPiketGuru::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'pegawai_id' => $guruPiket['pegawai']->id,
            'hari' => 'kamis',
            'aktif' => true,
        ]);

        $this->actingAs($guruPiket['akun'])
            ->get(route('rekap-kegiatan-ibadah.index', ['tanggal' => '2026-08-13']))
            ->assertOk();
        $this->get(route('rekap-kegiatan-ibadah.bulanan', ['bulan' => '2026-08']))
            ->assertOk();
        $this->get(route('rekap-kegiatan-ibadah.koreksi.edit', [
            'anggotaKelas' => $data['anggota_a1'],
            'tanggal' => '2026-08-13',
            'kegiatan_ibadah_id' => $data['kegiatan']->id,
        ]))->assertOk();
    }

    public function test_input_koreksi_dan_pembatalan_presensi_manual_menyimpan_riwayat(): void
    {
        Carbon::setTestNow('2026-08-13 12:30:00');
        $data = $this->dataDasar();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $parameter = [
            'anggotaKelas' => $data['anggota_a2'],
            'tanggal' => '2026-08-13',
            'kegiatan_ibadah_id' => $data['kegiatan']->id,
        ];

        $this->actingAs($administrator)
            ->get(route('rekap-kegiatan-ibadah.koreksi.edit', $parameter))
            ->assertOk()
            ->assertSee('Input presensi manual')
            ->assertSee('Siswa Belum Presensi');

        $this->put(route('rekap-kegiatan-ibadah.koreksi.update', $data['anggota_a2']), [
            'tanggal' => '2026-08-13',
            'kegiatan_ibadah_id' => $data['kegiatan']->id,
            'status_presensi' => 'sudah',
            'waktu_presensi' => '12:10',
            'alasan' => 'Siswa lupa membawa kartu dan dikonfirmasi guru piket.',
        ])->assertRedirect(route('rekap-kegiatan-ibadah.index', [
            'tanggal' => '2026-08-13',
            'kegiatan_ibadah_id' => $data['kegiatan']->id,
            'kelas_id' => $data['kelas_a']->id,
        ]));

        $presensi = PresensiKegiatanIbadah::where('siswa_id', $data['anggota_a2']->siswa_id)->firstOrFail();
        $this->assertSame('manual', $presensi->sumber);
        $this->assertSame('12:10', substr((string) $presensi->waktu_scan, 0, 5));
        $this->assertDatabaseHas('riwayat_koreksi_kegiatan_ibadah', [
            'siswa_id' => $data['anggota_a2']->siswa_id,
            'tindakan' => 'tambah',
            'hadir_sebelum' => false,
            'hadir_sesudah' => true,
        ]);

        $this->put(route('rekap-kegiatan-ibadah.koreksi.update', $data['anggota_a2']), [
            'tanggal' => '2026-08-13',
            'kegiatan_ibadah_id' => $data['kegiatan']->id,
            'status_presensi' => 'sudah',
            'waktu_presensi' => '12:15',
            'alasan' => 'Waktu sebelumnya salah tulis dan sudah dikonfirmasi.',
        ])->assertSessionHas('berhasil');

        $this->assertDatabaseHas('riwayat_koreksi_kegiatan_ibadah', [
            'siswa_id' => $data['anggota_a2']->siswa_id,
            'tindakan' => 'ubah',
            'waktu_sesudah' => '12:15:00',
        ]);

        $this->put(route('rekap-kegiatan-ibadah.koreksi.update', $data['anggota_a2']), [
            'tanggal' => '2026-08-13',
            'kegiatan_ibadah_id' => $data['kegiatan']->id,
            'status_presensi' => 'belum',
            'alasan' => 'Catatan ternyata milik siswa lain dan harus dibatalkan.',
        ])->assertSessionHas('berhasil');

        $this->assertDatabaseMissing('presensi_kegiatan_ibadah', [
            'siswa_id' => $data['anggota_a2']->siswa_id,
            'tanggal' => '2026-08-13',
        ]);
        $this->assertDatabaseHas('riwayat_koreksi_kegiatan_ibadah', [
            'siswa_id' => $data['anggota_a2']->siswa_id,
            'tindakan' => 'hapus',
            'hadir_sebelum' => true,
            'hadir_sesudah' => false,
        ]);
        $this->assertSame(3, RiwayatKoreksiKegiatanIbadah::count());
    }

    public function test_koreksi_manual_wajib_memiliki_alasan(): void
    {
        Carbon::setTestNow('2026-08-13 12:30:00');
        $data = $this->dataDasar();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->from(route('rekap-kegiatan-ibadah.koreksi.edit', [
                'anggotaKelas' => $data['anggota_a2'],
                'tanggal' => '2026-08-13',
                'kegiatan_ibadah_id' => $data['kegiatan']->id,
            ]))
            ->put(route('rekap-kegiatan-ibadah.koreksi.update', $data['anggota_a2']), [
                'tanggal' => '2026-08-13',
                'kegiatan_ibadah_id' => $data['kegiatan']->id,
                'status_presensi' => 'sudah',
                'waktu_presensi' => '12:10',
                'alasan' => '',
            ])
            ->assertSessionHasErrors('alasan');

        $this->assertDatabaseCount('presensi_kegiatan_ibadah', 0);
    }

    public function test_ringkasan_bulanan_menghitung_jadwal_capaian_kelas_dan_detail_siswa(): void
    {
        Carbon::setTestNow('2026-08-13 15:00:00');
        $data = $this->dataDasar();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->buatPresensi($data, $data['anggota_a1'], $administrator);
        PresensiKegiatanIbadah::create([
            'jadwal_kegiatan_ibadah_id' => $data['jadwal']->id,
            'kegiatan_ibadah_id' => $data['kegiatan']->id,
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $data['kelas_a']->id,
            'anggota_kelas_id' => $data['anggota_a1']->id,
            'siswa_id' => $data['anggota_a1']->siswa_id,
            'dipindai_oleh_pengguna_id' => $administrator->id,
            'tanggal' => '2026-08-06',
            'waktu_scan' => '12:05:00',
            'sumber' => 'manual',
        ]);
        PresensiKegiatanIbadah::create([
            'jadwal_kegiatan_ibadah_id' => $data['jadwal']->id,
            'kegiatan_ibadah_id' => $data['kegiatan']->id,
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $data['kelas_b']->id,
            'anggota_kelas_id' => $data['anggota_b1']->id,
            'siswa_id' => $data['anggota_b1']->siswa_id,
            'dipindai_oleh_pengguna_id' => $administrator->id,
            'tanggal' => '2026-08-13',
            'waktu_scan' => '12:07:00',
            'sumber' => 'kamera',
        ]);

        $this->actingAs($administrator)
            ->get(route('rekap-kegiatan-ibadah.bulanan', [
                'bulan' => '2026-08',
                'kegiatan_ibadah_id' => $data['kegiatan']->id,
            ]))
            ->assertOk()
            ->assertSee('Ringkasan Ibadah Bulanan')
            ->assertViewHas('tanggalKegiatan', fn ($tanggal) => $tanggal->values()->all() === ['2026-08-06', '2026-08-13'])
            ->assertViewHas('ringkasan', fn (array $ringkasan) =>
                $ringkasan['hari_kegiatan'] === 2
                && $ringkasan['target'] === 6
                && $ringkasan['tercatat'] === 3
                && $ringkasan['belum'] === 3
                && (float) $ringkasan['persentase'] === 50.0
            )
            ->assertViewHas('ringkasanKelas', function ($items) use ($data) {
                $kelasA = $items->first(fn ($item) => (int) $item['kelas']->id === (int) $data['kelas_a']->id);

                return $kelasA['target'] === 4
                    && $kelasA['tercatat'] === 2
                    && $kelasA['belum'] === 2
                    && (float) $kelasA['persentase'] === 50.0;
            });

        $this->get(route('rekap-kegiatan-ibadah.bulanan', [
            'bulan' => '2026-08',
            'kegiatan_ibadah_id' => $data['kegiatan']->id,
            'kelas_id' => $data['kelas_a']->id,
        ]))
            ->assertOk()
            ->assertSee('Capaian Siswa Kelas VII.A')
            ->assertViewHas('detailSiswa', function ($items) use ($data) {
                $siswaA1 = $items->first(fn ($item) => (int) $item['anggota']->id === (int) $data['anggota_a1']->id);
                $siswaA2 = $items->first(fn ($item) => (int) $item['anggota']->id === (int) $data['anggota_a2']->id);

                return $siswaA1['target'] === 2
                    && $siswaA1['tercatat'] === 2
                    && $siswaA1['manual'] === 1
                    && (float) $siswaA1['persentase'] === 100.0
                    && $siswaA2['target'] === 2
                    && $siswaA2['tercatat'] === 0
                    && $siswaA2['belum'] === 2;
            });
    }

    public function test_ringkasan_bulanan_menolak_bulan_masa_depan(): void
    {
        Carbon::setTestNow('2026-08-13 15:00:00');
        $this->dataDasar();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->from(route('rekap-kegiatan-ibadah.bulanan'))
            ->get(route('rekap-kegiatan-ibadah.bulanan', ['bulan' => '2026-09']))
            ->assertRedirect(route('rekap-kegiatan-ibadah.bulanan'))
            ->assertSessionHasErrors('bulan');
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
        $siswaA1 = Siswa::create(['nama_lengkap' => 'Siswa Sudah Presensi', 'nis' => '26001', 'nisn' => '0131201150', 'aktif' => true]);
        $siswaA2 = Siswa::create(['nama_lengkap' => 'Siswa Belum Presensi', 'nis' => '26002', 'nisn' => '0131201151', 'aktif' => true]);
        $siswaB1 = Siswa::create(['nama_lengkap' => 'Siswa Kelas B', 'nis' => '26003', 'nisn' => '0131201152', 'aktif' => true]);
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

    private function buatGuru(TahunPelajaran $tahun, Kelas $kelas, MataPelajaran $mataPelajaran, string $nama, string $nip): array
    {
        $pegawai = Pegawai::create(['nama_lengkap' => $nama, 'nip' => $nip, 'jenis_pegawai' => 'Guru', 'aktif' => true]);
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
