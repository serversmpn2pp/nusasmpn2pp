<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pegawai;
use App\Models\PendampinganSiswa;
use App\Models\PenugasanGuruWaliSiswa;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KonteksGuruWaliTest extends TestCase
{
    use RefreshDatabase;

    public function test_menu_pembinaan_guru_wali_hanya_menampilkan_siswa_dampingan(): void
    {
        $data = $this->buatSkenarioDuaPeran();

        $this->actingAs($data['akun'])
            ->get(route('pembinaan-siswa-wali.index'))
            ->assertOk()
            ->assertSee('Pembinaan Siswa Wali')
            ->assertSee($data['siswa_wali']->nama_lengkap)
            ->assertDontSee($data['siswa_kelas']->nama_lengkap);

        $this->get(route('pembinaan-siswa-wali.show', $data['laporan_wali']))
            ->assertOk()
            ->assertSee($data['siswa_wali']->nama_lengkap);

        $this->get(route('pembinaan-siswa-wali.show', $data['laporan_kelas']))
            ->assertForbidden();
    }

    public function test_menu_tindak_lanjut_guru_wali_hanya_menampilkan_siswa_dampingan(): void
    {
        $data = $this->buatSkenarioDuaPeran();

        $this->actingAs($data['akun'])
            ->get(route('pendampingan-siswa-wali.index'))
            ->assertOk()
            ->assertSee('Tindak Lanjut Siswa Wali')
            ->assertSee($data['siswa_wali']->nama_lengkap)
            ->assertDontSee($data['siswa_kelas']->nama_lengkap);

        $this->get(route('pendampingan-siswa-wali.edit', $data['pendampingan_wali']))
            ->assertOk()
            ->assertSee($data['siswa_wali']->nama_lengkap);

        $this->get(route('pendampingan-siswa-wali.edit', $data['pendampingan_kelas']))
            ->assertForbidden();
    }

    public function test_menu_rekap_poin_guru_wali_hanya_menampilkan_siswa_dampingan(): void
    {
        $data = $this->buatSkenarioDuaPeran();

        $this->actingAs($data['akun'])
            ->get(route('rekap-poin-siswa-wali.index', [
                'tahun_pelajaran_id' => $data['tahun']->id,
            ]))
            ->assertOk()
            ->assertSee('Rekap Poin Siswa Wali')
            ->assertSee($data['siswa_wali']->nama_lengkap)
            ->assertDontSee($data['siswa_kelas']->nama_lengkap);

        $this->get(route('rekap-poin-siswa-wali.show', [
            'siswa' => $data['siswa_wali'],
            'tahun_pelajaran_id' => $data['tahun']->id,
        ]))->assertOk();

        $this->get(route('rekap-poin-siswa-wali.show', [
            'siswa' => $data['siswa_kelas'],
            'tahun_pelajaran_id' => $data['tahun']->id,
        ]))->assertForbidden();
    }

    private function buatSkenarioDuaPeran(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Guru Dua Peran',
            'nip' => '198808082016081008',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $akun = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip,
            'kata_sandi' => 'KataSandi-Uji-2026',
            'peran' => 'pegawai',
            'aktif' => true,
        ]);
        $akun->daftarPeran()->attach(Peran::whereIn('kode', ['wali_kelas', 'guru_wali'])->pluck('id'));

        $kelasWali = $this->buatKelas($tahun, 'VII.A', 7, $pegawai);
        $kelasLain = $this->buatKelas($tahun, 'VIII.B', 8);
        $siswaKelas = $this->buatSiswa($kelasWali, 'Siswa Kelas Bukan Dampingan', '0077000011', 1);
        $siswaWali = $this->buatSiswa($kelasLain, 'Siswa Dampingan Guru Wali', '0088000022', 2);

        PenugasanGuruWaliSiswa::create([
            'siswa_id' => $siswaWali->id,
            'guru_wali_pegawai_id' => $pegawai->id,
            'tanggal_mulai' => '2026-07-15',
            'aktif' => true,
        ]);

        $laporanKelas = $this->buatLaporan($siswaKelas, $kelasWali, $tahun, $pegawai, $akun, 'KLS');
        $laporanWali = $this->buatLaporan($siswaWali, $kelasLain, $tahun, $pegawai, $akun, 'WLI');
        $pendampinganKelas = $this->buatPendampingan($siswaKelas, $tahun, $pegawai, $akun);
        $pendampinganWali = $this->buatPendampingan($siswaWali, $tahun, $pegawai, $akun);

        return [
            'tahun' => $tahun,
            'akun' => $akun,
            'siswa_kelas' => $siswaKelas,
            'siswa_wali' => $siswaWali,
            'laporan_kelas' => $laporanKelas,
            'laporan_wali' => $laporanWali,
            'pendampingan_kelas' => $pendampinganKelas,
            'pendampingan_wali' => $pendampinganWali,
        ];
    }

    private function buatKelas(
        TahunPelajaran $tahun,
        string $nama,
        int $tingkat,
        ?Pegawai $waliKelas = null,
    ): Kelas {
        return Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'wali_kelas_id' => $waliKelas?->id,
            'nama' => $nama,
            'tingkat' => $tingkat,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
    }

    private function buatSiswa(Kelas $kelas, string $nama, string $nisn, int $nomorAbsen): Siswa
    {
        $siswa = Siswa::create([
            'nama_lengkap' => $nama,
            'nis' => '26'.str_pad((string) $nomorAbsen, 5, '0', STR_PAD_LEFT),
            'nisn' => $nisn,
            'jenis_kelamin' => $nomorAbsen % 2 === 0 ? 'P' : 'L',
            'aktif' => true,
        ]);

        AnggotaKelas::create([
            'tahun_pelajaran_id' => $kelas->tahun_pelajaran_id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => $nomorAbsen,
            'status_keanggotaan' => 'aktif',
        ]);

        return $siswa;
    }

    private function buatLaporan(
        Siswa $siswa,
        Kelas $kelas,
        TahunPelajaran $tahun,
        Pegawai $pegawai,
        Pengguna $akun,
        string $kode,
    ): LaporanPembinaanSiswa {
        return LaporanPembinaanSiswa::create([
            'nomor_laporan' => 'PB-UJI-'.$kode,
            'jenis_laporan' => 'pembinaan',
            'tanggal_kejadian' => '2026-08-01',
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'pelapor_pegawai_id' => $pegawai->id,
            'tingkat' => 'ringan',
            'status' => 'baru',
            'status_verifikasi' => 'tidak_perlu',
            'total_poin' => 0,
            'kronologi' => 'Catatan pengujian pemisahan konteks Guru Wali.',
            'dibuat_oleh_pengguna_id' => $akun->id,
        ]);
    }

    private function buatPendampingan(
        Siswa $siswa,
        TahunPelajaran $tahun,
        Pegawai $pegawai,
        Pengguna $akun,
    ): PendampinganSiswa {
        return PendampinganSiswa::create([
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'petugas_pegawai_id' => $pegawai->id,
            'jenis_tindakan' => 'pembinaan_wali',
            'tanggal_tindak_lanjut' => '2026-08-02',
            'catatan' => 'Pendampingan untuk pengujian.',
            'status' => 'dalam_proses',
            'kunci_aktif' => PendampinganSiswa::kunciAktif($siswa->id, $tahun->id),
            'dibuat_oleh_pengguna_id' => $akun->id,
            'diperbarui_oleh_pengguna_id' => $akun->id,
        ]);
    }
}
