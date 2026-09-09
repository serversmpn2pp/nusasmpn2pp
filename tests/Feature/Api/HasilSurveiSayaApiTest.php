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
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HasilSurveiSayaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_hasil_survei_saya_hanya_memuat_penugasan_guru_yang_login(): void
    {
        $data = $this->dataDasar();

        $this->getJson(route('api.v1.hasil-survei-saya.index'))->assertUnauthorized();

        $this->withToken($this->token($data['akun']))
            ->getJson(route('api.v1.hasil-survei-saya.index', [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'semester' => 'ganjil',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $data['penugasan']->id)
            ->assertJsonPath('data.items.0.guru.nama', 'Guru Hasil Survei Mobile')
            ->assertJsonPath('data.items.0.mata_pelajaran.nama', 'Matematika Mobile')
            ->assertJsonPath('data.ringkasan.penugasan', 1)
            ->assertJsonMissing(['nama' => 'Guru Lain Mobile']);
    }

    public function test_guru_hanya_dapat_membuka_rincian_penugasannya_sendiri(): void
    {
        $data = $this->dataDasar();
        $token = $this->token($data['akun']);

        $this->withToken($token)
            ->getJson(route('api.v1.hasil-survei-saya.show', [
                'guruMataPelajaran' => $data['penugasan'],
                'semester' => 'ganjil',
            ]))
            ->assertOk()
            ->assertJsonPath('data.penugasan.id', $data['penugasan']->id)
            ->assertJsonPath('data.penugasan.jumlah_siswa', 1)
            ->assertJsonPath('data.penugasan.hasil_terbuka', false)
            ->assertJsonCount(0, 'data.rincian_pertanyaan')
            ->assertJsonCount(0, 'data.saran');

        $this->withToken($token)
            ->getJson(route('api.v1.hasil-survei-saya.show', [
                'guruMataPelajaran' => $data['penugasanLain'],
                'semester' => 'ganjil',
            ]))
            ->assertForbidden();
    }

    private function dataDasar(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2037/2038',
            'tanggal_mulai' => '2037-07-01',
            'tanggal_selesai' => '2038-06-30',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VIII.Hasil Survei',
            'tingkat' => 8,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $mapel = MataPelajaran::create([
            'kode' => 'MTK-HASIL-MOBILE',
            'nama' => 'Matematika Mobile',
            'kelompok' => 'Umum',
            'tingkat' => 8,
            'kkm' => 75,
            'aktif' => true,
        ]);
        $mapelLain = MataPelajaran::create([
            'kode' => 'IPA-HASIL-MOBILE',
            'nama' => 'IPA Mobile',
            'kelompok' => 'Umum',
            'tingkat' => 8,
            'kkm' => 75,
            'aktif' => true,
        ]);
        $guru = Pegawai::create([
            'nama_lengkap' => 'Guru Hasil Survei Mobile',
            'nip' => '198001012010017777',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $guruLain = Pegawai::create([
            'nama_lengkap' => 'Guru Lain Mobile',
            'nip' => '198001012010018888',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $penugasan = $this->buatPenugasan($tahun, $kelas, $mapel, $guru);
        $penugasanLain = $this->buatPenugasan($tahun, $kelas, $mapelLain, $guruLain);
        $siswa = Siswa::create([
            'nama_lengkap' => 'Siswa Hasil Survei Mobile',
            'nis' => '3700000001',
            'nisn' => '9700000001',
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => 1,
            'status_keanggotaan' => 'aktif',
        ]);
        $akun = Pengguna::create([
            'pegawai_id' => $guru->id,
            'nama' => $guru->nama_lengkap,
            'username' => $guru->nip,
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $akun->daftarPeran()->attach(Peran::where('kode', 'guru_mapel')->firstOrFail());

        return compact('tahun', 'penugasan', 'penugasanLain', 'akun');
    }

    private function buatPenugasan(
        TahunPelajaran $tahun,
        Kelas $kelas,
        MataPelajaran $mapel,
        Pegawai $guru,
    ): GuruMataPelajaran {
        return GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mapel->id,
            'pegawai_id' => $guru->id,
            'jenis_penugasan' => 'pengampu',
            'aktif' => true,
        ]);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
