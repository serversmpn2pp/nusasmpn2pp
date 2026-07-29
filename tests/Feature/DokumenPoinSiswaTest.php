<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\AturanSanksiPoin;
use App\Models\ButirPelanggaranLaporan;
use App\Models\JenisPelanggaranSiswa;
use App\Models\KategoriPembinaanSiswa;
use App\Models\Kelas;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pegawai;
use App\Models\PendampinganSiswa;
use App\Models\Pengguna;
use App\Models\PenguranganPoinSiswa;
use App\Models\PenugasanGuruWaliSiswa;
use App\Models\Peran;
use App\Models\SanksiPoinSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DokumenPoinSiswaTest extends TestCase
{
    use RefreshDatabase;

    public function test_laporan_individual_hanya_memuat_data_resmi_dan_penandatangan_otomatis(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$tahun, $kelas, $siswa, $waliKelas, $guruWali] = $this->buatSiswaLengkap();
        $guruBk = $this->buatAkunPeran('Guru BK Dokumen', '198001012010011001', 'bk')[0];

        $laporanResmi = $this->buatLaporan($tahun, $kelas, $siswa, 'PB-DOK-001', 'disahkan', 15);
        ButirPelanggaranLaporan::create([
            'laporan_pembinaan_siswa_id' => $laporanResmi->id,
            'jenis_pelanggaran_siswa_id' => JenisPelanggaranSiswa::firstOrFail()->id,
            'kode_pelanggaran' => 'A-01',
            'nama_pelanggaran' => 'Datang terlambat berulang',
            'tingkat' => 'ringan',
            'poin' => 15,
        ]);
        $laporanMenunggu = $this->buatLaporan($tahun, $kelas, $siswa, 'PB-DOK-002', 'pemeriksaan_bk', 20);

        TransaksiPoinSiswa::create([
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'laporan_pembinaan_siswa_id' => $laporanResmi->id,
            'kunci_sumber' => 'dokumen-poin:pelanggaran',
            'jenis' => 'pelanggaran',
            'poin' => 15,
            'keterangan' => 'Poin pelanggaran resmi.',
            'tercatat_pada' => '2026-08-10 08:00:00',
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);
        $pengurangan = PenguranganPoinSiswa::create([
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'tanggal_kegiatan' => '2026-09-01',
            'jenis_kegiatan' => 'Menjadi petugas upacara',
            'deskripsi' => 'Melaksanakan tugas dengan baik.',
            'poin_pengurangan' => 5,
            'status' => 'disetujui',
            'disetujui_oleh_pegawai_id' => $guruBk->id,
            'diputuskan_pada' => '2026-09-01 10:00:00',
        ]);
        TransaksiPoinSiswa::create([
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'pengurangan_poin_siswa_id' => $pengurangan->id,
            'kunci_sumber' => 'dokumen-poin:pengurangan',
            'jenis' => 'pengurangan',
            'poin' => -5,
            'keterangan' => 'Pengurangan poin disetujui.',
            'tercatat_pada' => '2026-09-01 10:00:00',
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);

        SanksiPoinSiswa::create([
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'aturan_sanksi_poin_id' => AturanSanksiPoin::firstOrFail()->id,
            'poin_saat_terpicu' => 15,
            'status' => 'diproses',
            'terpicu_pada' => '2026-08-11 08:00:00',
            'petugas_pegawai_id' => $guruBk->id,
        ]);
        PendampinganSiswa::create([
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'petugas_pegawai_id' => $guruWali->id,
            'jenis_tindakan' => 'pembinaan_wali',
            'tanggal_tindak_lanjut' => '2026-08-12',
            'catatan' => 'Pembinaan bersama guru wali.',
            'status' => 'selesai',
            'hasil' => 'Siswa berkomitmen datang lebih awal.',
            'selesai_pada' => '2026-08-12 10:00:00',
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);

        $this->actingAs($administrator)
            ->get(route('dokumen-poin-siswa.laporan', [
                'siswa' => $siswa,
                'tahun_pelajaran_id' => $tahun->id,
                'periode' => 'semester',
                'semester' => 'ganjil',
            ]))
            ->assertOk()
            ->assertSee('LAPORAN POIN INDIVIDUAL SISWA')
            ->assertSee($siswa->nama_lengkap)
            ->assertSee($laporanResmi->nomor_laporan)
            ->assertDontSee($laporanMenunggu->nomor_laporan)
            ->assertSee('Datang terlambat berulang')
            ->assertSee('Menjadi petugas upacara')
            ->assertSee('Siswa berkomitmen datang lebih awal.')
            ->assertSee($guruBk->nama_lengkap)
            ->assertSee($waliKelas->nama_lengkap)
            ->assertSee($guruWali->nama_lengkap)
            ->assertSee('Total poin terkini')
            ->assertSee('10');
    }

    public function test_dua_jenis_surat_dibuat_dari_data_siswa_dan_jadwal_pemanggilan_wajib(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$tahun, $kelas, $siswa] = $this->buatSiswaLengkap();
        [$kepalaSekolah] = $this->buatAkunPeran('Kepala Sekolah Dokumen', '197501012001011001', 'pimpinan');
        [$wakilKesiswaan] = $this->buatAkunPeran('Wakil Kesiswaan Dokumen', '197701012003011002', 'wakil_pimpinan_kesiswaan');
        $laporan = $this->buatLaporan($tahun, $kelas, $siswa, 'PB-SRT-001', 'disahkan', 10);
        ButirPelanggaranLaporan::create([
            'laporan_pembinaan_siswa_id' => $laporan->id,
            'jenis_pelanggaran_siswa_id' => JenisPelanggaranSiswa::firstOrFail()->id,
            'kode_pelanggaran' => 'B-01',
            'nama_pelanggaran' => 'Tidak mematuhi tata tertib',
            'tingkat' => 'ringan',
            'poin' => 10,
        ]);
        TransaksiPoinSiswa::create([
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'laporan_pembinaan_siswa_id' => $laporan->id,
            'kunci_sumber' => 'dokumen-surat:poin',
            'jenis' => 'pelanggaran',
            'poin' => 10,
            'keterangan' => 'Poin surat orang tua.',
            'tercatat_pada' => '2026-08-10 08:00:00',
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);

        $this->actingAs($administrator)
            ->get(route('dokumen-poin-siswa.surat', [
                'siswa' => $siswa,
                'tahun_pelajaran_id' => $tahun->id,
            ]))
            ->assertOk()
            ->assertSee('Buat Surat Orang Tua')
            ->assertSee('Surat Pemberitahuan Poin')
            ->assertSee('Surat Pemanggilan Orang Tua')
            ->assertSee('Bapak Dokumen / Ibu Dokumen');

        $this->get(route('dokumen-poin-siswa.cetak-surat', [
            'siswa' => $siswa,
            'tahun_pelajaran_id' => $tahun->id,
            'jenis_surat' => 'pemberitahuan',
            'nomor_surat' => '421.3/001/SMPN2/2026',
            'tanggal_surat' => '2026-08-15',
            'nama_penerima' => 'Bapak Dokumen',
            'alamat_penerima' => 'Padang Panjang',
        ]))
            ->assertOk()
            ->assertSee('Pemberitahuan Perkembangan Poin Siswa')
            ->assertSee('421.3/001/SMPN2/2026')
            ->assertSee('Tidak mematuhi tata tertib')
            ->assertSee($kepalaSekolah->nama_lengkap)
            ->assertSee($wakilKesiswaan->nama_lengkap);

        $this->get(route('dokumen-poin-siswa.cetak-surat', [
            'siswa' => $siswa,
            'tahun_pelajaran_id' => $tahun->id,
            'jenis_surat' => 'pemanggilan',
            'tanggal_surat' => '2026-08-15',
            'nama_penerima' => 'Bapak Dokumen',
        ]))->assertSessionHasErrors(['tanggal_pertemuan', 'jam_pertemuan', 'tempat_pertemuan', 'keperluan']);

        $this->get(route('dokumen-poin-siswa.cetak-surat', [
            'siswa' => $siswa,
            'tahun_pelajaran_id' => $tahun->id,
            'jenis_surat' => 'pemanggilan',
            'tanggal_surat' => '2026-08-15',
            'nama_penerima' => 'Bapak Dokumen',
            'tanggal_pertemuan' => '2026-08-20',
            'jam_pertemuan' => '09:00',
            'tempat_pertemuan' => 'Ruang BK',
            'keperluan' => 'Membahas pembinaan siswa.',
        ]))
            ->assertOk()
            ->assertSee('Pemanggilan Orang Tua/Wali Siswa')
            ->assertSee('Ruang BK')
            ->assertSee('09:00 WIB');
    }

    public function test_guru_wali_tidak_dapat_mencetak_dokumen_siswa_di_luar_tugasnya(): void
    {
        [$tahun, $kelas, $siswaDitugaskan, , $guruWali] = $this->buatSiswaLengkap();
        $siswaLain = Siswa::create([
            'nama_lengkap' => 'Siswa Di Luar Dokumen',
            'nis' => 'DOK-002',
            'nisn' => '0077000002',
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswaLain->id,
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => $tahun->tanggal_mulai,
        ]);
        $akunGuruWali = $guruWali->pengguna;

        $this->actingAs($akunGuruWali)
            ->get(route('dokumen-poin-siswa.laporan', [
                'siswa' => $siswaDitugaskan,
                'tahun_pelajaran_id' => $tahun->id,
            ]))->assertOk();

        $this->get(route('dokumen-poin-siswa.laporan', [
            'siswa' => $siswaLain,
            'tahun_pelajaran_id' => $tahun->id,
        ]))->assertForbidden();

        $this->get(route('dokumen-poin-siswa.surat', [
            'siswa' => $siswaLain,
            'tahun_pelajaran_id' => $tahun->id,
        ]))->assertForbidden();
    }

    private function buatSiswaLengkap(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        [$waliKelas] = $this->buatAkunPeran('Wali Kelas Dokumen', '198201012012011002', 'wali_kelas');
        [$guruWali, $akunGuruWali] = $this->buatAkunPeran('Guru Wali Dokumen', '198301012013011003', 'guru_wali');
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'wali_kelas_id' => $waliKelas->id,
            'nama' => 'VIII.D',
            'tingkat' => 8,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $siswa = Siswa::create([
            'nama_lengkap' => 'Siswa Dokumen Individual',
            'nis' => 'DOK-001',
            'nisn' => '0077000001',
            'nama_ayah' => 'Bapak Dokumen',
            'nama_ibu' => 'Ibu Dokumen',
            'alamat' => 'Padang Panjang',
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => $tahun->tanggal_mulai,
        ]);
        PenugasanGuruWaliSiswa::create([
            'siswa_id' => $siswa->id,
            'guru_wali_pegawai_id' => $guruWali->id,
            'tanggal_mulai' => $tahun->tanggal_mulai,
            'aktif' => true,
            'dibuat_oleh_pengguna_id' => Pengguna::where('username', 'administrator')->value('id'),
        ]);

        $akunGuruWali->load('daftarPeran');

        return [$tahun, $kelas, $siswa, $waliKelas, $guruWali];
    }

    private function buatLaporan(
        TahunPelajaran $tahun,
        Kelas $kelas,
        Siswa $siswa,
        string $nomor,
        string $statusVerifikasi,
        int $poin,
    ): LaporanPembinaanSiswa {
        return LaporanPembinaanSiswa::create([
            'nomor_laporan' => $nomor,
            'jenis_laporan' => 'pelanggaran',
            'tanggal_kejadian' => '2026-08-10',
            'tempat_kejadian' => 'Gerbang sekolah',
            'siswa_id' => $siswa->id,
            'kategori_pembinaan_siswa_id' => KategoriPembinaanSiswa::firstOrFail()->id,
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'anggota_kelas_id' => AnggotaKelas::where('siswa_id', $siswa->id)->value('id'),
            'tingkat' => 'ringan',
            'status' => 'selesai',
            'status_verifikasi' => $statusVerifikasi,
            'total_poin' => $poin,
            'kronologi' => 'Kronologi untuk dokumen poin siswa.',
            'dibuat_oleh_pengguna_id' => Pengguna::where('username', 'administrator')->value('id'),
        ]);
    }

    private function buatAkunPeran(string $nama, string $nip, string $kodePeran): array
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => $nama,
            'nip' => $nip,
            'aktif' => true,
        ]);
        $akun = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $nama,
            'username' => $nip,
            'kata_sandi' => 'KataSandi-Uji-2026',
            'peran' => 'pegawai',
            'aktif' => true,
        ]);
        $akun->daftarPeran()->attach(Peran::where('kode', $kodePeran)->firstOrFail());
        $pegawai->setRelation('pengguna', $akun);

        return [$pegawai, $akun];
    }
}
