<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\SoalCbt;
use App\Models\TahunPelajaran;
use App\Models\UjianCbt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AsesmenKelasApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_asesmen_kelas_memerlukan_token_dan_izin(): void
    {
        $this->getJson(route('api.v1.asesmen-kelas.index'))->assertUnauthorized();
        $pengguna = Pengguna::create([
            'nama' => 'Tanpa Akses Asesmen', 'username' => 'tanpa.asesmen',
            'kata_sandi' => 'RahasiaNusa123', 'peran' => 'pegawai', 'aktif' => true,
            'akun_sistem' => false, 'wajib_ganti_kata_sandi' => false,
        ]);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.asesmen-kelas.index'))->assertForbidden();
    }

    public function test_guru_mengelola_asesmen_kelas_dan_soal_secara_native(): void
    {
        $data = $this->fondasi();
        $token = $this->token($data['guru']);
        $kelompok = implode('-', [$data['pegawai']->id, $data['mapel']->id, 8]);

        $this->withToken($token)->getJson(route('api.v1.asesmen-kelas.index'))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.total', 0)
            ->assertJsonPath('data.referensi.kelompok_pengajaran.0.key', $kelompok)
            ->assertJsonCount(1, 'data.referensi.kelompok_pengajaran.0.kelas');

        $response = $this->withToken($token)->postJson(
            route('api.v1.asesmen-kelas.store'),
            $this->payload($kelompok, $data['kelas']->id),
        )->assertCreated()
            ->assertJsonPath('data.nama', 'Sumatif Bab Persamaan')
            ->assertJsonPath('data.jumlah_peserta', 2)
            ->assertJsonPath('data.kelas_tujuan.0.nama', 'VIII.A')
            ->assertJsonPath('data.kelas_tujuan.0.komponen_nilai', 'Sumatif Bab Persamaan');

        $asesmen = UjianCbt::query()->where('nama', 'Sumatif Bab Persamaan')->firstOrFail();
        $this->assertSame('kelas', $asesmen->alur);
        $this->assertSame($data['guru']->id, $asesmen->dibuat_oleh_pengguna_id);
        $this->assertSame(2, $asesmen->pesertaUjianCbt()->count());
        $this->assertDatabaseHas('komponen_nilai', [
            'nama' => 'Sumatif Bab Persamaan', 'jenis_komponen' => 'sumatif',
        ]);

        $soal = $this->soal($data['tahun'], $data['mapel']);
        $this->withToken($token)->getJson(route('api.v1.asesmen-kelas.soal', $asesmen))
            ->assertOk()->assertJsonCount(1, 'data.soal')
            ->assertJsonPath('data.soal.0.dipilih', false);
        $this->withToken($token)->putJson(route('api.v1.asesmen-kelas.soal.update', $asesmen), [
            'soal' => [['id' => $soal->id, 'bobot' => 2.5]],
        ])->assertOk()
            ->assertJsonPath('data.soal.0.dipilih', true)
            ->assertJsonPath('data.soal.0.bobot', 2.5);

        $this->withToken($token)->patchJson(route('api.v1.asesmen-kelas.update', $asesmen), [
            ...$this->payload($kelompok, $data['kelas']->id),
            'nama' => 'Sumatif Persamaan Diperbarui',
            'status' => 'berlangsung',
        ])->assertOk()->assertJsonPath('data.status', 'berlangsung');

        $this->withToken($token)->deleteJson(route('api.v1.asesmen-kelas.destroy', $asesmen))->assertOk();
        $this->assertDatabaseHas('ujian_cbt', ['id' => $asesmen->id, 'status' => 'nonaktif']);
        $this->assertNotNull($response->json('data.id'));
    }

    public function test_guru_tidak_dapat_memakai_kelas_atau_mengelola_asesmen_guru_lain(): void
    {
        $data = $this->fondasi();
        $guruLain = $this->buatGuru('Guru Lain', '199002022026091002');
        GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $data['tahun']->id, 'kelas_id' => $data['kelas_lain']->id,
            'mata_pelajaran_id' => $data['mapel']->id, 'pegawai_id' => $guruLain['pegawai']->id,
            'jenis_penugasan' => 'pengampu', 'aktif' => true,
        ]);
        $kelompok = implode('-', [$data['pegawai']->id, $data['mapel']->id, 8]);

        $this->withToken($this->token($data['guru']))->postJson(
            route('api.v1.asesmen-kelas.store'),
            $this->payload($kelompok, $data['kelas_lain']->id),
        )->assertUnprocessable()->assertJsonValidationErrors('kelas_peserta');

        $this->app['auth']->forgetGuards();

        $this->withToken($this->token($guruLain['pengguna']))->postJson(
            route('api.v1.asesmen-kelas.store'),
            $this->payload(implode('-', [$guruLain['pegawai']->id, $data['mapel']->id, 8]), $data['kelas_lain']->id),
        )->assertCreated();
        $asesmenLain = UjianCbt::query()->where('dibuat_oleh_pengguna_id', $guruLain['pengguna']->id)->firstOrFail();

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($data['guru']))
            ->getJson(route('api.v1.asesmen-kelas.show', $asesmenLain))->assertForbidden();
    }

    private function fondasi(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30', 'aktif' => true,
        ]);
        $mapel = MataPelajaran::create([
            'kode' => 'MTK-AKM', 'nama' => 'Matematika', 'tingkat' => 8, 'kkm' => 78, 'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id, 'nama' => 'VIII.A',
            'tingkat' => 8, 'kapasitas' => 32, 'aktif' => true,
        ]);
        $kelasLain = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id, 'nama' => 'VIII.B',
            'tingkat' => 8, 'kapasitas' => 32, 'aktif' => true,
        ]);
        $guru = $this->buatGuru('Guru Matematika Native', '198801012026091001');
        GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mapel->id, 'pegawai_id' => $guru['pegawai']->id,
            'jenis_penugasan' => 'pengampu', 'aktif' => true,
        ]);
        for ($nomor = 1; $nomor <= 2; $nomor++) {
            $siswa = Siswa::create([
                'nama_lengkap' => "Siswa Asesmen {$nomor}", 'nis' => "AKM-00{$nomor}",
                'nisn' => "009900000{$nomor}", 'jenis_kelamin' => $nomor === 1 ? 'L' : 'P', 'aktif' => true,
            ]);
            AnggotaKelas::create([
                'tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'siswa_id' => $siswa->id,
                'nomor_absen' => $nomor, 'status_keanggotaan' => 'aktif', 'tanggal_masuk' => '2026-07-01',
            ]);
        }

        return [
            'tahun' => $tahun, 'mapel' => $mapel, 'kelas' => $kelas, 'kelas_lain' => $kelasLain,
            'pegawai' => $guru['pegawai'], 'guru' => $guru['pengguna'],
        ];
    }

    private function buatGuru(string $nama, string $nip): array
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => $nama, 'nip' => $nip, 'jenis_kelamin' => 'L',
            'jenis_pegawai' => 'Guru', 'aktif' => true,
        ]);
        $pengguna = Pengguna::create([
            'pegawai_id' => $pegawai->id, 'nama' => $nama, 'username' => $nip,
            'kata_sandi' => 'RahasiaNusa123', 'peran' => 'pegawai', 'aktif' => true,
            'akun_sistem' => false, 'wajib_ganti_kata_sandi' => false,
        ]);
        $pengguna->daftarPeran()->attach(Peran::where('kode', 'guru_mapel')->firstOrFail());

        return compact('pegawai', 'pengguna');
    }

    private function payload(string $kelompok, int $kelasId): array
    {
        return [
            'kelompok_pengajaran' => $kelompok, 'nama' => 'Sumatif Bab Persamaan', 'semester' => 'ganjil',
            'tanggal_mulai' => '2026-09-10 08:00:00', 'tanggal_selesai' => '2026-09-10 09:00:00',
            'durasi_menit' => 40, 'jumlah_soal' => 10, 'status' => 'terjadwal',
            'acak_soal' => true, 'acak_jawaban' => true, 'batasi_satu_perangkat' => false,
            'deteksi_pindah_tab' => true, 'tampilkan_hasil' => false, 'petunjuk' => 'Kerjakan dengan teliti.',
            'kelas_peserta' => [['kelas_id' => $kelasId, 'komponen_nilai_id' => 'baru']],
        ];
    }

    private function soal(TahunPelajaran $tahun, MataPelajaran $mapel): SoalCbt
    {
        return SoalCbt::create([
            'tahun_pelajaran_id' => $tahun->id, 'mata_pelajaran_id' => $mapel->id,
            'tingkat' => 8, 'kode' => 'SOAL-AKM-001', 'jenis_soal' => 'pilihan_ganda',
            'tingkat_kesulitan' => 'sedang', 'kategori' => 'umum', 'pertanyaan' => 'Nilai x adalah?',
            'opsi' => ['pilihan' => ['A' => '1', 'B' => '2']], 'kunci_jawaban' => ['jawaban' => 'B'],
            'skor_maksimal' => 2.5, 'status' => 'siap', 'aktif' => true,
        ]);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
