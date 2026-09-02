<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PeringatanDiniSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PeringatanDiniSiswaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_mendukung_ringkasan_filter_detail_dan_mengaktifkan_menu_native(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$tahun, $kelas, $siswa] = $this->buatSiswa('Siswa Peringatan Mobile', '0088221001');
        $peringatan = $this->buatPeringatan($tahun, $siswa, 'sering_terlambat', 'penting');

        $this->getJson(route('api.v1.peringatan-dini-siswa.index'))->assertUnauthorized();

        $response = $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.peringatan-dini-siswa.index', [
                'tahun_pelajaran_id' => $tahun->id,
                'kelas_id' => $kelas->id,
                'jenis' => 'sering_terlambat',
                'tingkat' => 'penting',
                'status' => 'aktif',
                'kata_kunci' => 'mobile',
            ]))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.total_aktif', 1)
            ->assertJsonPath('data.ringkasan.penting', 1)
            ->assertJsonPath('data.ringkasan.pola_berulang', 1)
            ->assertJsonPath('data.filter.tahun_pelajaran_id', $tahun->id)
            ->assertJsonPath('data.filter.kelas_id', $kelas->id)
            ->assertJsonPath('data.filter.jenis', 'sering_terlambat')
            ->assertJsonPath('data.hak_akses.dapat_proses', true)
            ->assertJsonPath('data.hak_akses.dapat_kelola_pendampingan', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $peringatan->id)
            ->assertJsonPath('data.items.0.siswa.nama', $siswa->nama_lengkap)
            ->assertJsonPath('data.items.0.kelas.nama', $kelas->nama)
            ->assertJsonPath('data.items.0.data_pendukung_ringkas.0.nilai', '4 kali');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.peringatan-dini-siswa.show', $peringatan))
            ->assertOk()
            ->assertJsonPath('data.peringatan.id', $peringatan->id)
            ->assertJsonPath('data.peringatan.label_jenis', 'Sering Terlambat')
            ->assertJsonPath('data.peringatan.label_tingkat', 'Penting');

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'peringatan-dini-siswa',
                'status' => 'tersedia',
                'rute' => '/peringatan-dini-siswa',
            ]);
    }

    public function test_peringatan_dapat_dihubungkan_ke_pendampingan_dan_deteksi_dapat_dijalankan_admin(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$tahun, , $siswa] = $this->buatSiswa('Siswa Tindak Lanjut', '0088221002');
        $peringatan = $this->buatPeringatan($tahun, $siswa, 'pelanggaran_berulang');
        $petugas = Pegawai::create([
            'nama_lengkap' => 'Guru BK Peringatan Mobile',
            'nip' => '198707072026087007',
            'aktif' => true,
        ]);
        $token = $this->token($administrator);

        $create = $this->withToken($token)
            ->postJson(route('api.v1.pendampingan-siswa.store'), [
                'siswa_id' => $siswa->id,
                'tahun_pelajaran_id' => $tahun->id,
                'peringatan_dini_siswa_id' => $peringatan->id,
                'jenis_tindakan' => 'konseling',
                'petugas_pegawai_id' => $petugas->id,
                'tanggal_tindak_lanjut' => '2026-09-01',
                'catatan' => 'Tindak lanjut dibuat langsung dari peringatan dini siswa.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.pendampingan.peringatan.id', $peringatan->id);

        $pendampinganId = $create->json('data.pendampingan.id');
        $this->assertDatabaseHas('pendampingan_siswa', [
            'id' => $pendampinganId,
            'peringatan_dini_siswa_id' => $peringatan->id,
        ]);

        $this->withToken($token)
            ->getJson(route('api.v1.peringatan-dini-siswa.show', $peringatan))
            ->assertOk()
            ->assertJsonPath('data.peringatan.pendampingan_aktif.id', $pendampinganId)
            ->assertJsonPath('data.peringatan.pendampingan_aktif.petugas.nama', $petugas->nama_lengkap);

        TransaksiPoinSiswa::create([
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'kunci_sumber' => 'api-peringatan-mobile:poin:1',
            'jenis' => 'pelanggaran',
            'poin' => 20,
            'keterangan' => 'Saldo pengujian deteksi mobile.',
            'tercatat_pada' => now(),
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);

        $this->withToken($token)
            ->postJson(route('api.v1.peringatan-dini-siswa.proses'), [
                'tahun_pelajaran_id' => $tahun->id,
            ])
            ->assertOk()
            ->assertJsonStructure(['message', 'data' => [
                'tahun_diproses',
                'siswa_diproses',
                'peringatan_baru',
                'peringatan_diperbarui',
                'peringatan_diselesaikan',
            ]]);

        $this->assertDatabaseHas('peringatan_dini_siswa', [
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'jenis' => 'mendekati_sanksi',
            'status' => 'aktif',
        ]);
    }

    public function test_guru_wali_hanya_melihat_siswa_tugasnya_dan_tidak_dapat_memproses_deteksi(): void
    {
        [$tahun, $kelas, $siswaDitugaskan] = $this->buatSiswa('Siswa Cakupan Wali', '0088221003');
        [, , $siswaLain] = $this->buatSiswa('Siswa Luar Cakupan', '0088221004', $tahun, $kelas);
        $dalam = $this->buatPeringatan($tahun, $siswaDitugaskan, 'sering_terlambat');
        $luar = $this->buatPeringatan($tahun, $siswaLain, 'sering_terlambat');
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Guru Wali Peringatan API',
            'nip' => '198808082026088008',
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
            ->getJson(route('api.v1.peringatan-dini-siswa.index', ['tahun_pelajaran_id' => $tahun->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $dalam->id)
            ->assertJsonPath('data.hak_akses.dapat_proses', false);

        $this->withToken($token)
            ->getJson(route('api.v1.peringatan-dini-siswa.show', $luar))
            ->assertForbidden();

        $this->withToken($token)
            ->postJson(route('api.v1.peringatan-dini-siswa.proses'), ['tahun_pelajaran_id' => $tahun->id])
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
            'nama' => 'VIII.PD',
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
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => $tahun->tanggal_mulai,
        ]);

        return [$tahun, $kelas, $siswa];
    }

    private function buatPeringatan(
        TahunPelajaran $tahun,
        Siswa $siswa,
        string $jenis,
        string $tingkat = 'peringatan',
    ): PeringatanDiniSiswa {
        return PeringatanDiniSiswa::create([
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'jenis' => $jenis,
            'tingkat' => $tingkat,
            'status' => 'aktif',
            'kunci_unik' => "uji-api:{$tahun->id}:{$siswa->id}:{$jenis}",
            'judul' => 'Peringatan dini untuk '.$siswa->nama_lengkap,
            'pesan' => 'Kondisi siswa memerlukan tindak lanjut terarah.',
            'data_pendukung' => $jenis === 'sering_terlambat' ? [
                'jumlah_keterlambatan' => 4,
                'total_menit' => 45,
                'periode_hari' => 30,
            ] : [
                'jumlah_pelanggaran' => 3,
                'total_poin_periode' => 15,
                'periode_hari' => 30,
            ],
            'siklus' => 1,
            'terdeteksi_pada' => now(),
            'terakhir_terdeteksi_pada' => now(),
        ]);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Perangkat Peringatan Dini', ['mobile'])->plainTextToken;
    }
}
