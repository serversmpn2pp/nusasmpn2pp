<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LaporanKejadianPegawaiTest extends TestCase
{
    use RefreshDatabase;

    public function test_setiap_akun_pegawai_dapat_melaporkan_dan_melihat_laporannya_sendiri(): void
    {
        $data = $this->dataDasar();

        $this->assertTrue($data['akun_pegawai']->memilikiIzin('poin_siswa.lapor'));

        $responsBeranda = $this->actingAs($data['akun_pegawai'])
            ->get(route('beranda'))
            ->assertOk()
            ->assertSee('Laporkan Kejadian')
            ->assertSee('href="'.route('laporan-pembinaan-siswa.create').'"', false);

        $this->assertGreaterThanOrEqual(
            2,
            substr_count($responsBeranda->getContent(), route('laporan-pembinaan-siswa.create')),
            'Tautan Laporkan Kejadian harus tersedia di sidebar dan aksi cepat dashboard.',
        );

        $this->get(route('laporan-pembinaan-siswa.create'))
            ->assertOk()
            ->assertSee('Pegawai melapor');

        $this->post(route('laporan-pembinaan-siswa.store'), [
            'tanggal_kejadian' => now()->toDateString(),
            'tempat_kejadian' => 'Koridor sekolah',
            'siswa_id' => $data['siswa']->id,
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $data['kelas']->id,
            'kronologi' => 'Pegawai melihat kejadian siswa dan melaporkan fakta yang tersedia.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $laporan = LaporanPembinaanSiswa::where('dibuat_oleh_pengguna_id', $data['akun_pegawai']->id)
            ->firstOrFail();

        $this->assertDatabaseHas('laporan_pembinaan_siswa', [
            'id' => $laporan->id,
            'jenis_laporan' => 'kejadian',
            'pelapor_pegawai_id' => $data['pegawai']->id,
            'status_verifikasi' => 'diajukan',
        ]);

        $this->get(route('laporan-pembinaan-siswa.index'))
            ->assertOk()
            ->assertSee($data['siswa']->nama_lengkap)
            ->assertSee('Laporkan kejadian')
            ->assertSee('class="report-header-actions"', false);

        $this->get(route('laporan-pembinaan-siswa.show', $laporan))
            ->assertOk();
    }

    public function test_pegawai_tidak_dapat_melihat_laporan_pegawai_lain_dan_siswa_tidak_dapat_melapor(): void
    {
        $data = $this->dataDasar();
        $pegawaiLain = $this->buatAkunPegawai('Pegawai Pelapor Lain', '198202022012021002');
        $laporanLain = LaporanPembinaanSiswa::create([
            'nomor_laporan' => 'PB-20260804-9999',
            'jenis_laporan' => 'kejadian',
            'tanggal_kejadian' => now()->toDateString(),
            'siswa_id' => $data['siswa']->id,
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $data['kelas']->id,
            'anggota_kelas_id' => $data['anggota']->id,
            'pelapor_pegawai_id' => $pegawaiLain['pegawai']->id,
            'tingkat' => 'ringan',
            'status' => 'baru',
            'status_verifikasi' => 'diajukan',
            'total_poin' => 0,
            'kronologi' => 'Laporan milik pegawai lain.',
            'dibuat_oleh_pengguna_id' => $pegawaiLain['akun']->id,
        ]);

        $this->actingAs($data['akun_pegawai'])
            ->get(route('laporan-pembinaan-siswa.show', $laporanLain))
            ->assertForbidden();

        $akunSiswa = Pengguna::create([
            'siswa_id' => $data['siswa']->id,
            'nama' => $data['siswa']->nama_lengkap,
            'username' => $data['siswa']->nisn,
            'kata_sandi' => 'KataSandi-Siswa-2026',
            'wajib_ganti_kata_sandi' => false,
            'peran' => 'siswa',
            'aktif' => true,
            'akun_sistem' => false,
        ]);
        $akunSiswa->daftarPeran()->attach(Peran::where('kode', 'siswa')->firstOrFail());

        $this->actingAs($akunSiswa)
            ->get(route('laporan-pembinaan-siswa.create'))
            ->assertForbidden();
    }

    public function test_wali_kelas_mendapat_notifikasi_jika_siswanya_dilaporkan_pegawai_lain(): void
    {
        $data = $this->dataDasar();
        $waliKelas = $this->buatAkunPegawai('Wali Kelas Penerima Notifikasi', '198303032013031003');
        $data['kelas']->update(['wali_kelas_id' => $waliKelas['pegawai']->id]);

        $this->actingAs($data['akun_pegawai'])
            ->post(route('laporan-pembinaan-siswa.store'), [
                'tanggal_kejadian' => now()->toDateString(),
                'tempat_kejadian' => 'Halaman sekolah',
                'siswa_id' => $data['siswa']->id,
                'tahun_pelajaran_id' => $data['tahun']->id,
                'kelas_id' => $data['kelas']->id,
                'kronologi' => 'Pegawai lain melaporkan kejadian siswa kepada sekolah.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $laporanPegawaiLain = LaporanPembinaanSiswa::latest('id')->firstOrFail();
        $this->assertDatabaseHas('notifikasi_pengguna', [
            'pengguna_id' => $waliKelas['akun']->id,
            'jenis' => 'peringatan',
            'judul' => 'Siswa kelas Anda dilaporkan',
            'tautan' => route('laporan-pembinaan-siswa.show', $laporanPegawaiLain, false),
            'kunci_unik' => "laporan-pembinaan-wali-kelas:{$laporanPegawaiLain->id}",
        ]);

        $this->actingAs($waliKelas['akun'])
            ->post(route('laporan-pembinaan-siswa.store'), [
                'tanggal_kejadian' => now()->toDateString(),
                'tempat_kejadian' => 'Dalam kelas',
                'siswa_id' => $data['siswa']->id,
                'tahun_pelajaran_id' => $data['tahun']->id,
                'kelas_id' => $data['kelas']->id,
                'kronologi' => 'Wali kelas membuat laporan untuk siswanya sendiri.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $laporanWaliKelas = LaporanPembinaanSiswa::latest('id')->firstOrFail();
        $this->assertDatabaseMissing('notifikasi_pengguna', [
            'pengguna_id' => $waliKelas['akun']->id,
            'kunci_unik' => "laporan-pembinaan-wali-kelas:{$laporanWaliKelas->id}",
        ]);
    }

    public function test_pegawai_dapat_melaporkan_beberapa_siswa_dalam_satu_kejadian(): void
    {
        $data = $this->dataDasar();
        $siswaKedua = Siswa::create([
            'nama_lengkap' => 'Siswa Kedua Laporan Kolektif',
            'nisn' => '0099000012',
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $data['kelas']->id,
            'siswa_id' => $siswaKedua->id,
            'status_keanggotaan' => 'aktif',
        ]);

        $this->actingAs($data['akun_pegawai'])
            ->get(route('laporan-pembinaan-siswa.create'))
            ->assertOk()
            ->assertSee('Siswa terlapor')
            ->assertSee('Pilih yang tampil')
            ->assertSee('Setiap siswa tetap memperoleh laporan tersendiri.');

        $this->post(route('laporan-pembinaan-siswa.store'), [
            'tanggal_kejadian' => now()->toDateString(),
            'tempat_kejadian' => 'Lapangan sekolah',
            'siswa_ids' => [$data['siswa']->id, $siswaKedua->id],
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $data['kelas']->id,
            'kronologi' => 'Dua siswa terlibat dalam kejadian yang sama pada waktu dan tempat yang sama.',
        ])->assertRedirect(route('laporan-pembinaan-siswa.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('berhasil', '2 laporan siswa berhasil dibuat dari satu kejadian dan dikirim untuk diperiksa.');

        $daftarLaporan = LaporanPembinaanSiswa::where('dibuat_oleh_pengguna_id', $data['akun_pegawai']->id)
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $daftarLaporan);
        $this->assertSame([$data['siswa']->id, $siswaKedua->id], $daftarLaporan->pluck('siswa_id')->all());
        $this->assertSame(2, $daftarLaporan->pluck('nomor_laporan')->unique()->count());
        $this->assertTrue($daftarLaporan->every(fn ($laporan) => $laporan->status_verifikasi === 'diajukan'));
        $this->assertTrue($daftarLaporan->every(fn ($laporan) => (int) $laporan->kelas_id === (int) $data['kelas']->id));

        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $this->actingAs($administrator)->post(route('verifikasi-pelanggaran.bk', $daftarLaporan->first()), [
            'hasil' => 'pembinaan',
            'catatan' => 'Siswa pertama ditetapkan mendapat pembinaan.',
        ])->assertSessionHasNoErrors();

        $this->assertSame('ditetapkan_pembinaan', $daftarLaporan->first()->fresh()->status_verifikasi);
        $this->assertSame('diajukan', $daftarLaporan->last()->fresh()->status_verifikasi);
        $this->assertDatabaseHas('notifikasi_pengguna', [
            'pengguna_id' => $administrator->id,
            'judul' => 'Laporan kolektif menunggu pemeriksaan BK',
            'kunci_unik' => "laporan-kolektif-baru:{$daftarLaporan->first()->id}:{$daftarLaporan->last()->id}",
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
        $siswa = Siswa::create([
            'nama_lengkap' => 'Siswa untuk Laporan Pegawai',
            'nisn' => '0099000011',
            'aktif' => true,
        ]);
        $anggota = AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'status_keanggotaan' => 'aktif',
        ]);
        $pegawai = $this->buatAkunPegawai('Pegawai Pelapor Umum', '198101012011011001');

        return [
            'tahun' => $tahun,
            'kelas' => $kelas,
            'siswa' => $siswa,
            'anggota' => $anggota,
            'pegawai' => $pegawai['pegawai'],
            'akun_pegawai' => $pegawai['akun'],
        ];
    }

    private function buatAkunPegawai(string $nama, string $nip): array
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => $nama,
            'nip' => $nip,
            'jenis_pegawai' => 'Tenaga Kependidikan',
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
        $akun->daftarPeran()->attach(Peran::where('kode', 'pegawai')->firstOrFail());

        return compact('pegawai', 'akun');
    }
}
