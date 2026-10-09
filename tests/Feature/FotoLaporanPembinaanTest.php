<?php

namespace Tests\Feature;

use App\Models\LaporanPembinaanSiswa;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FotoLaporanPembinaanTest extends TestCase
{
    use RefreshDatabase;

    public function test_foto_identitas_tampil_pada_semua_jenis_detail_laporan(): void
    {
        Storage::fake('public');
        $foto = 'siswa/foto/siswa-uji.png';
        Storage::disk('public')->put($foto, File::get(public_path('images/kartu-pelajar/default-user.png')));
        $siswa = Siswa::create(['nama_lengkap' => 'Siswa Foto Uji', 'nisn' => '0099887766', 'foto' => $foto, 'aktif' => true]);
        $this->actingAs(Pengguna::where('username', 'administrator')->firstOrFail());

        foreach (['kejadian', 'pelanggaran', 'pembinaan'] as $jenis) {
            $respons = $this->get(route('laporan-pembinaan-siswa.show', $this->buatLaporan($siswa, $jenis)))
                ->assertOk()
                ->assertViewHas('fotoSiswaUrl', asset('storage/'.$foto))
                ->assertSee('alt="Foto Siswa Foto Uji"', false)
                ->assertSee('decoding="async" data-student-photo', false);

            $xpath = $this->xpath($respons->getContent());
            $this->assertSame(1, $xpath->query('//*[@data-student-avatar]/img')->length);
            $this->assertSame(1, $xpath->query('//*[@data-student-photo-fallback and @hidden]')->length);

            if ($jenis === 'pelanggaran' && getenv('NUSA_CAPTURE_FOTO_LAPORAN')) {
                $directory = storage_path('framework/testing/foto-laporan');
                File::ensureDirectoryExists($directory);
                File::put($directory.'/foto.html', $respons->getContent());
                File::put($directory.'/foto.png', Storage::disk('public')->get($foto));
            }
        }
    }

    public function test_tanpa_foto_atau_file_hilang_menampilkan_inisial(): void
    {
        Storage::fake('public');
        $siswa = Siswa::create(['nama_lengkap' => 'Siswa Foto Uji', 'aktif' => true]);
        $laporan = $this->buatLaporan($siswa);
        $this->actingAs(Pengguna::where('username', 'administrator')->firstOrFail());

        foreach ([null, '', 'siswa/foto/sudah-dihapus.png'] as $foto) {
            $siswa->update(['foto' => $foto]);
            $respons = $this->get(route('laporan-pembinaan-siswa.show', $laporan))
                ->assertOk()
                ->assertViewHas('fotoSiswaUrl', null);

            $xpath = $this->xpath($respons->getContent());
            $this->assertSame(0, $xpath->query('//*[@data-student-avatar]/img')->length);
            $this->assertSame(1, $xpath->query('//*[@data-student-photo-fallback and not(@hidden)]')->length);
            $this->assertSame('SI', $xpath->query('//*[@data-student-photo-fallback]')->item(0)->textContent);

            if ($foto === null && getenv('NUSA_CAPTURE_FOTO_LAPORAN')) {
                $directory = storage_path('framework/testing/foto-laporan');
                File::ensureDirectoryExists($directory);
                File::put($directory.'/inisial.html', $respons->getContent());
            }
        }
    }

    public function test_detail_memakai_foto_siswa_terbaru_tanpa_mengubah_laporan(): void
    {
        Storage::fake('public');
        $siswa = Siswa::create(['nama_lengkap' => 'Siswa Foto Uji', 'aktif' => true]);
        $laporan = $this->buatLaporan($siswa);
        $this->actingAs(Pengguna::where('username', 'administrator')->firstOrFail());
        $this->get(route('laporan-pembinaan-siswa.show', $laporan))->assertOk()->assertViewHas('fotoSiswaUrl', null);

        foreach (['foto-awal.png', 'foto-baru.png'] as $namaFoto) {
            $foto = 'siswa/foto/'.$namaFoto;
            Storage::disk('public')->put($foto, File::get(public_path('images/kartu-pelajar/default-user.png')));
            $siswa->update(['foto' => $foto]);
            $this->get(route('laporan-pembinaan-siswa.show', $laporan))
                ->assertOk()->assertViewHas('fotoSiswaUrl', asset('storage/'.$foto));
        }

        $this->assertSame($laporan->getRawOriginal('updated_at'), $laporan->fresh()->getRawOriginal('updated_at'));
    }

    public function test_foto_tidak_membuka_akses_detail_laporan_di_luar_cakupan(): void
    {
        Storage::fake('public');
        $foto = 'siswa/foto/privasi-uji.png';
        Storage::disk('public')->put($foto, File::get(public_path('images/kartu-pelajar/default-user.png')));
        $siswa = Siswa::create(['nama_lengkap' => 'Siswa Foto Uji', 'foto' => $foto, 'aktif' => true]);
        $laporan = $this->buatLaporan($siswa);
        $pegawai = Pegawai::create(['nama_lengkap' => 'Guru Di Luar Cakupan Foto', 'aktif' => true]);
        $pengguna = Pengguna::create([
            'nama' => $pegawai->nama_lengkap, 'username' => 'guru-luar-foto', 'kata_sandi' => 'Uji-Foto-2026',
            'peran' => 'pegawai', 'pegawai_id' => $pegawai->id, 'aktif' => true,
        ]);
        $pengguna->daftarPeran()->attach(Peran::where('kode', 'guru_mapel')->firstOrFail());

        $this->actingAs($pengguna)->get(route('laporan-pembinaan-siswa.show', $laporan))
            ->assertForbidden()->assertDontSee(asset('storage/'.$foto));
    }

    private function buatLaporan(Siswa $siswa, string $jenis = 'kejadian'): LaporanPembinaanSiswa
    {
        return LaporanPembinaanSiswa::create([
            'nomor_laporan' => 'LP-FOTO-'.$jenis, 'jenis_laporan' => $jenis, 'tanggal_kejadian' => '2026-10-07',
            'siswa_id' => $siswa->id, 'tingkat' => 'sedang', 'status' => 'baru',
            'status_verifikasi' => 'diajukan', 'total_poin' => 0, 'kronologi' => 'Kronologi uji foto identitas siswa.',
            'dibuat_oleh_pengguna_id' => Pengguna::where('username', 'administrator')->firstOrFail()->id,
        ]);
    }

    private function xpath(string $html): \DOMXPath
    {
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new \DOMXPath($document);
    }
}
