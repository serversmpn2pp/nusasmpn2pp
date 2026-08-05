<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\Izin;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TabelAnggotaKelasRingkasTest extends TestCase
{
    use RefreshDatabase;

    public function test_tabel_anggota_pada_penempatan_siswa_hanya_memiliki_kolom_utama(): void
    {
        [$tahun, $kelas, $siswa] = $this->buatDataKelas();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->get(route('penempatan-siswa.index', [
                'tahun_pelajaran_id' => $tahun->id,
                'kelas_id' => $kelas->id,
            ]))
            ->assertOk()
            ->assertSee('<table class="employee-table placement-table compact-member-table">', false)
            ->assertSee('<th>No.</th>', false)
            ->assertSee('<th>Nama</th>', false)
            ->assertDontSee('<th class="text-right">Aksi</th>', false)
            ->assertDontSee('<th>Tanggal masuk</th>', false)
            ->assertDontSee('<th>Keterangan</th>', false)
            ->assertSee($siswa->nama_lengkap)
            ->assertSee(route('siswa.show', $siswa))
            ->assertSee('>Simpan nomor</button>', false)
            ->assertSee('>Keluarkan</button>', false);
    }

    public function test_tabel_kelas_wali_hanya_menampilkan_no_nama_dan_aksi_yang_diizinkan(): void
    {
        [, $kelas, $siswa] = $this->buatDataKelas();
        $wali = $this->buatAkunWaliKelas($kelas);

        $this->actingAs($wali)
            ->get(route('kelas-wali.index'))
            ->assertOk()
            ->assertSee('<th>No</th>', false)
            ->assertSee('<th>Nama</th>', false)
            ->assertDontSee('<th class="text-right">Aksi</th>', false)
            ->assertDontSee('<th>NIS / NISN</th>', false)
            ->assertDontSee('<th>JK</th>', false)
            ->assertDontSee('<th>Tempat, tanggal lahir</th>', false)
            ->assertDontSee('<th>Orang tua</th>', false)
            ->assertSee($siswa->nama_lengkap)
            ->assertSee(route('siswa.show', $siswa));
    }

    public function test_kolom_aksi_kelas_wali_disembunyikan_tanpa_izin_melihat_siswa(): void
    {
        [, $kelas, $siswa] = $this->buatDataKelas();
        $peranWali = Peran::where('kode', 'wali_kelas')->firstOrFail();
        $peranWali->izin()->detach(Izin::where('kode', 'siswa.lihat')->value('id'));
        $wali = $this->buatAkunWaliKelas($kelas);

        $this->actingAs($wali)
            ->get(route('kelas-wali.index'))
            ->assertOk()
            ->assertSee($siswa->nama_lengkap)
            ->assertDontSee('<th class="text-right">Aksi</th>', false)
            ->assertDontSee(route('siswa.show', $siswa));
    }

    private function buatDataKelas(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.RINGKAS',
            'tingkat' => 7,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $siswa = Siswa::create([
            'nama_lengkap' => 'Siswa Tampilan Ringkas',
            'nis' => '2600001',
            'nisn' => '0099999991',
            'jenis_kelamin' => 'L',
            'tempat_lahir' => 'Padang Panjang',
            'tanggal_lahir' => '2013-01-15',
            'nama_ayah' => 'Ayah Pengujian',
            'nama_ibu' => 'Ibu Pengujian',
            'aktif' => true,
        ]);

        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => 1,
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => '2026-07-01',
            'keterangan' => 'Penempatan siswa',
        ]);

        return [$tahun, $kelas, $siswa];
    }

    private function buatAkunWaliKelas(Kelas $kelas): Pengguna
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Wali Kelas Ringkas',
            'nip' => '198001012010011991',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $kelas->update(['wali_kelas_id' => $pegawai->id]);

        $pengguna = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => 'wali-kelas-ringkas',
            'kata_sandi' => 'KataSandi-Uji-2026',
            'wajib_ganti_kata_sandi' => false,
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);
        $pengguna->daftarPeran()->attach(Peran::where('kode', 'wali_kelas')->firstOrFail());

        return $pengguna;
    }
}
