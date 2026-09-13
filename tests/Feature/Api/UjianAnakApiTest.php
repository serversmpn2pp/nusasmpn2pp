<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\JawabanPesertaUjianCbt;
use App\Models\JenisUjianCbt;
use App\Models\Kelas;
use App\Models\KelasUjianCbt;
use App\Models\MataPelajaran;
use App\Models\Pengguna;
use App\Models\PesertaUjianCbt;
use App\Models\Siswa;
use App\Models\SoalCbt;
use App\Models\TahunPelajaran;
use App\Models\UjianCbt;
use App\Services\AkunOrangTuaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class UjianAnakApiTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_orang_tua_hanya_memantau_ujian_anak_yang_terhubung(): void
    {
        $data = $this->fondasi();
        $token = $this->token($data['akun']);

        $this->getJson(route('api.v1.ujian-anak-saya.index'))->assertUnauthorized();

        $this->withToken($token)
            ->getJson(route('api.v1.ujian-anak-saya.index'))
            ->assertOk()
            ->assertJsonPath('data.mode', 'orang_tua')
            ->assertJsonPath('data.hanya_pemantauan', true)
            ->assertJsonPath('data.siswa.id', $data['anak_utama']->id)
            ->assertJsonCount(2, 'data.pilihan_siswa')
            ->assertJsonPath('data.ringkasan.selesai', 1)
            ->assertJsonPath('data.items.0.nama', 'Ujian Anak Utama')
            ->assertJsonPath('data.items.0.hasil.nilai', null)
            ->assertJsonMissingPath('data.items.0.token')
            ->assertJsonMissingPath('data.items.0.soal')
            ->assertJsonMissingPath('data.items.0.jawaban');

        $this->withToken($token)
            ->getJson(route('api.v1.ujian-anak-saya.index', [
                'siswa_id' => $data['anak_kedua']->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.siswa.id', $data['anak_kedua']->id)
            ->assertJsonPath('data.ringkasan.aktif', 1)
            ->assertJsonPath('data.items.0.nama', 'Ujian Anak Kedua')
            ->assertJsonMissing(['nama' => 'Ujian Anak Utama']);

        $this->withToken($token)
            ->getJson(route('api.v1.ujian-anak-saya.index', [
                'siswa_id' => $data['siswa_lain']->id,
            ]))
            ->assertForbidden();
    }

    public function test_nilai_hanya_tampil_setelah_hasil_ujian_dipublikasikan(): void
    {
        $data = $this->fondasi();
        $token = $this->token($data['akun']);

        $this->withToken($token)
            ->getJson(route('api.v1.ujian-anak-saya.index'))
            ->assertOk()
            ->assertJsonPath('data.items.0.hasil.ditampilkan', false)
            ->assertJsonPath('data.items.0.hasil.nilai', null);

        $data['ujian_utama']->update(['tampilkan_hasil' => true]);

        $this->withToken($token)
            ->getJson(route('api.v1.ujian-anak-saya.index'))
            ->assertOk()
            ->assertJsonPath('data.items.0.hasil.ditampilkan', true)
            ->assertJsonPath('data.items.0.hasil.nilai', 100)
            ->assertJsonPath('data.items.0.hasil.tuntas', true);
    }

    public function test_menu_ujian_anak_hanya_tersedia_untuk_identitas_orang_tua(): void
    {
        $data = $this->fondasi();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $responsOrangTua = $this->withToken($this->token($data['akun']))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'ujian-anak-saya',
                'label' => 'Ujian Anak Saya',
                'rute' => '/ujian-anak-saya',
            ]);
        $kodeOrangTua = collect($responsOrangTua->json('data.kelompok'))
            ->flatMap(fn (array $kelompok) => collect($kelompok['items'])->pluck('kode'));
        $this->assertNotContains('ujian-saya', $kodeOrangTua);
        $kelompokUjian = collect($responsOrangTua->json('data.kelompok'))
            ->firstWhere('kode', 'ujian-asesmen');
        $this->assertSame('Ujian Anak Saya', $kelompokUjian['label']);
        $this->assertSame(1, count($kelompokUjian['items']));

        $this->app['auth']->forgetGuards();
        $responsAdministrator = $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.menu'))
            ->assertOk();
        $kodeAdministrator = collect($responsAdministrator->json('data.kelompok'))
            ->flatMap(fn (array $kelompok) => collect($kelompok['items'])->pluck('kode'));
        $this->assertNotContains('ujian-anak-saya', $kodeAdministrator);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.ujian-anak-saya.index'))
            ->assertForbidden();
    }

    private function fondasi(): array
    {
        Carbon::setTestNow('2026-09-12 08:00:00');
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027 Ujian Anak',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelasA = $this->kelas($tahun, 'VIII.UA.A');
        $kelasB = $this->kelas($tahun, 'VIII.UA.B');
        $kelasC = $this->kelas($tahun, 'VIII.UA.C');
        [$anakUtama, $anggotaUtama] = $this->siswa($tahun, $kelasA, 'Alya Ujian Anak', '0011770001');
        [$anakKedua, $anggotaKedua] = $this->siswa($tahun, $kelasB, 'Bima Ujian Anak', '0011770002');
        [$siswaLain] = $this->siswa($tahun, $kelasC, 'Siswa Tidak Terhubung', '0011770003');
        $akun = app(AkunOrangTuaService::class)->buat($anakUtama);
        $akun->update(['wajib_ganti_kata_sandi' => false]);
        $akun->orangTuaWali->siswa()->attach($anakKedua->id, [
            'hubungan' => 'anak',
            'utama' => false,
        ]);
        $mapel = MataPelajaran::create([
            'kode' => 'IPA-UA',
            'nama' => 'IPA Ujian Anak',
            'tingkat' => 8,
            'kkm' => 75,
            'aktif' => true,
        ]);
        $jenis = JenisUjianCbt::create([
            'kode' => 'UA',
            'nama' => 'Ujian Anak',
            'memerlukan_token' => true,
            'dapat_diterapkan_ke_nilai' => true,
            'urutan' => 1,
            'aktif' => true,
        ]);
        [$ujianUtama, $kelasUjianUtama] = $this->ujian(
            $tahun,
            $kelasA,
            $mapel,
            $jenis,
            'Ujian Anak Utama',
            'UA-UTAMA',
            'selesai',
            false,
        );
        $soal = SoalCbt::create([
            'tahun_pelajaran_id' => $tahun->id,
            'mata_pelajaran_id' => $mapel->id,
            'tingkat' => 8,
            'kode' => 'SOAL-UA-001',
            'jenis_soal' => 'pilihan_ganda',
            'tingkat_kesulitan' => 'sedang',
            'kategori' => 'umum',
            'pertanyaan' => 'Soal aman untuk pengujian orang tua.',
            'opsi' => [
                ['kode' => 'A', 'teks' => 'Benar'],
                ['kode' => 'B', 'teks' => 'Salah'],
            ],
            'kunci_jawaban' => ['A'],
            'skor_maksimal' => 1,
            'status' => 'siap',
            'aktif' => true,
        ]);
        $soalUjian = $ujianUtama->soalUjianCbt()->create([
            'soal_cbt_id' => $soal->id,
            'nomor_urut' => 1,
            'bobot' => 1,
        ]);
        $pesertaUtama = PesertaUjianCbt::create([
            'ujian_cbt_id' => $ujianUtama->id,
            'kelas_ujian_cbt_id' => $kelasUjianUtama->id,
            'anggota_kelas_id' => $anggotaUtama->id,
            'nomor_peserta' => 'UA-001',
            'status' => 'selesai',
            'waktu_mulai' => now()->subHour(),
            'waktu_selesai' => now()->subMinutes(30),
            'menit_tersisa' => 0,
        ]);
        JawabanPesertaUjianCbt::create([
            'peserta_ujian_cbt_id' => $pesertaUtama->id,
            'soal_ujian_cbt_id' => $soalUjian->id,
            'soal_cbt_id' => $soal->id,
            'jawaban' => ['A'],
            'ragu' => false,
            'skor' => 1,
            'benar' => true,
            'waktu_dijawab' => now()->subMinutes(35),
        ]);
        [$ujianKedua, $kelasUjianKedua] = $this->ujian(
            $tahun,
            $kelasB,
            $mapel,
            $jenis,
            'Ujian Anak Kedua',
            'UA-KEDUA',
            'berlangsung',
            false,
        );
        PesertaUjianCbt::create([
            'ujian_cbt_id' => $ujianKedua->id,
            'kelas_ujian_cbt_id' => $kelasUjianKedua->id,
            'anggota_kelas_id' => $anggotaKedua->id,
            'nomor_peserta' => 'UA-002',
            'status' => 'aktif',
            'menit_tersisa' => 60,
        ]);

        return [
            'akun' => $akun,
            'anak_utama' => $anakUtama,
            'anak_kedua' => $anakKedua,
            'siswa_lain' => $siswaLain,
            'ujian_utama' => $ujianUtama,
        ];
    }

    private function kelas(TahunPelajaran $tahun, string $nama): Kelas
    {
        return Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => $nama,
            'tingkat' => 8,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
    }

    private function siswa(TahunPelajaran $tahun, Kelas $kelas, string $nama, string $nisn): array
    {
        $siswa = Siswa::create([
            'nama_lengkap' => $nama,
            'nis' => '26'.$nisn,
            'nisn' => $nisn,
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
        $anggota = AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => 1,
            'status_keanggotaan' => 'aktif',
        ]);

        return [$siswa, $anggota];
    }

    private function ujian(
        TahunPelajaran $tahun,
        Kelas $kelas,
        MataPelajaran $mapel,
        JenisUjianCbt $jenis,
        string $nama,
        string $kode,
        string $status,
        bool $tampilkanHasil,
    ): array {
        $ujian = UjianCbt::create([
            'alur' => 'kelas',
            'jenis_ujian_cbt_id' => $jenis->id,
            'tahun_pelajaran_id' => $tahun->id,
            'mata_pelajaran_id' => $mapel->id,
            'kode' => $kode,
            'nama' => $nama,
            'semester' => 'ganjil',
            'tingkat' => 8,
            'tanggal_mulai' => now()->subHours(2),
            'tanggal_selesai' => now()->addHours(2),
            'durasi_menit' => 60,
            'jumlah_soal' => 1,
            'kkm' => 75,
            'token' => 'RAHASIA',
            'acak_soal' => false,
            'acak_jawaban' => false,
            'batasi_satu_perangkat' => true,
            'tampilkan_hasil' => $tampilkanHasil,
            'status' => $status,
        ]);

        return [
            $ujian,
            KelasUjianCbt::create([
                'ujian_cbt_id' => $ujian->id,
                'kelas_id' => $kelas->id,
            ]),
        ];
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('NUSA Android Test', ['mobile'])->plainTextToken;
    }
}
