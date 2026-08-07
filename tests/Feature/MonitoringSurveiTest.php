<?php

namespace Tests\Feature;

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

class MonitoringSurveiTest extends TestCase
{
    use RefreshDatabase;

    public function test_monitoring_hanya_dapat_diakses_wakil_kurikulum_dan_administrator(): void
    {
        $wakil = $this->buatAkunPeran('Wakil Kurikulum Uji', 'wakil-kurikulum-monitor', 'wakil_pimpinan_kurikulum');
        $guru = $this->buatAkunPeran('Guru Biasa Uji', 'guru-biasa-monitor', 'guru_mapel');

        $this->assertTrue(Peran::where('kode', 'wakil_pimpinan_kurikulum')->firstOrFail()->memilikiIzin('survei.monitor'));
        $this->actingAs($wakil)->get(route('monitoring-survei.index'))->assertOk();
        $this->actingAs($guru)->get(route('monitoring-survei.index'))->assertForbidden();
        $this->actingAs(Pengguna::where('username', 'administrator')->firstOrFail())
            ->get(route('monitoring-survei.index'))
            ->assertOk();
    }

    public function test_wakil_kurikulum_melihat_ringkasan_semua_penugasan_dan_rincian_anonim(): void
    {
        $data = $this->dataDasar();
        $siswa = $this->buatSiswa($data['tahun'], $data['kelas'], 6);

        $nilaiSurveiUtama = [5, 5, 4, 3, 4];

        foreach ($siswa->take(5) as $index => $item) {
            $this->buatSurvei(
                $data['penugasan_utama'],
                $item,
                $nilaiSurveiUtama[$index],
                $index === 0 ? 'Pembelajaran matematika mudah dipahami.' : null,
            );
        }
        foreach ($siswa->take(2) as $index => $item) {
            $this->buatSurvei(
                $data['penugasan_kedua'],
                $item,
                3,
                $index === 0 ? 'Saran ini belum boleh terbuka.' : null,
            );
        }

        $parameter = [
            'tahun_pelajaran_id' => $data['tahun']->id,
            'semester' => 'ganjil',
        ];
        $this->actingAs($data['wakil'])
            ->get(route('monitoring-survei.index', $parameter))
            ->assertOk()
            ->assertSee('Guru Matematika Monitoring')
            ->assertSee('Guru IPA Monitoring')
            ->assertSeeInOrder(['Penugasan terpantau', '2', 'Target respons', '12', 'Respons masuk', '7', 'Hasil terbuka', '1']);

        $responsUtama = $this->actingAs($data['wakil'])
            ->get(route('monitoring-survei.index', $parameter + [
                'guru_mata_pelajaran_id' => $data['penugasan_utama']->id,
            ]));
        $responsUtama
            ->assertOk()
            ->assertSee('Rincian pernyataan')
            ->assertSee('4,20')
            ->assertSee('Pembelajaran matematika mudah dipahami.');

        foreach ($siswa as $item) {
            $responsUtama->assertDontSee($item->nama_lengkap);
            $responsUtama->assertDontSee($item->nisn);
        }

        $this->actingAs($data['wakil'])
            ->get(route('monitoring-survei.index', $parameter + [
                'guru_mata_pelajaran_id' => $data['penugasan_kedua']->id,
            ]))
            ->assertOk()
            ->assertSee('Hasil rinci belum ditampilkan')
            ->assertSee('2/5')
            ->assertDontSee('Saran ini belum boleh terbuka.');
    }

    private function dataDasar(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2034/2035',
            'tanggal_mulai' => '2034-07-01',
            'tanggal_selesai' => '2035-06-30',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.M Monitoring',
            'tingkat' => 7,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $mapelUtama = MataPelajaran::create([
            'kode' => 'MTK-MONITOR',
            'nama' => 'Matematika Monitoring',
            'kelompok' => 'Umum',
            'tingkat' => 7,
            'kkm' => 75,
            'aktif' => true,
        ]);
        $mapelKedua = MataPelajaran::create([
            'kode' => 'IPA-MONITOR',
            'nama' => 'IPA Monitoring',
            'kelompok' => 'Umum',
            'tingkat' => 7,
            'kkm' => 75,
            'aktif' => true,
        ]);
        $wakil = $this->buatAkunPeran('Wakil Kurikulum Monitoring', 'wakil-monitoring-survei', 'wakil_pimpinan_kurikulum');
        $guruUtama = $this->buatPegawaiGuru('Guru Matematika Monitoring', '198001012010013333');
        $guruKedua = $this->buatPegawaiGuru('Guru IPA Monitoring', '198001012010014444');
        $penugasanUtama = $this->buatPenugasan($tahun, $kelas, $mapelUtama, $guruUtama);
        $penugasanKedua = $this->buatPenugasan($tahun, $kelas, $mapelKedua, $guruKedua);

        return [
            'tahun' => $tahun,
            'kelas' => $kelas,
            'wakil' => $wakil,
            'penugasan_utama' => $penugasanUtama,
            'penugasan_kedua' => $penugasanKedua,
        ];
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

    private function buatPegawaiGuru(string $nama, string $nip): Pegawai
    {
        return Pegawai::create([
            'nama_lengkap' => $nama,
            'nip' => $nip,
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
    }

    private function buatPenugasan(TahunPelajaran $tahun, Kelas $kelas, MataPelajaran $mapel, Pegawai $guru): GuruMataPelajaran
    {
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
                'nama_lengkap' => 'Siswa Monitoring Rahasia '.$nomor,
                'nis' => '34'.str_pad((string) $nomor, 8, '0', STR_PAD_LEFT),
                'nisn' => '97'.str_pad((string) $nomor, 8, '0', STR_PAD_LEFT),
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

    private function buatSurvei(GuruMataPelajaran $penugasan, Siswa $siswa, int $nilai, ?string $saran): void
    {
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
}
