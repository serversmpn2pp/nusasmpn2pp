<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KelasApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_kelas_mobile_memerlukan_token_dan_izin(): void
    {
        $this->getJson(route('api.v1.kelas.index'))
            ->assertUnauthorized();

        $pengguna = Pengguna::create([
            'nama' => 'Pengguna Tanpa Izin Kelas',
            'username' => 'tanpa.izin.kelas',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.kelas.index'))
            ->assertForbidden();
    }

    public function test_administrator_dapat_mencari_memfilter_dan_memuat_paginasi_kelas(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'aktif' => true,
        ]);

        foreach (range(1, 7) as $nomor) {
            Kelas::create([
                'tahun_pelajaran_id' => $tahun->id,
                'nama' => sprintf('VIII.UJI.%02d', $nomor),
                'tingkat' => 8,
                'kapasitas' => 32,
                'aktif' => true,
            ]);
        }

        Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VIII.UJI.NONAKTIF',
            'tingkat' => 8,
            'aktif' => false,
        ]);
        Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'IX.DI.LUAR.PENCARIAN',
            'tingkat' => 9,
            'aktif' => true,
        ]);

        $response = $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.kelas.index', [
                'cari' => 'viii.uji',
                'status' => 'aktif',
                'tahun_pelajaran_id' => $tahun->id,
                'per_halaman' => 5,
            ]))
            ->assertOk()
            ->assertJsonCount(5, 'data.items')
            ->assertJsonPath('data.ringkasan.total', 9)
            ->assertJsonPath('data.ringkasan.aktif', 8)
            ->assertJsonPath('data.ringkasan.nonaktif', 1)
            ->assertJsonPath('data.filter.cari', 'viii.uji')
            ->assertJsonPath('data.filter.status', 'aktif')
            ->assertJsonPath('data.filter.tahun_pelajaran_id', $tahun->id)
            ->assertJsonPath('data.paginasi.halaman_terakhir', 2)
            ->assertJsonPath('data.paginasi.total', 7)
            ->assertJsonPath('data.paginasi.ada_halaman_berikutnya', true)
            ->assertJsonPath('data.tahun_pelajaran.0.nama', '2026/2027')
            ->assertJsonMissing(['nama' => 'VIII.UJI.NONAKTIF'])
            ->assertJsonStructure([
                'data' => [
                    'items' => [
                        '*' => [
                            'id',
                            'nama',
                            'tingkat',
                            'kapasitas',
                            'jumlah_siswa_aktif',
                            'kapasitas_tersedia',
                            'aktif',
                            'tahun_pelajaran',
                            'wali_kelas',
                        ],
                    ],
                    'ringkasan' => ['total', 'aktif', 'nonaktif'],
                    'tahun_pelajaran',
                    'filter',
                    'paginasi',
                ],
            ]);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_detail_kelas_memuat_wali_kapasitas_dan_anggota_siswa(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Wali Kelas VIII A',
            'nip' => '198001012010011088',
            'jenis_pegawai' => 'Guru',
            'jabatan_utama' => 'Wali Kelas',
            'aktif' => true,
        ]);
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'wali_kelas_id' => $pegawai->id,
            'nama' => 'VIII.A',
            'tingkat' => 8,
            'kapasitas' => 32,
            'aktif' => true,
            'keterangan' => 'Kelas unggulan',
        ]);
        $siswaAktif = Siswa::create([
            'nama_lengkap' => 'Alya Anggota Aktif',
            'nisn' => 'NISN-KELAS-AKTIF',
            'aktif' => true,
        ]);
        $siswaKeluar = Siswa::create([
            'nama_lengkap' => 'Bima Riwayat Kelas',
            'nisn' => 'NISN-KELAS-KELUAR',
            'aktif' => false,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswaAktif->id,
            'nomor_absen' => 1,
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => '2026-07-01',
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswaKeluar->id,
            'status_keanggotaan' => 'keluar',
            'tanggal_masuk' => '2026-07-01',
            'tanggal_keluar' => '2026-08-01',
        ]);

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.kelas.show', $kelas))
            ->assertOk()
            ->assertJsonPath('data.nama', 'VIII.A')
            ->assertJsonPath('data.wali_kelas.nama', 'Wali Kelas VIII A')
            ->assertJsonPath('data.kapasitas', 32)
            ->assertJsonPath('data.jumlah_siswa_aktif', 1)
            ->assertJsonPath('data.kapasitas_tersedia', 31)
            ->assertJsonPath('data.tahun_pelajaran.nama', '2026/2027')
            ->assertJsonCount(2, 'data.anggota_siswa')
            ->assertJsonPath('data.anggota_siswa.0.status_keanggotaan', 'aktif')
            ->assertJsonPath('data.anggota_siswa.0.siswa.nama', 'Alya Anggota Aktif')
            ->assertJsonPath('data.anggota_siswa.1.status_keanggotaan', 'keluar')
            ->assertJsonPath('data.hak_akses.dapat_kelola_anggota', true)
            ->assertJsonPath('data.hak_akses.dapat_melihat_jadwal', true)
            ->assertJsonPath('data.hak_akses.dapat_kelola_jadwal', true)
            ->assertJsonPath('data.keterangan', 'Kelas unggulan');
    }

    public function test_detail_kelas_memuat_jadwal_mingguan_beserta_slot_non_pelajaran(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'aktif' => true]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VIII.JADWAL',
            'tingkat' => 8,
            'aktif' => true,
        ]);
        $guru = Pegawai::create([
            'nama_lengkap' => 'Guru Matematika Mobile',
            'nip' => '198001012010011099',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $mataPelajaran = MataPelajaran::create([
            'kode' => 'MTK-MOBILE',
            'nama' => 'Matematika Mobile',
            'kelompok' => 'Wajib',
            'aktif' => true,
        ]);
        $penugasan = GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'pegawai_id' => $guru->id,
            'jenis_penugasan' => 'pengampu',
            'aktif' => true,
        ]);
        $jamPertama = JamPelajaran::create([
            'hari' => 'senin',
            'nomor_jam' => 1,
            'label' => 'Jam 1',
            'jam_mulai' => '07:30:00',
            'jam_selesai' => '08:15:00',
            'jenis' => 'pelajaran',
            'aktif' => true,
        ]);
        JamPelajaran::create([
            'hari' => 'senin',
            'nomor_jam' => 2,
            'label' => 'Istirahat',
            'jam_mulai' => '08:15:00',
            'jam_selesai' => '08:30:00',
            'jenis' => 'istirahat',
            'aktif' => true,
        ]);
        JadwalPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'hari' => 'senin',
            'jam_pelajaran_id' => $jamPertama->id,
            'guru_mata_pelajaran_id' => $penugasan->id,
            'aktif' => true,
        ]);

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.kelas.show', $kelas))
            ->assertOk()
            ->assertJsonPath('data.jadwal_kelas.jumlah_terisi', 1)
            ->assertJsonPath('data.jadwal_kelas.hari.0.kode', 'senin')
            ->assertJsonCount(2, 'data.jadwal_kelas.hari.0.slots')
            ->assertJsonPath('data.jadwal_kelas.hari.0.slots.0.jam_mulai', '07:30')
            ->assertJsonPath('data.jadwal_kelas.hari.0.slots.0.pilihan_jadwal', 'guru:'.$penugasan->id)
            ->assertJsonPath('data.jadwal_kelas.hari.0.slots.0.mata_pelajaran.nama', 'Matematika Mobile')
            ->assertJsonPath('data.jadwal_kelas.hari.0.slots.0.guru.nama', 'Guru Matematika Mobile')
            ->assertJsonPath('data.jadwal_kelas.hari.0.slots.1.jenis', 'istirahat')
            ->assertJsonPath('data.jadwal_kelas.hari.0.slots.1.terisi', false);
    }

    public function test_administrator_dapat_memilih_mengubah_dan_mengosongkan_slot_jadwal_kelas(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'aktif' => true]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VIII.JADWAL.MOBILE',
            'tingkat' => 8,
            'aktif' => true,
        ]);
        $kelasLain = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VIII.JADWAL.LAIN',
            'tingkat' => 8,
            'aktif' => true,
        ]);
        $guru = Pegawai::create([
            'nama_lengkap' => 'Guru Jadwal Mobile',
            'nip' => '198001012010011066',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $mataPelajaran = MataPelajaran::create([
            'kode' => 'IPA-MOBILE',
            'nama' => 'IPA Mobile',
            'kelompok' => 'Wajib',
            'aktif' => true,
        ]);
        $kegiatan = MataPelajaran::create([
            'kode' => 'PRAMUKA-MOBILE',
            'nama' => 'Pramuka Mobile',
            'kelompok' => 'Ekstrakurikuler',
            'tingkat' => 8,
            'aktif' => true,
        ]);
        $penugasan = GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'pegawai_id' => $guru->id,
            'jenis_penugasan' => 'pengampu',
            'aktif' => true,
        ]);
        $penugasanKelasLain = GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelasLain->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'pegawai_id' => $guru->id,
            'jenis_penugasan' => 'pengampu',
            'aktif' => true,
        ]);
        $jam = JamPelajaran::create([
            'hari' => 'senin',
            'nomor_jam' => 1,
            'label' => 'Jam 1',
            'jam_mulai' => '07:30:00',
            'jam_selesai' => '08:15:00',
            'jenis' => 'pelajaran',
            'aktif' => true,
        ]);
        $istirahat = JamPelajaran::create([
            'hari' => 'senin',
            'nomor_jam' => 2,
            'label' => 'Istirahat',
            'jam_mulai' => '08:15:00',
            'jam_selesai' => '08:30:00',
            'jenis' => 'istirahat',
            'aktif' => true,
        ]);
        $jadwalBentrok = JadwalPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelasLain->id,
            'hari' => 'senin',
            'jam_pelajaran_id' => $jam->id,
            'guru_mata_pelajaran_id' => $penugasanKelasLain->id,
            'aktif' => true,
        ]);
        $token = $this->token($administrator);

        $this->withToken($token)
            ->getJson(route('api.v1.kelas.jadwal.pilihan', $kelas))
            ->assertOk()
            ->assertJsonPath('data.jumlah', 2)
            ->assertJsonPath('data.items.0.nilai', 'guru:'.$penugasan->id)
            ->assertJsonPath('data.items.0.judul', 'IPA Mobile')
            ->assertJsonPath('data.items.0.subjudul', 'Guru Jadwal Mobile')
            ->assertJsonPath('data.items.1.nilai', 'kegiatan:'.$kegiatan->id)
            ->assertJsonPath('data.items.1.judul', 'Pramuka Mobile');

        $this->withToken($token)
            ->putJson(route('api.v1.kelas.jadwal.update', [$kelas, $jam]), [
                'pilihan_jadwal' => 'guru:'.$penugasan->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('pilihan_jadwal');

        $jadwalBentrok->update(['aktif' => false]);

        $this->withToken($token)
            ->putJson(route('api.v1.kelas.jadwal.update', [$kelas, $jam]), [
                'pilihan_jadwal' => 'guru:'.$penugasan->id,
                'keterangan' => 'Disimpan dari NUSA Mobile',
            ])
            ->assertOk()
            ->assertJsonPath('pesan', 'Slot jadwal berhasil diperbarui.');
        $this->assertDatabaseHas('jadwal_pelajaran', [
            'kelas_id' => $kelas->id,
            'jam_pelajaran_id' => $jam->id,
            'guru_mata_pelajaran_id' => $penugasan->id,
            'mata_pelajaran_id' => null,
            'keterangan' => 'Disimpan dari NUSA Mobile',
            'aktif' => true,
        ]);

        $this->withToken($token)
            ->putJson(route('api.v1.kelas.jadwal.update', [$kelas, $jam]), [
                'pilihan_jadwal' => 'kegiatan:'.$kegiatan->id,
            ])
            ->assertOk();
        $this->assertDatabaseHas('jadwal_pelajaran', [
            'kelas_id' => $kelas->id,
            'jam_pelajaran_id' => $jam->id,
            'guru_mata_pelajaran_id' => null,
            'mata_pelajaran_id' => $kegiatan->id,
            'aktif' => true,
        ]);

        $this->withToken($token)
            ->putJson(route('api.v1.kelas.jadwal.update', [$kelas, $jam]), [
                'pilihan_jadwal' => null,
            ])
            ->assertOk()
            ->assertJsonPath('pesan', 'Slot jadwal berhasil dikosongkan.');
        $this->assertDatabaseHas('jadwal_pelajaran', [
            'kelas_id' => $kelas->id,
            'jam_pelajaran_id' => $jam->id,
            'aktif' => false,
        ]);

        $this->withToken($token)
            ->putJson(route('api.v1.kelas.jadwal.update', [$kelas, $istirahat]), [
                'pilihan_jadwal' => 'guru:'.$penugasan->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('jam_pelajaran_id');
    }

    public function test_administrator_dapat_mencari_menambah_mengubah_dan_menghapus_anggota_kelas(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VIII.KELOLA',
            'kapasitas' => 2,
            'aktif' => true,
        ]);
        $kelasLain = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VIII.LAIN.KELOLA',
            'aktif' => true,
        ]);
        $siswaAwal = Siswa::create([
            'nama_lengkap' => 'Alya Anggota Awal',
            'nisn' => 'NISN-ANGGOTA-AWAL',
            'aktif' => true,
        ]);
        $siswaTersedia = Siswa::create([
            'nama_lengkap' => 'Bima Calon Anggota',
            'nis' => 'NIS-CALON-BIMA',
            'nisn' => 'NISN-CALON-BIMA',
            'aktif' => true,
        ]);
        $siswaKelasLain = Siswa::create([
            'nama_lengkap' => 'Citra Sudah Ditempatkan',
            'nisn' => 'NISN-SUDAH-DITEMPATKAN',
            'aktif' => true,
        ]);
        $siswaPenuh = Siswa::create([
            'nama_lengkap' => 'Dini Melebihi Kapasitas',
            'nisn' => 'NISN-MELEBIHI-KAPASITAS',
            'aktif' => true,
        ]);
        $anggotaAwal = AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswaAwal->id,
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => '2026-07-01',
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelasLain->id,
            'siswa_id' => $siswaKelasLain->id,
            'status_keanggotaan' => 'aktif',
        ]);

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.kelas.calon-anggota', [
                'kelas' => $kelas,
                'cari' => 'bima',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.nama', 'Bima Calon Anggota')
            ->assertJsonPath('data.kapasitas_tersedia', 1)
            ->assertJsonMissing(['nama' => 'Citra Sudah Ditempatkan']);

        $response = $this->withToken($this->token($administrator))
            ->postJson(route('api.v1.kelas.anggota.store', $kelas), [
                'siswa_id' => $siswaTersedia->id,
                'keterangan' => 'Ditambahkan dari NUSA Mobile',
            ])
            ->assertCreated()
            ->assertJsonPath('pesan', 'Siswa berhasil ditambahkan ke kelas.');
        $anggotaBaruId = $response->json('data.id');

        $this->assertDatabaseHas('anggota_kelas', [
            'id' => $anggotaBaruId,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswaTersedia->id,
            'nomor_absen' => 2,
            'keterangan' => 'Ditambahkan dari NUSA Mobile',
        ]);

        $this->withToken($this->token($administrator))
            ->postJson(route('api.v1.kelas.anggota.store', $kelas), [
                'siswa_id' => $siswaPenuh->id,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('siswa_id');

        $this->withToken($this->token($administrator))
            ->patchJson(route('api.v1.kelas.anggota.update', [
                'kelas' => $kelas,
                'anggotaKelas' => $anggotaBaruId,
            ]), [
                'tanggal_masuk' => '2026-07-02',
                'keterangan' => 'Catatan diperbarui',
            ])
            ->assertOk()
            ->assertJsonPath('pesan', 'Data anggota kelas berhasil diperbarui.');
        $this->assertDatabaseHas('anggota_kelas', [
            'id' => $anggotaBaruId,
            'tanggal_masuk' => '2026-07-02 00:00:00',
            'keterangan' => 'Catatan diperbarui',
        ]);

        $this->withToken($this->token($administrator))
            ->deleteJson(route('api.v1.kelas.anggota.destroy', [
                'kelas' => $kelas,
                'anggotaKelas' => $anggotaAwal,
            ]))
            ->assertOk()
            ->assertJsonPath('pesan', 'Siswa berhasil dikeluarkan dari kelas.');
        $this->assertDatabaseMissing('anggota_kelas', ['id' => $anggotaAwal->id]);
        $this->assertDatabaseHas('anggota_kelas', [
            'id' => $anggotaBaruId,
            'nomor_absen' => 1,
        ]);
    }

    public function test_wali_kelas_hanya_dapat_melihat_kelas_yang_diwalinya(): void
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Wali Kelas Mobile',
            'nip' => '198001012010011077',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $wali = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => 'wali.kelas.api.mobile',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $wali->daftarPeran()->attach(Peran::where('kode', 'wali_kelas')->firstOrFail());

        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'aktif' => true,
        ]);
        $kelasWali = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'wali_kelas_id' => $pegawai->id,
            'nama' => 'VII.WALI',
            'aktif' => true,
        ]);
        $kelasLain = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.LAIN',
            'aktif' => true,
        ]);

        $this->withToken($this->token($wali))
            ->getJson(route('api.v1.kelas.index'))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.total', 1)
            ->assertJsonCount(1, 'data.tahun_pelajaran')
            ->assertJsonFragment(['nama' => 'VII.WALI'])
            ->assertJsonMissing(['nama' => 'VII.LAIN']);

        $this->withToken($this->token($wali))
            ->getJson(route('api.v1.kelas.show', $kelasWali))
            ->assertOk()
            ->assertJsonPath('data.hak_akses.dapat_kelola_anggota', false)
            ->assertJsonPath('data.hak_akses.dapat_melihat_jadwal', true)
            ->assertJsonPath('data.hak_akses.dapat_kelola_jadwal', false);

        $this->withToken($this->token($wali))
            ->getJson(route('api.v1.kelas.calon-anggota', $kelasWali))
            ->assertForbidden();

        $this->withToken($this->token($wali))
            ->getJson(route('api.v1.kelas.jadwal.pilihan', $kelasWali))
            ->assertForbidden();

        $this->withToken($this->token($wali))
            ->getJson(route('api.v1.kelas.show', $kelasLain))
            ->assertForbidden();
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
