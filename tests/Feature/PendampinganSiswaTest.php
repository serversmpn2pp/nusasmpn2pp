<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\PendampinganSiswa;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PeringatanDiniSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PendampinganSiswaTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_membuat_melanjutkan_dan_menyelesaikan_tindak_lanjut_ringkas(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$tahun, , $siswa] = $this->buatSiswaDalamKelas('Siswa Pendampingan Utama', '0088110001');
        $petugas = Pegawai::create([
            'nama_lengkap' => 'Guru BK Pendamping',
            'nip' => '198101012010011001',
            'aktif' => true,
        ]);
        $peringatan = $this->buatPeringatan($tahun, $siswa, 'sering_terlambat');

        $this->assertDatabaseHas('izin', [
            'kode' => 'poin_siswa.pendampingan_kelola',
            'aktif' => true,
        ]);
        $this->assertTrue(Peran::where('kode', 'bk')->firstOrFail()->memilikiIzin('poin_siswa.pendampingan_kelola'));
        $this->assertTrue(Peran::where('kode', 'wali_kelas')->firstOrFail()->memilikiIzin('poin_siswa.pendampingan_kelola'));
        $this->assertTrue(Peran::where('kode', 'guru_wali')->firstOrFail()->memilikiIzin('poin_siswa.pendampingan_kelola'));

        $this->actingAs($administrator)
            ->get(route('pendampingan-siswa.create', ['peringatan_id' => $peringatan->id]))
            ->assertOk()
            ->assertSee('Mulai tindak lanjut')
            ->assertSee($siswa->nama_lengkap)
            ->assertSee('Pembinaan Wali Kelas/Guru Wali');

        $response = $this->post(route('pendampingan-siswa.store'), [
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'peringatan_dini_siswa_id' => $peringatan->id,
            'jenis_tindakan' => 'pembinaan_wali',
            'petugas_pegawai_id' => $petugas->id,
            'tanggal_tindak_lanjut' => '2026-07-23',
            'catatan' => 'Siswa diajak berdiskusi mengenai kebiasaan datang terlambat.',
        ]);

        $pendampingan = PendampinganSiswa::firstOrFail();
        $response->assertRedirect(route('pendampingan-siswa.edit', $pendampingan));
        $this->assertSame('dalam_proses', $pendampingan->status);
        $this->assertNotNull($pendampingan->kunci_aktif);

        $this->get(route('pendampingan-siswa.create', ['peringatan_id' => $peringatan->id]))
            ->assertRedirect(route('pendampingan-siswa.edit', $pendampingan));
        $this->assertSame(1, PendampinganSiswa::count());

        $this->put(route('pendampingan-siswa.update', $pendampingan), [
            'jenis_tindakan' => 'pembinaan_wali',
            'petugas_pegawai_id' => $petugas->id,
            'tanggal_tindak_lanjut' => '2026-07-23',
            'catatan' => 'Siswa diajak berdiskusi mengenai kebiasaan datang terlambat.',
            'status' => 'selesai',
            'hasil' => 'Siswa dan orang tua menyepakati waktu keberangkatan yang lebih awal.',
        ])->assertRedirect(route('pendampingan-siswa.edit', $pendampingan));

        $pendampingan->refresh();
        $this->assertSame('selesai', $pendampingan->status);
        $this->assertNull($pendampingan->kunci_aktif);
        $this->assertNotNull($pendampingan->selesai_pada);

        $this->get(route('pendampingan-siswa.index', [
            'tahun_pelajaran_id' => $tahun->id,
            'status' => 'selesai',
        ]))
            ->assertOk()
            ->assertSee($siswa->nama_lengkap)
            ->assertSee('Sudah selesai');

        $this->get(route('rekap-poin-siswa.show', [
            'siswa' => $siswa,
            'tahun_pelajaran_id' => $tahun->id,
        ]))
            ->assertOk()
            ->assertSee('Riwayat tindak lanjut')
            ->assertSee('Siswa dan orang tua menyepakati waktu keberangkatan yang lebih awal.');
    }

    public function test_tindak_lanjut_tidak_bisa_diselesaikan_tanpa_hasil(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        [$tahun, , $siswa] = $this->buatSiswaDalamKelas('Siswa Hasil Wajib', '0088110002');
        $petugas = Pegawai::create([
            'nama_lengkap' => 'Petugas Hasil Wajib',
            'nip' => '198202022011021002',
            'aktif' => true,
        ]);
        $pendampingan = PendampinganSiswa::create([
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'petugas_pegawai_id' => $petugas->id,
            'jenis_tindakan' => 'konseling',
            'tanggal_tindak_lanjut' => today(),
            'catatan' => 'Catatan awal pendampingan.',
            'status' => 'dalam_proses',
            'kunci_aktif' => PendampinganSiswa::kunciAktif($siswa->id, $tahun->id),
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);

        $this->actingAs($administrator)
            ->from(route('pendampingan-siswa.edit', $pendampingan))
            ->put(route('pendampingan-siswa.update', $pendampingan), [
                'jenis_tindakan' => 'konseling',
                'petugas_pegawai_id' => $petugas->id,
                'tanggal_tindak_lanjut' => today()->toDateString(),
                'catatan' => 'Catatan awal pendampingan.',
                'status' => 'selesai',
                'hasil' => '',
            ])
            ->assertRedirect(route('pendampingan-siswa.edit', $pendampingan))
            ->assertSessionHasErrors('hasil');

        $this->assertSame('dalam_proses', $pendampingan->fresh()->status);
    }

    public function test_guru_wali_hanya_mengelola_tindak_lanjut_siswa_yang_ditugaskan(): void
    {
        [$tahun, $kelas, $siswaDitugaskan] = $this->buatSiswaDalamKelas('Siswa Wali Didampingi', '0088110003');
        [, , $siswaLain] = $this->buatSiswaDalamKelas('Siswa Di Luar Pendampingan', '0088110004', $tahun, $kelas);
        $guruWali = Pegawai::create([
            'nama_lengkap' => 'Guru Wali Pendampingan',
            'nip' => '198303032012031003',
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
            'tanggal_mulai' => today()->toDateString(),
            'aktif' => true,
            'dibuat_oleh_pengguna_id' => Pengguna::where('username', 'administrator')->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        foreach ([$siswaDitugaskan, $siswaLain] as $siswa) {
            PendampinganSiswa::create([
                'siswa_id' => $siswa->id,
                'tahun_pelajaran_id' => $tahun->id,
                'petugas_pegawai_id' => $guruWali->id,
                'jenis_tindakan' => 'pembinaan_wali',
                'tanggal_tindak_lanjut' => today(),
                'catatan' => 'Catatan pendampingan untuk '.$siswa->nama_lengkap,
                'status' => 'dalam_proses',
                'kunci_aktif' => PendampinganSiswa::kunciAktif($siswa->id, $tahun->id),
            ]);
        }

        $this->actingAs($akun)
            ->get(route('pendampingan-siswa.index', ['tahun_pelajaran_id' => $tahun->id]))
            ->assertOk()
            ->assertSee($siswaDitugaskan->nama_lengkap)
            ->assertDontSee($siswaLain->nama_lengkap);

        $this->get(route('pendampingan-siswa.create', [
            'siswa_id' => $siswaDitugaskan->id,
            'tahun_pelajaran_id' => $tahun->id,
        ]))->assertRedirect();

        $this->get(route('pendampingan-siswa.create', [
            'siswa_id' => $siswaLain->id,
            'tahun_pelajaran_id' => $tahun->id,
        ]))->assertForbidden();
    }

    private function buatPeringatan(TahunPelajaran $tahun, Siswa $siswa, string $jenis): PeringatanDiniSiswa
    {
        return PeringatanDiniSiswa::create([
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'jenis' => $jenis,
            'tingkat' => 'peringatan',
            'status' => 'aktif',
            'kunci_unik' => 'pendampingan-uji:'.$siswa->id.':'.$jenis,
            'judul' => 'Peringatan untuk tindak lanjut',
            'pesan' => 'Siswa memerlukan tindak lanjut sederhana.',
            'siklus' => 1,
            'terdeteksi_pada' => now(),
            'terakhir_terdeteksi_pada' => now(),
        ]);
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
            'nama' => 'VIII.C',
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
