<?php

namespace Tests\Feature;

use App\Models\Pengguna;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class FiturOmrDihapusTest extends TestCase
{
    use RefreshDatabase;

    public function test_fitur_omr_dan_ljk_tidak_lagi_dapat_diakses(): void
    {
        $administrator = Pengguna::query()->where('username', 'administrator')->firstOrFail();

        $this->assertFalse(Route::has('ujian-omr.index'));
        $this->assertFalse(Route::has('ujian-omr.scan.index'));

        $this->actingAs($administrator)
            ->get('/ujian-omr')
            ->assertNotFound();

        $this->actingAs($administrator)
            ->get(route('beranda'))
            ->assertOk()
            ->assertDontSee('Ujian OMR &amp; LJK', false);

        $this->assertDatabaseMissing('izin', ['kode' => 'omr.lihat']);
        $this->assertDatabaseMissing('izin', ['kode' => 'omr.kelola']);
    }
}
