<?php

namespace Tests\Feature\Api;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\AturanSanksiPoin;
use App\Models\KategoriPembinaanSiswa;
use App\Models\Kelas;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pegawai;
use App\Models\PendampinganSiswa;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PeringatanDiniSiswa;
use App\Models\SanksiPoinSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RekapPoinSiswaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_dan_profil_disiplin_native_memuat_saldo_resmi_dan_konteks_tindak_lanjut(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$tahun, $kelas, $siswa, $anggota] = $this->buatSiswa('Siswa Rekap Poin Native', '0088331001');
        $petugas = Pegawai::create([
            'nama_lengkap' => 'Guru BK Rekap Native',
            'nip' => '198909092026089009',
            'aktif' => true,
        ]);
        TransaksiPoinSiswa::create([
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'kunci_sumber' => 'rekap-native:poin:1',
            'jenis' => 'pelanggaran',
            'poin' => 20,
            'keterangan' => 'Poin pelanggaran yang sudah disahkan.',
            'tercatat_pada' => now(),
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);
        TransaksiPoinSiswa::create([
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'kunci_sumber' => 'rekap-native:pengurangan:1',
            'jenis' => 'pengurangan',
            'poin' => -5,
            'keterangan' => 'Pengurangan poin yang sudah disetujui.',
            'tercatat_pada' => now()->addMinute(),
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);
        $laporan = LaporanPembinaanSiswa::create([
            'nomor_laporan' => 'PB-RPN-001',
            'jenis_laporan' => 'pelanggaran',
            'tanggal_kejadian' => today(),
            'siswa_id' => $siswa->id,
            'kategori_pembinaan_siswa_id' => KategoriPembinaanSiswa::firstOrFail()->id,
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'anggota_kelas_id' => $anggota->id,
            'tingkat' => 'ringan',
            'status' => 'diproses',
            'status_verifikasi' => 'pemeriksaan_bk',
            'total_poin' => 10,
            'kronologi' => 'Laporan masih diperiksa oleh BK.',
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);
        AbsensiSiswa::create([
            'tanggal' => today(),
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'anggota_kelas_id' => $anggota->id,
            'siswa_id' => $siswa->id,
            'jam_masuk' => '07:12:00',
            'status_masuk' => 'terlambat',
            'menit_terlambat' => 12,
            'status_kehadiran' => 'hadir',
            'sumber' => 'scan',
        ]);
        $peringatan = PeringatanDiniSiswa::create([
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'jenis' => 'mendekati_sanksi',
            'tingkat' => 'peringatan',
            'status' => 'aktif',
            'kunci_unik' => 'rekap-native:peringatan:1',
            'judul' => 'Siswa mendekati ambang sanksi',
            'pesan' => 'Perlu pemantauan poin siswa.',
            'siklus' => 1,
            'terdeteksi_pada' => now(),
            'terakhir_terdeteksi_pada' => now(),
        ]);
        $pendampingan = PendampinganSiswa::create([
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'peringatan_dini_siswa_id' => $peringatan->id,
            'petugas_pegawai_id' => $petugas->id,
            'jenis_tindakan' => 'konseling',
            'tanggal_tindak_lanjut' => today(),
            'catatan' => 'Pendampingan sedang berlangsung.',
            'status' => 'dalam_proses',
            'kunci_aktif' => PendampinganSiswa::kunciAktif($siswa->id, $tahun->id),
        ]);
        $sanksi = SanksiPoinSiswa::create([
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'aturan_sanksi_poin_id' => AturanSanksiPoin::where('batas_poin', 25)->firstOrFail()->id,
            'poin_saat_terpicu' => 25,
            'status' => 'menunggu',
            'terpicu_pada' => now(),
        ]);
        $token = $this->token($administrator);

        $this->getJson(route('api.v1.rekap-poin-siswa.index'))->assertUnauthorized();

        $response = $this->withToken($token)
            ->getJson(route('api.v1.rekap-poin-siswa.index', [
                'tahun_pelajaran_id' => $tahun->id,
                'kelas_id' => $kelas->id,
                'status_perhatian' => 'sanksi_aktif',
                'kata_kunci' => 'native',
            ]))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.total_siswa', 1)
            ->assertJsonPath('data.ringkasan.siswa_berpoin', 1)
            ->assertJsonPath('data.ringkasan.laporan_menunggu', 1)
            ->assertJsonPath('data.ringkasan.sanksi_aktif', 1)
            ->assertJsonPath('data.items.0.siswa.id', $siswa->id)
            ->assertJsonPath('data.items.0.kelas.nama', $kelas->nama)
            ->assertJsonPath('data.items.0.total_poin', 15)
            ->assertJsonPath('data.items.0.indikator.kode', 'sanksi_aktif')
            ->assertJsonPath('data.filter.status_perhatian', 'sanksi_aktif')
            ->assertJsonPath('data.hak_akses.cakupan_luas', true)
            ->assertJsonCount(1, 'data.ringkasan_kelas');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->withToken($token)
            ->getJson(route('api.v1.rekap-poin-siswa.show', [
                'siswa' => $siswa,
                'tahun_pelajaran_id' => $tahun->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.siswa.siswa.nama', $siswa->nama_lengkap)
            ->assertJsonPath('data.ringkasan.total_poin', 15)
            ->assertJsonPath('data.ringkasan.laporan_menunggu', 1)
            ->assertJsonPath('data.ringkasan.poin_dalam_proses', 10)
            ->assertJsonPath('data.ringkasan.sanksi_aktif', 1)
            ->assertJsonPath('data.ringkasan.keterlambatan.jumlah', 1)
            ->assertJsonPath('data.ringkasan.keterlambatan.total_menit', 12)
            ->assertJsonCount(2, 'data.transaksi')
            ->assertJsonPath('data.laporan.0.id', $laporan->id)
            ->assertJsonPath('data.peringatan.0.id', $peringatan->id)
            ->assertJsonPath('data.pendampingan.0.id', $pendampingan->id)
            ->assertJsonPath('data.sanksi.0.id', $sanksi->id)
            ->assertJsonPath('data.keterlambatan.0.menit', 12);

        $this->withToken($token)
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'rekap-poin-siswa',
                'status' => 'tersedia',
                'rute' => '/rekap-poin-siswa',
            ]);
    }

    public function test_guru_wali_hanya_melihat_rekap_siswa_yang_ditugaskan(): void
    {
        [$tahun, $kelas, $siswaDitugaskan] = $this->buatSiswa('Siswa Poin Wali', '0088331002');
        [, , $siswaLain] = $this->buatSiswa('Siswa Poin Lain', '0088331003', $tahun, $kelas);
        foreach ([$siswaDitugaskan, $siswaLain] as $index => $siswa) {
            TransaksiPoinSiswa::create([
                'siswa_id' => $siswa->id,
                'tahun_pelajaran_id' => $tahun->id,
                'kunci_sumber' => 'rekap-wali:'.$index,
                'jenis' => 'pelanggaran',
                'poin' => 5,
                'keterangan' => 'Poin cakupan guru wali.',
                'tercatat_pada' => now(),
            ]);
        }
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Guru Wali Rekap API',
            'nip' => '199001012026090001',
            'aktif' => true,
        ]);
        $akun = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip,
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $akun->daftarPeran()->attach(Peran::where('kode', 'guru_wali')->firstOrFail());
        DB::table('penugasan_guru_wali_siswa')->insert([
            'siswa_id' => $siswaDitugaskan->id,
            'guru_wali_pegawai_id' => $pegawai->id,
            'tanggal_mulai' => today()->toDateString(),
            'aktif' => true,
            'dibuat_oleh_pengguna_id' => Pengguna::where('username', 'administrator')->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $token = $this->token($akun);

        $this->withToken($token)
            ->getJson(route('api.v1.rekap-poin-siswa.index', ['tahun_pelajaran_id' => $tahun->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.siswa.id', $siswaDitugaskan->id)
            ->assertJsonPath('data.hak_akses.cakupan_luas', false);

        $this->withToken($token)
            ->getJson(route('api.v1.rekap-poin-siswa.show', [
                'siswa' => $siswaLain,
                'tahun_pelajaran_id' => $tahun->id,
            ]))
            ->assertForbidden();
    }

    private function buatSiswa(
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
            'nama' => 'VIII.RP',
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

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Perangkat Rekap Poin', ['mobile'])->plainTextToken;
    }
}
