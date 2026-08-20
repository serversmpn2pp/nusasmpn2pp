<?php

namespace Tests\Feature;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\PenugasanPendampingIbadahSiswi;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Ibadah\AksesBerhalanganIbadah;
use App\Services\Ibadah\AksesScanKegiatanIbadah;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class GuruPlTest extends TestCase
{
    use RefreshDatabase;

    public function test_role_guru_pl_memiliki_izin_operasional_yang_terbatas(): void
    {
        $peran = Peran::query()->where('kode', 'guru_pl')->firstOrFail();
        $izin = $peran->izin()->pluck('kode');

        $this->assertSame('Guru PL', $peran->nama);
        $this->assertTrue($peran->sistem);
        $this->assertTrue($peran->aktif);
        $this->assertTrue($izin->contains('beranda.akses'));
        $this->assertTrue($izin->contains('pegawai.profil'));
        $this->assertTrue($izin->contains('absensi_pegawai.pribadi'));
        $this->assertTrue($izin->contains('poin_siswa.lapor'));
        $this->assertTrue($izin->contains('ibadah.scan'));
        $this->assertTrue($izin->contains('absensi.koreksi_hari_ini'));
        $this->assertFalse($izin->contains('absensi.koreksi'));
        $this->assertFalse($izin->contains('absensi.scan'));
    }

    public function test_guru_pl_dapat_scan_ibadah_dan_perempuan_yang_ditugaskan_dapat_scan_berhalangan(): void
    {
        $tahun = $this->buatTahunPelajaran();
        [$pegawai, $akun] = $this->buatAkunGuruPl('Guru PL Perempuan', 'PL-0001', 'P');

        $this->assertTrue(app(AksesScanKegiatanIbadah::class)->dapatMemindai($akun, $tahun));
        $this->assertFalse(app(AksesBerhalanganIbadah::class)->dapatMemindai($akun, $tahun));

        PenugasanPendampingIbadahSiswi::create([
            'tahun_pelajaran_id' => $tahun->id,
            'pegawai_id' => $pegawai->id,
            'semua_kelas' => true,
            'aktif' => true,
            'ditugaskan_oleh_pengguna_id' => Pengguna::query()->where('username', 'administrator')->value('id'),
        ]);

        $this->assertTrue(app(AksesBerhalanganIbadah::class)->dapatMemindai($akun, $tahun));

        [, $akunLakiLaki] = $this->buatAkunGuruPl('Guru PL Laki-laki', 'PL-0002', 'L');
        $this->assertFalse(app(AksesBerhalanganIbadah::class)->dapatMemindai($akunLakiLaki, $tahun));
    }

    public function test_guru_pl_hanya_dapat_mengoreksi_presensi_siswa_hari_ini_dengan_catatan(): void
    {
        $this->travelTo(Carbon::parse('2026-08-21 09:00:00'));
        $tahun = $this->buatTahunPelajaran();
        $kelas = $this->buatKelas($tahun);
        [$anggota] = $this->buatSiswaDalamKelas($tahun, $kelas, 'Siswa Koreksi Guru PL', '0011224401');
        [, $akun] = $this->buatAkunGuruPl('Guru PL Presensi', 'PL-0003', 'P');

        $this->actingAs($akun)
            ->get(route('rekap-absensi-harian.index'))
            ->assertOk()
            ->assertSee('Tugas Guru PL')
            ->assertSee('Presensi Siswa Hari Ini')
            ->assertSee('Scan Ibadah Siswa')
            ->assertSee('Akses Guru PL berlaku untuk presensi hari ini');

        $this->get(route('rekap-absensi-harian.index', ['tanggal' => '2026-08-20']))
            ->assertForbidden();

        $this->put(route('rekap-absensi-harian.koreksi.update', $anggota), [
            'tanggal' => '2026-08-21',
            'status_kehadiran' => 'sakit',
            'catatan' => '',
        ])->assertSessionHasErrors('catatan');

        $this->put(route('rekap-absensi-harian.koreksi.update', $anggota), [
            'tanggal' => '2026-08-21',
            'status_kehadiran' => 'sakit',
            'catatan' => 'Surat sakit diterima petugas.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('absensi_siswa', [
            'siswa_id' => $anggota->siswa_id,
            'tanggal' => '2026-08-21 00:00:00',
            'status_kehadiran' => 'sakit',
            'sumber' => 'manual',
        ]);

        $this->put(route('rekap-absensi-harian.koreksi.update', $anggota), [
            'tanggal' => '2026-08-20',
            'status_kehadiran' => 'izin',
            'catatan' => 'Mencoba mengubah hari lampau.',
        ])->assertForbidden();
    }

    public function test_guru_pl_tidak_dapat_mengubah_hasil_scan_atau_presensi_pegawai(): void
    {
        $this->travelTo(Carbon::parse('2026-08-21 09:00:00'));
        $tahun = $this->buatTahunPelajaran();
        $kelas = $this->buatKelas($tahun);
        [$anggota] = $this->buatSiswaDalamKelas($tahun, $kelas, 'Siswa Sudah Scan', '0011224402');
        [$pegawai, $akun] = $this->buatAkunGuruPl('Guru PL Terbatas', 'PL-0004', 'P');
        AbsensiSiswa::create([
            'tanggal' => '2026-08-21',
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'anggota_kelas_id' => $anggota->id,
            'siswa_id' => $anggota->siswa_id,
            'jam_masuk' => '06:45',
            'status_masuk' => 'tepat_waktu',
            'status_kehadiran' => 'hadir',
            'sumber' => 'scan',
        ]);

        $this->actingAs($akun)
            ->get(route('rekap-absensi-harian.koreksi.edit', [
                'anggotaKelas' => $anggota,
                'tanggal' => '2026-08-21',
            ]))
            ->assertForbidden();

        $this->get(route('rekap-absensi-pegawai-harian.koreksi.edit', [
            'pegawai' => $pegawai,
            'tanggal' => '2026-08-21',
        ]))->assertForbidden();
    }

    private function buatTahunPelajaran(): TahunPelajaran
    {
        return TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
    }

    private function buatKelas(TahunPelajaran $tahun): Kelas
    {
        return Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.A',
            'tingkat' => 7,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
    }

    private function buatSiswaDalamKelas(TahunPelajaran $tahun, Kelas $kelas, string $nama, string $nisn): array
    {
        $siswa = Siswa::create([
            'nama_lengkap' => $nama,
            'nisn' => $nisn,
            'aktif' => true,
        ]);
        $anggota = AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => 1,
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => $tahun->tanggal_mulai,
        ]);

        return [$anggota, $siswa];
    }

    private function buatAkunGuruPl(string $nama, string $nip, string $jenisKelamin): array
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => $nama,
            'nip' => $nip,
            'jenis_pegawai' => 'Pegawai',
            'jenis_kelamin' => $jenisKelamin,
            'aktif' => true,
        ]);
        $akun = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $nama,
            'username' => $nip,
            'kata_sandi' => Hash::make('rahasia-guru-pl'),
            'peran' => 'pegawai',
            'aktif' => true,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $akun->daftarPeran()->attach(Peran::query()->where('kode', 'guru_pl')->firstOrFail());

        return [$pegawai, $akun];
    }
}
