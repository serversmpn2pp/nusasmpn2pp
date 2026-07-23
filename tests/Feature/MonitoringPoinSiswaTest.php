<?php

namespace Tests\Feature;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\KategoriPembinaanSiswa;
use App\Models\Kelas;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MonitoringPoinSiswaTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_melihat_ringkasan_dan_profil_disiplin_siswa(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$tahun, $kelas, $siswa, $anggota] = $this->buatSiswaDalamKelas('Siswa Monitoring Utama', '0099110001');

        TransaksiPoinSiswa::create([
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'kunci_sumber' => 'uji-monitoring:1',
            'jenis' => 'pelanggaran',
            'poin' => 20,
            'keterangan' => 'Poin resmi untuk pengujian monitoring.',
            'tercatat_pada' => now(),
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);

        $laporan = LaporanPembinaanSiswa::create([
            'nomor_laporan' => 'PB-MON-001',
            'jenis_laporan' => 'pelanggaran',
            'tanggal_kejadian' => now()->toDateString(),
            'siswa_id' => $siswa->id,
            'kategori_pembinaan_siswa_id' => KategoriPembinaanSiswa::firstOrFail()->id,
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'anggota_kelas_id' => $anggota->id,
            'tingkat' => 'ringan',
            'status' => 'diproses',
            'status_verifikasi' => 'pemeriksaan_bk',
            'total_poin' => 10,
            'kronologi' => 'Laporan masih menunggu pemeriksaan dan belum menjadi poin resmi.',
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);

        AbsensiSiswa::create([
            'tanggal' => now()->toDateString(),
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'anggota_kelas_id' => $anggota->id,
            'siswa_id' => $siswa->id,
            'jam_masuk' => '07:08:00',
            'status_masuk' => 'terlambat',
            'menit_terlambat' => 8,
            'status_kehadiran' => 'hadir',
            'sumber' => 'scan',
        ]);

        $this->actingAs($administrator)
            ->get(route('rekap-poin-siswa.index', [
                'tahun_pelajaran_id' => $tahun->id,
                'status_perhatian' => 'mendekati_sanksi',
            ]))
            ->assertOk()
            ->assertSee('Monitoring Poin Siswa')
            ->assertSee($siswa->nama_lengkap)
            ->assertSee('Mendekati ambang sanksi')
            ->assertSee('5 poin menuju Teguran Lisan');

        $this->get(route('rekap-poin-siswa.show', [
            'siswa' => $siswa,
            'tahun_pelajaran_id' => $tahun->id,
        ]))
            ->assertOk()
            ->assertSee('Profil Disiplin Siswa')
            ->assertSee('20 poin saat ini')
            ->assertSee('Potensi 10 poin, belum masuk saldo resmi')
            ->assertSee($laporan->nomor_laporan)
            ->assertSee('8 menit');
    }

    public function test_guru_wali_hanya_dapat_membuka_profil_siswa_yang_ditugaskan(): void
    {
        [$tahun, $kelas, $siswaDitugaskan] = $this->buatSiswaDalamKelas('Siswa Wali Terpantau', '0099110002');
        [, , $siswaLain] = $this->buatSiswaDalamKelas('Siswa Di Luar Tugas', '0099110003', $tahun, $kelas);

        $guruWali = Pegawai::create([
            'nama_lengkap' => 'Guru Wali Monitoring',
            'nip' => '198505052011051005',
            'aktif' => true,
        ]);
        $akun = Pengguna::create([
            'pegawai_id' => $guruWali->id,
            'nama' => $guruWali->nama_lengkap,
            'username' => $guruWali->nip,
            'kata_sandi' => 'KataSandi-Uji-2026',
            'peran' => 'pegawai',
            'aktif' => true,
        ]);
        $akun->daftarPeran()->attach(Peran::where('kode', 'guru_wali')->firstOrFail());

        DB::table('penugasan_guru_wali_siswa')->insert([
            'siswa_id' => $siswaDitugaskan->id,
            'guru_wali_pegawai_id' => $guruWali->id,
            'tanggal_mulai' => now()->toDateString(),
            'aktif' => true,
            'dibuat_oleh_pengguna_id' => Pengguna::where('username', 'administrator')->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($akun)
            ->get(route('rekap-poin-siswa.index', ['tahun_pelajaran_id' => $tahun->id]))
            ->assertOk()
            ->assertSee($siswaDitugaskan->nama_lengkap)
            ->assertDontSee($siswaLain->nama_lengkap);

        $this->get(route('rekap-poin-siswa.show', [
            'siswa' => $siswaDitugaskan,
            'tahun_pelajaran_id' => $tahun->id,
        ]))->assertOk();

        $this->get(route('rekap-poin-siswa.show', [
            'siswa' => $siswaLain,
            'tahun_pelajaran_id' => $tahun->id,
        ]))->assertForbidden();
    }

    private function buatSiswaDalamKelas(
        string $nama,
        string $nisn,
        ?TahunPelajaran $tahun = null,
        ?Kelas $kelas = null,
    ): array {
        $tahun ??= TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelas ??= Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VIII.A',
            'tingkat' => 8,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $siswa = Siswa::create([
            'nama_lengkap' => $nama,
            'nis' => 'NIS-'.$nisn,
            'nisn' => $nisn,
            'aktif' => true,
        ]);
        $anggota = AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => $tahun->tanggal_mulai,
        ]);

        return [$tahun, $kelas, $siswa, $anggota];
    }
}
