<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\Izin;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\PendampinganSiswa;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PendampinganSiswaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_memerlukan_token_mendukung_filter_dan_mengaktifkan_menu_native(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$tahun, $kelas, $siswa] = $this->buatSiswa('Siswa Pendampingan Mobile', '0088111001');
        $petugas = $this->buatPegawai('Petugas Pendampingan Mobile', '198101012026081001');
        $this->buatPendampingan($tahun, $siswa, $petugas, 'dalam_proses');
        $this->buatPendampingan($tahun, $siswa, $petugas, 'selesai');

        $this->getJson(route('api.v1.pendampingan-siswa.index'))->assertUnauthorized();

        $response = $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.pendampingan-siswa.index', [
                'tahun_pelajaran_id' => $tahun->id,
                'kelas_id' => $kelas->id,
                'status' => 'dalam_proses',
                'kata_kunci' => 'mobile',
            ]))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.total', 2)
            ->assertJsonPath('data.ringkasan.dalam_proses', 1)
            ->assertJsonPath('data.ringkasan.selesai', 1)
            ->assertJsonPath('data.filter.status', 'dalam_proses')
            ->assertJsonPath('data.filter.tahun_pelajaran_id', $tahun->id)
            ->assertJsonPath('data.filter.kelas_id', $kelas->id)
            ->assertJsonPath('data.hak_akses.dapat_kelola', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.siswa.nama', $siswa->nama_lengkap)
            ->assertJsonPath('data.items.0.kelas.nama', $kelas->nama)
            ->assertJsonPath('data.items.0.petugas.nama', $petugas->nama_lengkap)
            ->assertJsonPath('data.items.0.label_status', 'Dalam Proses');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'pendampingan-siswa',
                'status' => 'tersedia',
                'rute' => '/pendampingan-siswa',
            ]);
    }

    public function test_administrator_dapat_memilih_siswa_memulai_melanjutkan_dan_menyelesaikan_pendampingan(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$tahun, $kelas, $siswa] = $this->buatSiswa('Siswa Alur Pendampingan', '0088111002');
        $petugas = $this->buatPegawai('Guru BK Mobile', '198202022026082002');
        $token = $this->token($administrator);

        $this->withToken($token)
            ->getJson(route('api.v1.pendampingan-siswa.referensi', [
                'tahun_pelajaran_id' => $tahun->id,
                'kelas_id' => $kelas->id,
                'kata_kunci' => 'alur',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.siswa')
            ->assertJsonPath('data.siswa.0.id', $siswa->id)
            ->assertJsonPath('data.siswa.0.memiliki_pendampingan_aktif', false)
            ->assertJsonPath('data.pegawai.0.id', $petugas->id)
            ->assertJsonFragment(['kode' => 'mediasi', 'label' => 'Mediasi']);

        $create = $this->withToken($token)
            ->postJson(route('api.v1.pendampingan-siswa.store'), [
                'siswa_id' => $siswa->id,
                'tahun_pelajaran_id' => $tahun->id,
                'jenis_tindakan' => 'konseling',
                'petugas_pegawai_id' => $petugas->id,
                'tanggal_tindak_lanjut' => '2026-08-31',
                'catatan' => 'Siswa diajak menyusun langkah perbaikan yang terukur.',
            ])
            ->assertCreated()
            ->assertJsonPath('message', 'Pendampingan siswa berhasil dimulai.')
            ->assertJsonPath('data.pendampingan.status', 'dalam_proses')
            ->assertJsonPath('data.pendampingan.siswa.id', $siswa->id);

        $id = $create->json('data.pendampingan.id');
        $this->assertDatabaseHas('pendampingan_siswa', [
            'id' => $id,
            'status' => 'dalam_proses',
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);

        $this->withToken($token)
            ->getJson(route('api.v1.pendampingan-siswa.show', $id))
            ->assertOk()
            ->assertJsonPath('data.pendampingan.catatan', 'Siswa diajak menyusun langkah perbaikan yang terukur.')
            ->assertJsonPath('data.hak_akses.dapat_kelola', true)
            ->assertJsonPath('data.pilihan.pegawai.0.nama', $petugas->nama_lengkap);

        $this->withToken($token)
            ->putJson(route('api.v1.pendampingan-siswa.update', $id), [
                'jenis_tindakan' => 'konseling',
                'petugas_pegawai_id' => $petugas->id,
                'tanggal_tindak_lanjut' => '2026-08-31',
                'catatan' => 'Siswa diajak menyusun langkah perbaikan yang terukur.',
                'status' => 'selesai',
                'hasil' => 'Siswa menyepakati target dan jadwal evaluasi bersama guru BK.',
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Pendampingan siswa telah ditandai selesai.')
            ->assertJsonPath('data.pendampingan.status', 'selesai')
            ->assertJsonPath('data.pendampingan.hasil', 'Siswa menyepakati target dan jadwal evaluasi bersama guru BK.');

        $this->assertDatabaseHas('pendampingan_siswa', ['id' => $id, 'status' => 'selesai']);
        $this->assertNull(PendampinganSiswa::findOrFail($id)->kunci_aktif);
        $this->assertNotNull(PendampinganSiswa::findOrFail($id)->selesai_pada);
    }

    public function test_pendampingan_aktif_tidak_boleh_ganda_dan_hasil_wajib_saat_selesai(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$tahun, , $siswa] = $this->buatSiswa('Siswa Validasi Pendampingan', '0088111003');
        $petugas = $this->buatPegawai('Petugas Validasi', '198303032026083003');
        $pendampingan = $this->buatPendampingan($tahun, $siswa, $petugas, 'dalam_proses');
        $payload = [
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'jenis_tindakan' => 'mediasi',
            'petugas_pegawai_id' => $petugas->id,
            'tanggal_tindak_lanjut' => '2026-08-31',
            'catatan' => 'Catatan validasi pendampingan.',
        ];

        $this->withToken($this->token($administrator))
            ->postJson(route('api.v1.pendampingan-siswa.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('siswa_id');

        $this->withToken($this->token($administrator))
            ->putJson(route('api.v1.pendampingan-siswa.update', $pendampingan), [
                'jenis_tindakan' => 'konseling',
                'petugas_pegawai_id' => $petugas->id,
                'tanggal_tindak_lanjut' => '2026-08-31',
                'catatan' => 'Catatan validasi pendampingan.',
                'status' => 'selesai',
                'hasil' => '',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('hasil');

        $this->assertSame('dalam_proses', $pendampingan->fresh()->status);
    }

    public function test_guru_wali_hanya_melihat_dan_memilih_siswa_yang_ditugaskan(): void
    {
        [$tahun, $kelas, $siswaDitugaskan] = $this->buatSiswa('Siswa Dalam Cakupan', '0088111004');
        [, , $siswaLain] = $this->buatSiswa('Siswa Di Luar Cakupan', '0088111005', $tahun, $kelas);
        $pegawai = $this->buatPegawai('Guru Wali Mobile', '198404042026084004');
        $akun = $this->akunPegawai($pegawai, 'guru_wali');
        $adminId = Pengguna::where('username', 'administrator')->value('id');
        DB::table('penugasan_guru_wali_siswa')->insert([
            'siswa_id' => $siswaDitugaskan->id,
            'guru_wali_pegawai_id' => $pegawai->id,
            'tanggal_mulai' => today()->toDateString(),
            'aktif' => true,
            'dibuat_oleh_pengguna_id' => $adminId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $dalam = $this->buatPendampingan($tahun, $siswaDitugaskan, $pegawai, 'dalam_proses');
        $luar = $this->buatPendampingan($tahun, $siswaLain, $pegawai, 'dalam_proses');
        $token = $this->token($akun);

        $this->withToken($token)
            ->getJson(route('api.v1.pendampingan-siswa.index', ['tahun_pelajaran_id' => $tahun->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $dalam->id)
            ->assertJsonMissing(['id' => $luar->id]);

        $this->withToken($token)
            ->getJson(route('api.v1.pendampingan-siswa.referensi', ['tahun_pelajaran_id' => $tahun->id]))
            ->assertOk()
            ->assertJsonCount(1, 'data.siswa')
            ->assertJsonPath('data.siswa.0.id', $siswaDitugaskan->id);

        $this->withToken($token)
            ->getJson(route('api.v1.pendampingan-siswa.show', $luar))
            ->assertForbidden();
    }

    public function test_pengguna_pembaca_tidak_dapat_mengubah_pendampingan(): void
    {
        [$tahun, , $siswa] = $this->buatSiswa('Siswa Pembaca', '0088111006');
        $petugas = $this->buatPegawai('Pembaca Pendampingan', '198505052026085005');
        $akun = $this->akunDenganIzin($petugas, 'poin_siswa.lihat');

        $this->withToken($this->token($akun))
            ->postJson(route('api.v1.pendampingan-siswa.store'), [
                'siswa_id' => $siswa->id,
                'tahun_pelajaran_id' => $tahun->id,
                'jenis_tindakan' => 'konseling',
                'petugas_pegawai_id' => $petugas->id,
                'tanggal_tindak_lanjut' => '2026-08-31',
                'catatan' => 'Tidak boleh tersimpan.',
            ])
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
            'nama' => 'VIII.D',
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

    private function buatPegawai(string $nama, string $nip): Pegawai
    {
        return Pegawai::create(['nama_lengkap' => $nama, 'nip' => $nip, 'aktif' => true]);
    }

    private function buatPendampingan(
        TahunPelajaran $tahun,
        Siswa $siswa,
        Pegawai $petugas,
        string $status,
    ): PendampinganSiswa {
        return PendampinganSiswa::create([
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'petugas_pegawai_id' => $petugas->id,
            'jenis_tindakan' => 'konseling',
            'tanggal_tindak_lanjut' => today(),
            'catatan' => 'Catatan pendampingan '.$siswa->nama_lengkap,
            'status' => $status,
            'hasil' => $status === 'selesai' ? 'Pendampingan selesai dengan baik.' : null,
            'selesai_pada' => $status === 'selesai' ? now() : null,
            'kunci_aktif' => $status === 'dalam_proses' ? PendampinganSiswa::kunciAktif($siswa->id, $tahun->id) : null,
        ]);
    }

    private function akunPegawai(Pegawai $pegawai, string $kodePeran): Pengguna
    {
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
        $akun->daftarPeran()->attach(Peran::where('kode', $kodePeran)->firstOrFail());

        return $akun;
    }

    private function akunDenganIzin(Pegawai $pegawai, string $kodeIzin): Pengguna
    {
        $peran = Peran::create([
            'nama' => 'Pembaca Pendampingan Mobile',
            'kode' => 'pembaca_pendampingan_mobile',
            'aktif' => true,
            'sistem' => false,
        ]);
        $peran->izin()->attach(Izin::where('kode', $kodeIzin)->firstOrFail());
        $akun = $this->akunPegawai($pegawai, 'pegawai');
        $akun->daftarPeran()->attach($peran);

        return $akun;
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Perangkat Pendampingan', ['mobile'])->plainTextToken;
    }
}
