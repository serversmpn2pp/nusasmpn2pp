<?php

namespace Tests\Feature;

use App\Models\JenisPerangkatAjar;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\PerangkatAjar;
use App\Models\RiwayatFilePerangkatAjar;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerangkatAjarUploadUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_unggah_pdf_memeriksa_ukuran_sebelum_dikirim(): void
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Guru Perangkat Ajar',
            'nip' => '198001012010016666',
            'aktif' => true,
        ]);
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $administrator->update(['pegawai_id' => $pegawai->id]);

        $this->actingAs($administrator)
            ->get(route('perangkat-ajar-saya.create'))
            ->assertOk()
            ->assertSee('data-perangkat-ajar-form', false)
            ->assertSee('data-pdf-input', false)
            ->assertSee('data-max-bytes=', false)
            ->assertSee('data-pdf-client-error', false)
            ->assertSee('Ukuran file ${formatUkuran(file.size)}', false)
            ->assertSee('Mengunggah PDF...', false);
    }

    public function test_detail_perangkat_ajar_menyediakan_riwayat_responsif_untuk_hp(): void
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Guru Perangkat Ajar',
            'nip' => '198001012010016667',
            'aktif' => true,
        ]);
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $administrator->update(['pegawai_id' => $pegawai->id]);

        $tahunPelajaran = TahunPelajaran::create([
            'nama' => '2035/2036',
            'aktif' => true,
        ]);
        $mataPelajaran = MataPelajaran::create([
            'kode' => 'RESP-UI',
            'nama' => 'Pelajaran Responsif',
            'aktif' => true,
        ]);
        $jenisPerangkat = JenisPerangkatAjar::create([
            'kode' => 'MODUL-RESP',
            'nama' => 'Modul Responsif',
            'aktif' => true,
        ]);
        $namaFilePanjang = 'modul-ajar-dengan-nama-file-yang-sangat-panjang-untuk-pengujian-tampilan-hp.pdf';

        $perangkatAjar = PerangkatAjar::create([
            'pegawai_id' => $pegawai->id,
            'tahun_pelajaran_id' => $tahunPelajaran->id,
            'semester' => 1,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'jenis_perangkat_ajar_id' => $jenisPerangkat->id,
            'judul' => 'Modul Ajar Responsif',
            'lokasi_file' => 'perangkat-ajar/uji.pdf',
            'nama_file_asli' => $namaFilePanjang,
            'tipe_file' => 'application/pdf',
            'ukuran_file' => 1024 * 1024,
            'status' => 'menunggu_pemeriksaan',
            'diunggah_pada' => now(),
        ]);
        RiwayatFilePerangkatAjar::create([
            'perangkat_ajar_id' => $perangkatAjar->id,
            'diunggah_oleh_pengguna_id' => $administrator->id,
            'lokasi_file' => 'perangkat-ajar/uji.pdf',
            'nama_file_asli' => $namaFilePanjang,
            'tipe_file' => 'application/pdf',
            'ukuran_file' => 1024 * 1024,
            'diunggah_pada' => now(),
        ]);

        $this->actingAs($administrator)
            ->get(route('perangkat-ajar-saya.show', $perangkatAjar))
            ->assertOk()
            ->assertSee('teaching-document-detail', false)
            ->assertSee('teaching-history-desktop', false)
            ->assertSee('teaching-history-mobile', false)
            ->assertSee($namaFilePanjang)
            ->assertSee('Informasi Dokumen')
            ->assertSee('Riwayat File');
    }
}
