<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RekapAbsensiHarianResponsifTest extends TestCase
{
    use RefreshDatabase;

    public function test_modal_pesan_whatsapp_menjaga_tombol_salin_tetap_terlihat_di_hp(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->get(route('rekap-absensi-harian.index'))
            ->assertOk()
            ->assertSee('data-wa-summary-copy', false)
            ->assertSee('Salin Pesan')
            ->assertSee('grid-template-rows: auto minmax(0, 1fr) auto;', false)
            ->assertSee('max-height: calc(100svh - 20px);', false)
            ->assertSee('Tekan lama pada teks lalu pilih Salin.');
    }
}
