<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PertanyaanSurveiPembelajaran;
use App\Models\Siswa;
use App\Models\SurveiPembelajaran;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MonitoringSurveiApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_monitoring_memerlukan_token_dan_izin(): void
    {
        $this->getJson(route('api.v1.monitoring-survei.index'))->assertUnauthorized();

        $guru = $this->buatAkunPeran('Guru Biasa API', 'guru-monitor-api', 'guru_mapel');
        $this->withToken($this->token($guru))
            ->getJson(route('api.v1.monitoring-survei.index'))
            ->assertForbidden();

        $this->app['auth']->forgetGuards();
        $wakil = $this->buatAkunPeran('Wakil Kurikulum API', 'wakil-monitor-api', 'wakil_pimpinan_kurikulum');
        $this->assertTrue($wakil->memilikiIzin('survei.monitor'));
        $this->withToken($this->token($wakil))
            ->getJson(route('api.v1.monitoring-survei.index'))
            ->assertOk();
    }

    public function test_monitoring_meringkas_setiap_penugasan_dan_dapat_difilter(): void
    {
        $data = $this->dataDasar();
        $siswa = $this->buatSiswa($data['tahun'], $data['kelas'], 6);
        foreach ($siswa->take(5) as $index => $item) {
            $this->buatSurvei($data['utama'], $item, [5, 5, 4, 3, 4][$index]);
        }
        foreach ($siswa->take(2) as $item) {
            $this->buatSurvei($data['kedua'], $item, 3);
        }

        $token = $this->token($data['wakil']);
        $this->withToken($token)
            ->getJson(route('api.v1.monitoring-survei.index', [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'semester' => 'ganjil',
            ]))
            ->assertOk()
            ->assertJsonCount(2, 'data.items')
            ->assertJsonPath('data.ringkasan.penugasan', 2)
            ->assertJsonPath('data.ringkasan.target_respons', 12)
            ->assertJsonPath('data.ringkasan.respons_masuk', 7)
            ->assertJsonPath('data.ringkasan.hasil_terbuka', 1)
            ->assertJsonPath('data.items.0.guru.nama', 'Guru IPA API')
            ->assertJsonPath('data.items.0.jumlah_pengisi', 2)
            ->assertJsonPath('data.items.1.guru.nama', 'Guru Matematika API')
            ->assertJsonPath('data.items.1.hasil_terbuka', true)
            ->assertJsonPath('data.items.1.rata_rata_keseluruhan', 4.2)
            ->assertJsonPath('data.minimal_responden', 5);

        $this->withToken($token)
            ->getJson(route('api.v1.monitoring-survei.index', [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'semester' => 'ganjil',
                'status' => 'lengkap',
                'cari' => 'matematika',
            ]))
            ->assertOk()
            ->assertJsonCount(0, 'data.items');

        $this->withToken($token)
            ->getJson(route('api.v1.monitoring-survei.index', [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'semester' => 'ganjil',
                'status' => 'berjalan',
                'cari' => 'matematika',
            ]))
            ->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.guru.nama', 'Guru Matematika API');
    }

    public function test_rincian_terbuka_setelah_lima_responden_dan_tetap_anonim(): void
    {
        $data = $this->dataDasar();
        $siswa = $this->buatSiswa($data['tahun'], $data['kelas'], 6);
        foreach ($siswa->take(5) as $index => $item) {
            $this->buatSurvei(
                $data['utama'],
                $item,
                [5, 5, 4, 3, 4][$index],
                $index === 0 ? 'Pertahankan penjelasan yang runtut.' : null,
            );
        }
        foreach ($siswa->take(2) as $index => $item) {
            $this->buatSurvei(
                $data['kedua'],
                $item,
                3,
                $index === 0 ? 'Saran rahasia belum boleh tampil.' : null,
            );
        }

        $token = $this->token($data['wakil']);
        $terbuka = $this->withToken($token)
            ->getJson(route('api.v1.monitoring-survei.show', [
                'guruMataPelajaran' => $data['utama'],
                'semester' => 'ganjil',
            ]))
            ->assertOk()
            ->assertJsonPath('data.penugasan.hasil_terbuka', true)
            ->assertJsonPath('data.penugasan.rata_rata_keseluruhan', 4.2)
            ->assertJsonCount(6, 'data.rincian_pertanyaan')
            ->assertJsonPath('data.rincian_pertanyaan.0.jumlah_jawaban', 5)
            ->assertJsonCount(5, 'data.rincian_pertanyaan.0.distribusi')
            ->assertJsonPath('data.saran.0.saran', 'Pertahankan penjelasan yang runtut.');

        foreach ($siswa as $item) {
            $terbuka->assertJsonMissing(['nama' => $item->nama_lengkap]);
            $terbuka->assertJsonMissing(['nisn' => $item->nisn]);
        }

        $this->withToken($token)
            ->getJson(route('api.v1.monitoring-survei.show', [
                'guruMataPelajaran' => $data['kedua'],
                'semester' => 'ganjil',
            ]))
            ->assertOk()
            ->assertJsonPath('data.penugasan.hasil_terbuka', false)
            ->assertJsonCount(0, 'data.rincian_pertanyaan')
            ->assertJsonCount(0, 'data.saran')
            ->assertJsonMissing(['saran' => 'Saran rahasia belum boleh tampil.']);
    }

    private function dataDasar(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2036/2037',
            'tanggal_mulai' => '2036-07-01',
            'tanggal_selesai' => '2037-06-30',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.API Monitoring',
            'tingkat' => 7,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $utama = $this->buatPenugasan(
            $tahun,
            $kelas,
            'MTK-MON-API',
            'Matematika API',
            'Guru Matematika API',
            '198001012010015555',
        );
        $kedua = $this->buatPenugasan(
            $tahun,
            $kelas,
            'IPA-MON-API',
            'IPA API',
            'Guru IPA API',
            '198001012010016666',
        );

        return [
            'tahun' => $tahun,
            'kelas' => $kelas,
            'utama' => $utama,
            'kedua' => $kedua,
            'wakil' => $this->buatAkunPeran(
                'Wakil Kurikulum Monitoring API',
                'wakil-monitoring-api',
                'wakil_pimpinan_kurikulum',
            ),
        ];
    }

    private function buatPenugasan(
        TahunPelajaran $tahun,
        Kelas $kelas,
        string $kode,
        string $namaMapel,
        string $namaGuru,
        string $nip,
    ): GuruMataPelajaran {
        $mapel = MataPelajaran::create([
            'kode' => $kode,
            'nama' => $namaMapel,
            'kelompok' => 'Umum',
            'tingkat' => 7,
            'kkm' => 75,
            'aktif' => true,
        ]);
        $guru = Pegawai::create([
            'nama_lengkap' => $namaGuru,
            'nip' => $nip,
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);

        return GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mapel->id,
            'pegawai_id' => $guru->id,
            'jenis_penugasan' => 'pengampu',
            'aktif' => true,
        ]);
    }

    private function buatSiswa(TahunPelajaran $tahun, Kelas $kelas, int $jumlah): Collection
    {
        return collect(range(1, $jumlah))->map(function (int $nomor) use ($tahun, $kelas) {
            $siswa = Siswa::create([
                'nama_lengkap' => 'Siswa API Rahasia '.$nomor,
                'nis' => '36'.str_pad((string) $nomor, 8, '0', STR_PAD_LEFT),
                'nisn' => '96'.str_pad((string) $nomor, 8, '0', STR_PAD_LEFT),
                'jenis_kelamin' => $nomor % 2 === 0 ? 'P' : 'L',
                'aktif' => true,
            ]);
            AnggotaKelas::create([
                'tahun_pelajaran_id' => $tahun->id,
                'kelas_id' => $kelas->id,
                'siswa_id' => $siswa->id,
                'nomor_absen' => $nomor,
                'status_keanggotaan' => 'aktif',
            ]);

            return $siswa;
        });
    }

    private function buatSurvei(
        GuruMataPelajaran $penugasan,
        Siswa $siswa,
        int $nilai,
        ?string $saran = null,
    ): void {
        $pertanyaan = PertanyaanSurveiPembelajaran::aktif()->terurut()->get();
        SurveiPembelajaran::create([
            'guru_mata_pelajaran_id' => $penugasan->id,
            'siswa_id' => $siswa->id,
            'semester' => 'ganjil',
            'versi_pertanyaan' => SurveiPembelajaran::VERSI_PERTANYAAN,
            'jawaban' => $pertanyaan->mapWithKeys(fn ($item) => [$item->kode => $nilai])->all(),
            'snapshot_pertanyaan' => $pertanyaan->mapWithKeys(fn ($item) => [
                $item->kode => ['pernyataan' => $item->pernyataan, 'urutan' => $item->urutan],
            ])->all(),
            'saran' => $saran,
            'diisi_pada' => now(),
        ]);
    }

    private function buatAkunPeran(string $nama, string $username, string $kodePeran): Pengguna
    {
        $pengguna = Pengguna::create([
            'nama' => $nama,
            'username' => $username,
            'kata_sandi' => Hash::make('rahasia'),
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $pengguna->daftarPeran()->sync([Peran::where('kode', $kodePeran)->value('id')]);

        return $pengguna;
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
