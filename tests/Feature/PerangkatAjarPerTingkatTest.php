<?php

namespace Tests\Feature;

use App\Models\GuruMataPelajaran;
use App\Models\JenisPerangkatAjar;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\PerangkatAjar;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PerangkatAjarPerTingkatTest extends TestCase
{
    use RefreshDatabase;

    public function test_guru_yang_mengajar_dua_tingkat_wajib_mengunggah_perangkat_per_tingkat(): void
    {
        Storage::fake('local');

        [$administrator, $pegawai, $tahunPelajaran, $mataPelajaran, $jenis] = $this->siapkanPenugasanDuaTingkat();

        $this->actingAs($administrator)
            ->get(route('perangkat-ajar-saya.index', [
                'tahun_pelajaran_id' => $tahunPelajaran->id,
                'semester' => 1,
            ]))
            ->assertOk()
            ->assertSeeTextInOrder([
                'Tingkat VII',
                'Informatika',
                'Tingkat VIII',
                'Informatika',
            ])
            ->assertSee('0/2');

        $this->unggahPerangkat(
            $administrator,
            $tahunPelajaran->id,
            $mataPelajaran->id,
            $jenis->id,
            7,
            'modul-informatika-vii.pdf',
        )->assertRedirect();

        $this->assertDatabaseHas('perangkat_ajar', [
            'pegawai_id' => $pegawai->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'tingkat' => 7,
            'jenis_perangkat_ajar_id' => $jenis->id,
        ]);

        $this->actingAs($administrator)
            ->get(route('perangkat-ajar-saya.index', [
                'tahun_pelajaran_id' => $tahunPelajaran->id,
                'semester' => 1,
            ]))
            ->assertOk()
            ->assertSee('1/2');

        $this->unggahPerangkat(
            $administrator,
            $tahunPelajaran->id,
            $mataPelajaran->id,
            $jenis->id,
            8,
            'modul-informatika-viii.pdf',
        )->assertRedirect();

        $this->assertDatabaseCount('perangkat_ajar', 2);
        $this->assertDatabaseHas('perangkat_ajar', [
            'pegawai_id' => $pegawai->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'tingkat' => 8,
            'jenis_perangkat_ajar_id' => $jenis->id,
        ]);

        $this->actingAs($administrator)
            ->get(route('pemeriksaan-perangkat-ajar.index', [
                'tahun_pelajaran_id' => $tahunPelajaran->id,
                'semester' => 1,
            ]))
            ->assertOk()
            ->assertSee('2/2 dokumen');

        $this->unggahPerangkat(
            $administrator,
            $tahunPelajaran->id,
            $mataPelajaran->id,
            $jenis->id,
            7,
            'duplikat-vii.pdf',
        )->assertSessionHasErrors('jenis_perangkat_ajar_id');

        $this->assertDatabaseCount('perangkat_ajar', 2);
    }

    public function test_guru_tidak_dapat_memilih_tingkat_yang_tidak_diajarnya(): void
    {
        Storage::fake('local');

        [$administrator, , $tahunPelajaran, $mataPelajaran, $jenis] = $this->siapkanPenugasanDuaTingkat();

        $this->unggahPerangkat(
            $administrator,
            $tahunPelajaran->id,
            $mataPelajaran->id,
            $jenis->id,
            9,
            'modul-informatika-ix.pdf',
        )->assertSessionHasErrors('tingkat');

        $this->assertDatabaseCount('perangkat_ajar', 0);
    }

    public function test_dokumen_lama_dapat_diberi_tingkat_tanpa_menggandakan_file(): void
    {
        [$administrator, $pegawai, $tahunPelajaran, $mataPelajaran, $jenis] = $this->siapkanPenugasanDuaTingkat();
        $perangkatAjar = PerangkatAjar::create([
            'pegawai_id' => $pegawai->id,
            'tahun_pelajaran_id' => $tahunPelajaran->id,
            'semester' => 1,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'jenis_perangkat_ajar_id' => $jenis->id,
            'judul' => 'Dokumen Lama Informatika',
            'lokasi_file' => 'perangkat-ajar/dokumen-lama.pdf',
            'nama_file_asli' => 'dokumen-lama.pdf',
            'tipe_file' => 'application/pdf',
            'ukuran_file' => 1024,
            'status' => 'sudah_diperiksa',
            'diunggah_pada' => now(),
            'diperiksa_pada' => now(),
        ]);

        $this->actingAs($administrator)
            ->get(route('perangkat-ajar-saya.edit', $perangkatAjar))
            ->assertOk()
            ->assertSee('Tingkat VII')
            ->assertSee('Tingkat VIII');

        $this->actingAs($administrator)
            ->put(route('perangkat-ajar-saya.update', $perangkatAjar), [
                'tingkat' => 7,
                'judul' => 'Dokumen Lama Informatika',
            ])
            ->assertRedirect(route('perangkat-ajar-saya.show', $perangkatAjar));

        $this->assertDatabaseCount('perangkat_ajar', 1);
        $this->assertDatabaseHas('perangkat_ajar', [
            'id' => $perangkatAjar->id,
            'tingkat' => 7,
            'status' => 'menunggu_pemeriksaan',
            'pemeriksa_pegawai_id' => null,
        ]);
    }

    private function siapkanPenugasanDuaTingkat(): array
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Guru Informatika Dua Tingkat',
            'nip' => '198001012010019999',
            'aktif' => true,
        ]);
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $administrator->update(['pegawai_id' => $pegawai->id]);
        $tahunPelajaran = TahunPelajaran::create([
            'nama' => '2036/2037',
            'aktif' => true,
        ]);
        $kelasTujuhA = Kelas::create([
            'tahun_pelajaran_id' => $tahunPelajaran->id,
            'nama' => 'VII.A',
            'tingkat' => 7,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $kelasTujuhB = Kelas::create([
            'tahun_pelajaran_id' => $tahunPelajaran->id,
            'nama' => 'VII.B',
            'tingkat' => 7,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $kelasDelapanA = Kelas::create([
            'tahun_pelajaran_id' => $tahunPelajaran->id,
            'nama' => 'VIII.A',
            'tingkat' => 8,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $mataPelajaran = MataPelajaran::create([
            'kode' => 'INF',
            'nama' => 'Informatika',
            'aktif' => true,
        ]);
        $jenis = JenisPerangkatAjar::create([
            'kode' => 'MODUL-UJI',
            'nama' => 'Modul Ajar',
            'wajib' => true,
            'aktif' => true,
        ]);

        foreach ([$kelasTujuhA, $kelasTujuhB, $kelasDelapanA] as $kelas) {
            GuruMataPelajaran::create([
                'tahun_pelajaran_id' => $tahunPelajaran->id,
                'kelas_id' => $kelas->id,
                'mata_pelajaran_id' => $mataPelajaran->id,
                'pegawai_id' => $pegawai->id,
                'aktif' => true,
            ]);
        }

        return [$administrator, $pegawai, $tahunPelajaran, $mataPelajaran, $jenis];
    }

    private function unggahPerangkat(
        Pengguna $pengguna,
        int $tahunPelajaranId,
        int $mataPelajaranId,
        int $jenisPerangkatAjarId,
        int $tingkat,
        string $namaFile,
    ) {
        return $this->actingAs($pengguna)
            ->post(route('perangkat-ajar-saya.store'), [
                'tahun_pelajaran_id' => $tahunPelajaranId,
                'semester' => 1,
                'mata_pelajaran_id' => $mataPelajaranId,
                'tingkat' => $tingkat,
                'jenis_perangkat_ajar_id' => $jenisPerangkatAjarId,
                'judul' => 'Modul Informatika Tingkat '.$tingkat,
                'file_pdf' => UploadedFile::fake()->create($namaFile, 100, 'application/pdf'),
            ]);
    }
}
