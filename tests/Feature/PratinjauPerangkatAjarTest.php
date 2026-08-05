<?php

namespace Tests\Feature;

use App\Models\JenisPerangkatAjar;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\PerangkatAjar;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PratinjauPerangkatAjarTest extends TestCase
{
    use RefreshDatabase;

    public function test_pemeriksa_dapat_membuka_pdf_privat_sebagai_pratinjau(): void
    {
        Storage::fake('local');
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $perangkatAjar = $this->buatPerangkatAjar();
        Storage::disk('local')->put($perangkatAjar->lokasi_file, '%PDF-1.4 perangkat ajar');

        $respons = $this->actingAs($administrator)
            ->get(route('pemeriksaan-perangkat-ajar.preview', $perangkatAjar))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertHeader('x-content-type-options', 'nosniff');

        $cacheControl = (string) $respons->headers->get('cache-control');
        $this->assertStringContainsString('private', $cacheControl);
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('max-age=0', $cacheControl);
        $this->assertStringContainsString('inline', (string) $respons->headers->get('content-disposition'));
        $this->assertStringContainsString('modul-ajar.pdf', (string) $respons->headers->get('content-disposition'));
    }

    public function test_pratinjau_memberikan_404_jika_file_privat_tidak_tersedia(): void
    {
        Storage::fake('local');
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->get(route('pemeriksaan-perangkat-ajar.preview', $this->buatPerangkatAjar()))
            ->assertNotFound();
    }

    private function buatPerangkatAjar(): PerangkatAjar
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Guru Pemilik Dokumen',
            'nip' => fake()->unique()->numerify('##################'),
            'aktif' => true,
        ]);
        $tahunPelajaran = TahunPelajaran::create([
            'nama' => fake()->unique()->numerify('20##/20##'),
            'aktif' => true,
        ]);
        $mataPelajaran = MataPelajaran::create([
            'kode' => fake()->unique()->bothify('MP-####'),
            'nama' => fake()->unique()->words(2, true),
            'aktif' => true,
        ]);
        $jenis = JenisPerangkatAjar::create([
            'kode' => fake()->unique()->bothify('PA-####'),
            'nama' => fake()->unique()->words(2, true),
            'wajib' => true,
            'aktif' => true,
        ]);

        return PerangkatAjar::create([
            'pegawai_id' => $pegawai->id,
            'tahun_pelajaran_id' => $tahunPelajaran->id,
            'semester' => 1,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'jenis_perangkat_ajar_id' => $jenis->id,
            'judul' => 'Modul Ajar',
            'lokasi_file' => 'perangkat-ajar/modul-ajar.pdf',
            'nama_file_asli' => 'modul-ajar.pdf',
            'tipe_file' => 'application/pdf',
            'ukuran_file' => 24,
            'status' => 'menunggu_pemeriksaan',
            'diunggah_pada' => now(),
        ]);
    }
}
