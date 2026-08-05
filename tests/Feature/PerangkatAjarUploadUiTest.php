<?php

namespace Tests\Feature;

use App\Models\Pegawai;
use App\Models\Pengguna;
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
}
