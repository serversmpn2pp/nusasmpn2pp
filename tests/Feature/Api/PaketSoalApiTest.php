<?php

namespace Tests\Feature\Api;

use App\Models\GuruMataPelajaran;
use App\Models\JadwalUjianCbt;
use App\Models\JenisUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\SesiKegiatanUjianCbt;
use App\Models\SoalCbt;
use App\Models\TahunPelajaran;
use App\Models\UjianCbt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaketSoalApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_paket_soal_memerlukan_token_dan_izin(): void
    {
        $this->getJson(route('api.v1.paket-soal.index'))->assertUnauthorized();
        $pengguna = Pengguna::create([
            'nama' => 'Tanpa Paket CBT', 'username' => 'tanpa.paket.cbt',
            'kata_sandi' => 'RahasiaNusa123', 'peran' => 'pegawai', 'aktif' => true,
            'akun_sistem' => false, 'wajib_ganti_kata_sandi' => false,
        ]);

        $this->withToken($this->token($pengguna))->getJson(route('api.v1.paket-soal.index'))->assertForbidden();
    }

    public function test_admin_dapat_menyusun_mengurutkan_dan_menerbitkan_paket_native(): void
    {
        $data = $this->fondasi();
        $satu = $this->soal($data['tahun'], $data['mapel'], 'SOAL-MOB-1', 1);
        $dua = $this->soal($data['tahun'], $data['mapel'], 'SOAL-MOB-2', 2);
        $token = $this->token($data['admin']);

        $this->withToken($token)->getJson(route('api.v1.paket-soal.index'))
            ->assertOk()->assertJsonPath('data.ringkasan.belum_disusun', 1)
            ->assertJsonPath('data.items.0.dapat_kelola', true);
        $this->withToken($token)->getJson(route('api.v1.paket-soal.show', $data['jadwal']))
            ->assertOk()->assertJsonCount(2, 'data.soal')
            ->assertJsonPath('data.hak_akses.dapat_ubah', true);

        $this->withToken($token)->postJson(route('api.v1.paket-soal.update', $data['jadwal']), [
            'aksi' => 'terbitkan', 'acak_soal' => false, 'acak_jawaban' => true,
            'soal' => [
                ['id' => $dua->id, 'bobot' => 2.5],
                ['id' => $satu->id, 'bobot' => 1],
            ],
        ])->assertOk()->assertJsonPath('data.paket.status', 'terjadwal')
            ->assertJsonPath('data.jadwal.jumlah_soal', 2)
            ->assertJsonPath('data.jadwal.total_bobot', 3.5);

        $paket = UjianCbt::where('alur', 'terpusat')->firstOrFail();
        $this->assertFalse($paket->acak_soal);
        $this->assertTrue($paket->acak_jawaban);
        $this->assertTrue($paket->batasi_satu_perangkat);
        $this->assertTrue($paket->deteksi_pindah_tab);
        $this->assertTrue($paket->wajib_fullscreen);
        $this->assertTrue($paket->blokir_tangkapan_layar);
        $this->assertSame('tahan', $paket->tindakan_pindah_aplikasi);
        $this->assertSame([$dua->id, $satu->id], $paket->soalUjianCbt()->orderBy('nomor_urut')->pluck('soal_cbt_id')->all());
        $this->assertDatabaseHas('jadwal_ujian_cbt', ['id' => $data['jadwal']->id, 'status' => 'siap']);
    }

    public function test_guru_hanya_melihat_paket_dalam_cakupan_penugasannya(): void
    {
        $data = $this->fondasi();
        $token = $this->token($data['guru']);

        $this->withToken($token)->getJson(route('api.v1.paket-soal.index'))
            ->assertOk()->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.mata_pelajaran', 'Matematika');
        $this->withToken($token)->postJson(route('api.v1.paket-soal.update', $data['jadwal']), [
            'aksi' => 'draf', 'acak_soal' => true, 'acak_jawaban' => true, 'soal' => [],
        ])->assertOk()->assertJsonPath('data.paket.status', 'draft');
    }

    private function fondasi(): array
    {
        $admin = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30', 'aktif' => true,
        ]);
        $jenis = JenisUjianCbt::query()->firstOrFail();
        $kegiatan = KegiatanUjianCbt::create([
            'jenis_ujian_cbt_id' => $jenis->id, 'tahun_pelajaran_id' => $tahun->id,
            'kode' => 'PAKET-API-1', 'nama' => 'STS Native', 'semester' => 'ganjil',
            'tanggal_mulai' => '2026-09-15', 'tanggal_selesai' => '2026-09-20',
            'status' => 'aktif', 'dibuat_oleh_pengguna_id' => $admin->id,
        ]);
        $sesi = SesiKegiatanUjianCbt::create([
            'kegiatan_ujian_cbt_id' => $kegiatan->id, 'kode' => 'S01', 'nama' => 'Sesi Pagi',
            'waktu_mulai' => '07:30', 'waktu_selesai' => '09:30', 'urutan' => 1, 'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id, 'nama' => 'VIII.A',
            'tingkat' => 8, 'kapasitas' => 32, 'aktif' => true,
        ]);
        $mapel = MataPelajaran::create([
            'kode' => 'MTK-PAKET-MOB', 'nama' => 'Matematika', 'tingkat' => 8, 'kkm' => 78, 'aktif' => true,
        ]);
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Guru Paket Mobile', 'nip' => '198801012026091001',
            'jenis_kelamin' => 'L', 'jenis_pegawai' => 'Guru', 'aktif' => true,
        ]);
        $guru = Pengguna::create([
            'pegawai_id' => $pegawai->id, 'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip, 'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai', 'aktif' => true, 'akun_sistem' => false, 'wajib_ganti_kata_sandi' => false,
        ]);
        $guru->daftarPeran()->attach(Peran::where('kode', 'guru_mapel')->firstOrFail());
        GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mapel->id, 'pegawai_id' => $pegawai->id,
            'jenis_penugasan' => 'pengampu', 'aktif' => true,
        ]);
        $jadwal = JadwalUjianCbt::create([
            'kegiatan_ujian_cbt_id' => $kegiatan->id, 'sesi_kegiatan_ujian_cbt_id' => $sesi->id,
            'mata_pelajaran_id' => $mapel->id, 'tanggal' => '2026-09-15',
            'waktu_mulai' => '07:30', 'waktu_selesai' => '09:30', 'label_sesi' => 'Sesi Pagi',
            'tingkat' => 8, 'urutan' => 1, 'status' => 'draft',
        ]);
        $jadwal->kelas()->sync([$kelas->id]);

        return compact('admin', 'tahun', 'mapel', 'guru', 'jadwal');
    }

    private function soal(TahunPelajaran $tahun, MataPelajaran $mapel, string $kode, float $skor): SoalCbt
    {
        return SoalCbt::create([
            'tahun_pelajaran_id' => $tahun->id, 'mata_pelajaran_id' => $mapel->id,
            'tingkat' => 8, 'kode' => $kode, 'jenis_soal' => 'pilihan_ganda',
            'tingkat_kesulitan' => 'sedang', 'kategori' => 'umum', 'pertanyaan' => "Pertanyaan {$kode}",
            'opsi' => ['pilihan' => ['A' => 'Salah', 'B' => 'Benar']],
            'kunci_jawaban' => ['jawaban' => 'B'], 'skor_maksimal' => $skor, 'status' => 'siap', 'aktif' => true,
        ]);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
