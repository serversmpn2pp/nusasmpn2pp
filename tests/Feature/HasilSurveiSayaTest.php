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

class HasilSurveiSayaTest extends TestCase
{
    use RefreshDatabase;

    public function test_guru_hanya_dapat_memilih_penugasan_miliknya_sendiri(): void
    {
        $data = $this->dataDasar();
        $guruLain = $this->buatPegawaiDanAkun('Guru Lain Survei', '198001012010012222', 'guru-lain-survei');
        $mapelLain = MataPelajaran::create([
            'kode' => 'BIND-UJI',
            'nama' => 'Bahasa Rahasia Guru Lain',
            'kelompok' => 'Umum',
            'tingkat' => 7,
            'kkm' => 75,
            'aktif' => true,
        ]);
        $penugasanLain = $this->buatPenugasan($data['tahun'], $data['kelas'], $mapelLain, $guruLain['pegawai']);

        $this->actingAs($data['akun'])
            ->get(route('hasil-survei-saya.index', [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'guru_mata_pelajaran_id' => $penugasanLain->id,
                'semester' => 'ganjil',
            ]))
            ->assertOk()
            ->assertSee('Matematika Survei')
            ->assertDontSee('Bahasa Rahasia Guru Lain');
    }

    public function test_hasil_rinci_dan_saran_terkunci_jika_belum_lima_siswa_mengisi(): void
    {
        $data = $this->dataDasar();
        $siswa = $this->buatSiswa($data['tahun'], $data['kelas'], 5);

        foreach ($siswa->take(4) as $index => $item) {
            $this->buatSurvei($data['penugasan'], $item, 4, $index === 0 ? 'Saran rahasia harus terkunci.' : null);
        }

        $this->actingAs($data['akun'])
            ->get(route('hasil-survei-saya.index', [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'guru_mata_pelajaran_id' => $data['penugasan']->id,
                'semester' => 'ganjil',
            ]))
            ->assertOk()
            ->assertSee('Hasil rinci belum ditampilkan')
            ->assertSee('4/5')
            ->assertDontSee('Saran rahasia harus terkunci.');
    }

    public function test_lima_responden_membuka_ringkasan_anonim_tanpa_identitas_siswa(): void
    {
        $data = $this->dataDasar();
        $siswa = $this->buatSiswa($data['tahun'], $data['kelas'], 6);

        foreach ($siswa->take(5) as $index => $item) {
            $this->buatSurvei(
                $data['penugasan'],
                $item,
                4,
                $index === 0 ? 'Penjelasan menggunakan contoh sudah membantu.' : null,
            );
        }

        $respons = $this->actingAs($data['akun'])
            ->get(route('hasil-survei-saya.index', [
                'tahun_pelajaran_id' => $data['tahun']->id,
                'guru_mata_pelajaran_id' => $data['penugasan']->id,
                'semester' => 'ganjil',
            ]));

        $respons
            ->assertOk()
            ->assertSee('Rincian pernyataan')
            ->assertSee('4,00')
            ->assertSee('Penjelasan menggunakan contoh sudah membantu.')
            ->assertSee('Tanpa nama dan NISN siswa');

        foreach ($siswa as $item) {
            $respons->assertDontSee($item->nama_lengkap);
            $respons->assertDontSee($item->nisn);
        }
    }

    private function dataDasar(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2032/2033',
            'tanggal_mulai' => '2032-07-01',
            'tanggal_selesai' => '2033-06-30',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.A Survei',
            'tingkat' => 7,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $mapel = MataPelajaran::create([
            'kode' => 'MTK-SURVEI',
            'nama' => 'Matematika Survei',
            'kelompok' => 'Umum',
            'tingkat' => 7,
            'kkm' => 75,
            'aktif' => true,
        ]);
        $guru = $this->buatPegawaiDanAkun('Guru Hasil Survei', '198001012010011111', 'guru-hasil-survei');
        $penugasan = $this->buatPenugasan($tahun, $kelas, $mapel, $guru['pegawai']);

        return [
            'tahun' => $tahun,
            'kelas' => $kelas,
            'mapel' => $mapel,
            'pegawai' => $guru['pegawai'],
            'akun' => $guru['akun'],
            'penugasan' => $penugasan,
        ];
    }

    private function buatPegawaiDanAkun(string $nama, string $nip, string $username): array
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => $nama,
            'nip' => $nip,
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $akun = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $nama,
            'username' => $username,
            'kata_sandi' => Hash::make('rahasia'),
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $akun->daftarPeran()->sync([Peran::where('kode', 'guru_mapel')->value('id')]);

        return compact('pegawai', 'akun');
    }

    private function buatPenugasan(
        TahunPelajaran $tahun,
        Kelas $kelas,
        MataPelajaran $mapel,
        Pegawai $pegawai,
    ): GuruMataPelajaran {
        return GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mapel->id,
            'pegawai_id' => $pegawai->id,
            'jenis_penugasan' => 'pengampu',
            'aktif' => true,
        ]);
    }

    private function buatSiswa(TahunPelajaran $tahun, Kelas $kelas, int $jumlah): Collection
    {
        return collect(range(1, $jumlah))->map(function (int $nomor) use ($tahun, $kelas) {
            $siswa = Siswa::create([
                'nama_lengkap' => 'Siswa Rahasia '.$nomor,
                'nis' => '32'.str_pad((string) $nomor, 8, '0', STR_PAD_LEFT),
                'nisn' => '99'.str_pad((string) $nomor, 8, '0', STR_PAD_LEFT),
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
        ?string $saran,
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
}
