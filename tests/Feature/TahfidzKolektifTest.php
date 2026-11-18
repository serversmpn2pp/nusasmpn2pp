<?php

namespace Tests\Feature;

use App\Models\KelompokTahfidz;
use App\Models\NotifikasiPengguna;
use App\Models\OrangTuaWali;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\SetoranTahfidz;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Tahfidz\RekapTahfidzService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class TahfidzKolektifTest extends TestCase
{
    use RefreshDatabase;

    public function test_pembimbing_memilih_surah_dan_siswa_tanpa_mengubah_mode_tunggal(): void
    {
        [$guru, $kelompok, $pertemuan, $anggota] = $this->data();
        $this->actingAs($guru);
        $this->get(route('tahfidz.pertemuan.show', [$kelompok, $pertemuan]))
            ->assertOk()->assertSee('Catat setoran')->assertSee('Input kolektif');
        $kosong = $this->get($this->url($kelompok, $pertemuan))->assertOk()
            ->assertSee('Belum ada surah yang dipilih.')->assertDontSee('data-tf-batch-form', false);
        $this->capture('empty', $kosong->getContent());
        $tabel = $this->get($this->url($kelompok, $pertemuan, ['surah' => [112, 113, 114]]))
            ->assertOk()->assertViewHas('surahDipilih', fn ($surah) => $surah->pluck('nomor')->all() === [112, 113, 114])
            ->assertViewHas('anggota', fn ($items) => $items->total() === 2)->assertSee('Murid Simulasi 01')->assertSee('Murid Simulasi 02')
            ->assertSee('data-sidebar-search-text="akademik penilaian tahfidz"', false);
        $this->capture('table', $tabel->getContent());
        $this->assertStringContainsString('no-store', $tabel->headers->get('Cache-Control'));
        $satu = $this->get($this->url($kelompok, $pertemuan, ['surah' => [112, 114], 'siswa' => $anggota[1]->id]))->assertOk()
            ->assertViewHas('anggota', fn ($items) => $items->total() === 1 && $items->first()->is($anggota[1]));
        $this->capture('student', $satu->getContent());
        $this->assertDatabaseCount('setoran_tahfidz', 0);
    }

    public function test_simpan_banyak_surah_membedakan_nol_kosong_dan_nilai_desimal(): void
    {
        [$guru, $kelompok, $pertemuan, $anggota] = $this->data();
        $payload = $this->payload($anggota);
        $this->actingAs($guru)->post($this->url($kelompok, $pertemuan), $payload)->assertSessionHasNoErrors()
            ->assertRedirect($this->url($kelompok, $pertemuan, ['surah' => [112, 113, 114]]));
        $this->assertDatabaseCount('setoran_tahfidz', 4);
        $this->assertDatabaseCount('batch_setoran_tahfidz', 1);
        $this->assertDatabaseHas('setoran_tahfidz', ['anggota_tahfidz_id' => $anggota[0]->id, 'nomor_surah' => 113, 'nilai' => 0, 'penilai_id' => $guru->id]);
        $this->assertDatabaseHas('setoran_tahfidz', ['anggota_tahfidz_id' => $anggota[0]->id, 'nomor_surah' => 112, 'nilai' => 90.25]);
        $this->assertDatabaseMissing('setoran_tahfidz', ['anggota_tahfidz_id' => $anggota[0]->id, 'nomor_surah' => 114]);
        $this->assertDatabaseMissing('setoran_tahfidz', ['anggota_tahfidz_id' => $anggota[1]->id, 'nomor_surah' => 113]);
        $this->assertDatabaseCount('notifikasi_pengguna', 0);
    }

    public function test_retry_tidak_rangkap_dan_token_lama_tidak_bisa_digunakan_untuk_isian_berbeda(): void
    {
        [$guru, $kelompok, $pertemuan, $anggota] = $this->data();
        $payload = $this->payload($anggota);
        $url = $this->url($kelompok, $pertemuan);
        $this->actingAs($guru)->post($url, $payload)->assertSessionHasNoErrors();
        $ulang = $payload;
        $ulang['nilai'][$anggota[0]->id][112] = '90.25';
        $ulang['nilai'] = array_reverse($ulang['nilai'], true);
        $this->post($url, $ulang)->assertSessionHasNoErrors();
        $ulang['nilai'][$anggota[0]->id][112] = '91';
        $this->post($url, $ulang)->assertSessionHasErrors('token_batch');
        unset($ulang['nilai'][$anggota[0]->id][112]);
        $this->post($url, $ulang)->assertSessionHasErrors('token_batch');
        $this->assertDatabaseCount('setoran_tahfidz', 4);
        $this->assertDatabaseCount('batch_setoran_tahfidz', 1);
        $this->post($url, [...$payload, 'token_batch' => (string) Str::uuid()])->assertSessionHasNoErrors();
        $this->assertDatabaseCount('setoran_tahfidz', 8);
    }

    public function test_tidak_menimpa_riwayat_dan_acuan_mengikuti_tanggal_pertemuan_bukan_waktu_input(): void
    {
        [$guru, $kelompok, $pertemuan, $anggota] = $this->data();
        $lain = $kelompok->pertemuan()->create(['tanggal' => '2026-11-18']);
        $lama = $kelompok->pertemuan()->create(['tanggal' => '2026-11-05']);
        $depan = $kelompok->pertemuan()->create(['tanggal' => '2026-11-20']);
        foreach ([[$lain, '85.5'], [$lain, 0], [$lama, 70], [$depan, 99]] as [$sesi, $nilai]) {
            $sesi->setoran()->create(['anggota_tahfidz_id' => $anggota[0]->id, 'nomor_surah' => 112, 'nilai' => $nilai, 'token_setoran' => (string) Str::uuid()]);
        }
        $url = $this->url($kelompok, $pertemuan, ['surah' => [112]]);
        $acuan = $this->actingAs($guru)->get($url)->assertOk()->viewData('terakhir')[$anggota[0]->id][112];
        $this->assertSame('0.00', $acuan->nilai);
        $this->assertSame('2026-11-18', Carbon::parse($acuan->tanggal_setoran)->toDateString());
        $payload = $this->payload($anggota);
        $this->post($this->url($kelompok, $pertemuan), $payload)->assertSessionHasNoErrors();
        $after = $this->get($this->url($kelompok, $pertemuan, ['surah' => [112, 113, 114]]))->assertOk()
            ->assertViewHas('terakhir', fn ($items) => $items[$anggota[0]->id][112]->nilai === '90.25');
        $this->capture('reference', $after->getContent());
        $this->assertDatabaseCount('setoran_tahfidz', 8);
        $this->assertSame('0.00', $lain->setoran()->orderByDesc('id')->first()->nilai);
        $rekap = app(RekapTahfidzService::class)->siswa($kelompok, $anggota[0]);
        $this->assertSame(2, $rekap['sudah']);
    }

    public function test_nilai_tidak_valid_menolak_seluruh_batch_dan_mempertahankan_isian(): void
    {
        [$guru, $kelompok, $pertemuan, $anggota] = $this->data();
        $this->actingAs($guru);
        $payload = $this->payload($anggota);
        $key = 'nilai.'.$anggota[1]->id.'.114';
        foreach (['101', '-1', '90,123', '9e1', 'NaN', ['90']] as $nilai) {
            $salah = $payload;
            $salah['nilai'][$anggota[1]->id][114] = $nilai;
            $this->from($this->url($kelompok, $pertemuan, ['surah' => [112, 113, 114]]))
                ->post($this->url($kelompok, $pertemuan), $salah)->assertSessionHasErrors($key)
                ->assertSessionHasInput('nilai.'.$anggota[0]->id.'.112', '90.25');
            $this->assertDatabaseCount('setoran_tahfidz', 0);
            $this->assertDatabaseCount('batch_setoran_tahfidz', 0);
        }
        $salah['nilai'][$anggota[1]->id][114] = '101';
        $this->post($this->url($kelompok, $pertemuan), $salah);
        $error = $this->get($this->url($kelompok, $pertemuan, ['surah' => [112, 113, 114]]))->assertOk()->assertSee('aria-invalid="true"', false);
        $this->capture('invalid', $error->getContent());
    }

    public function test_semua_kosong_ditolak_tanpa_mencatat_setoran(): void
    {
        [$guru, $kelompok, $pertemuan, $anggota] = $this->data();
        $payload = $this->payload($anggota);
        $payload['nilai'] = [$anggota[0]->id => [112 => '', 113 => null, 114 => '   ']];
        $this->actingAs($guru)->post($this->url($kelompok, $pertemuan), $payload)->assertSessionHasErrors('nilai');
        $this->assertDatabaseCount('setoran_tahfidz', 0);
        $this->assertDatabaseCount('batch_setoran_tahfidz', 0);
    }

    public function test_kegagalan_di_tengah_penyimpanan_membatalkan_seluruh_setoran_dan_bisa_diulang(): void
    {
        [$guru, $kelompok, $pertemuan, $anggota] = $this->data();
        $aktif = true;
        $jumlah = 0;
        Event::listen('eloquent.created: '.SetoranTahfidz::class, function () use (&$aktif, &$jumlah) {
            if ($aktif && ++$jumlah === 2) {
                throw new RuntimeException('Simulasi kegagalan penyimpanan batch.');
            }
        });
        $this->actingAs($guru)->withoutExceptionHandling();
        $payload = $this->payload($anggota);
        try {
            $this->post($this->url($kelompok, $pertemuan), $payload);
            $this->fail('Kegagalan penyimpanan seharusnya diteruskan.');
        } catch (RuntimeException $error) {
            $this->assertSame('Simulasi kegagalan penyimpanan batch.', $error->getMessage());
        } finally {
            $aktif = false;
        }
        $this->assertDatabaseCount('setoran_tahfidz', 0);
        $this->assertDatabaseCount('batch_setoran_tahfidz', 0);
        $this->post($this->url($kelompok, $pertemuan), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('setoran_tahfidz', 4);
        $this->assertDatabaseCount('batch_setoran_tahfidz', 1);
    }

    public function test_siswa_surah_dan_pertemuan_di_luar_pilihan_ditolak(): void
    {
        [$guru, $kelompok, $pertemuan, $anggota] = $this->data();
        [$guruLain, $kelompokLain, $sesiLain, $anggotaLain] = $this->data();
        $payload = $this->payload($anggota);
        $url = $this->url($kelompok, $pertemuan);
        $this->actingAs($guru);
        $asing = $payload;
        $asing['nilai'][$anggotaLain[0]->id] = [112 => 80];
        $this->post($url, $asing)->assertSessionHasErrors('nilai');
        $this->get($this->url($kelompok, $pertemuan, ['siswa' => $anggotaLain[0]->id]))->assertNotFound();
        $this->post($this->url($kelompok, $sesiLain), $payload)->assertNotFound();
        $this->post($url, [...$payload, 'siswa' => $anggota[0]->id])->assertSessionHasErrors('nilai');
        $mauSurahLain = $payload;
        $mauSurahLain['nilai'][$anggota[0]->id][111] = 70;
        $this->post($url, $mauSurahLain)->assertSessionHasErrors('nilai');
        $mauSurahLain['nilai'][$anggota[0]->id] = ['surah-tidak-valid' => 70];
        $this->post($url, $mauSurahLain)->assertSessionHasErrors('nilai');
        $this->post($url, [...$payload, 'surah' => [1, 112]])->assertSessionHasErrors('surah.0');
        $this->post($url, [...$payload, 'surah' => [112, 112]])->assertSessionHasErrors('surah.0');
        $this->post($url, [...$payload, 'token_batch' => 'salah'])->assertSessionHasErrors('token_batch');
        $this->assertDatabaseCount('setoran_tahfidz', 0);
        $this->assertDatabaseCount('batch_setoran_tahfidz', 0);
    }

    public function test_hanya_pembimbing_aktif_yang_bisa_menggunakan_input_kolektif(): void
    {
        [$guru, $kelompok, $pertemuan, $anggota] = $this->data();
        [$guruLain] = $this->data();
        $url = $this->url($kelompok, $pertemuan);
        $payload = $this->payload($anggota);
        $koordinator = $this->pegawai('Koordinator');
        $koordinator->daftarPeran()->attach(Peran::where('kode', 'koordinator_tahfidz')->sole());
        foreach ([Pengguna::where('username', 'administrator')->sole(), $koordinator] as $akun) {
            $this->actingAs($akun)->get($url)->assertForbidden();
            $this->post($url, $payload)->assertForbidden();
        }
        $this->actingAs($guruLain)->get($url)->assertNotFound();
        $this->post($url, $payload)->assertNotFound();
        $this->actingAs($guru);
        $kelompok->update(['aktif' => false]);
        $this->post($url, $payload)->assertForbidden();
        $kelompok->update(['aktif' => true, 'pembimbing_pegawai_id' => $guruLain->pegawai_id]);
        $this->post($url, $payload)->assertForbidden();
        $this->assertDatabaseCount('setoran_tahfidz', 0);
    }

    public function test_akun_siswa_dan_orang_tua_tidak_memperoleh_akses_kolektif_dengan_peran_koordinator(): void
    {
        [$guru, $kelompok, $pertemuan, $anggota] = $this->data();
        foreach (['siswa', 'orang_tua'] as $jenis) {
            $akun = Pengguna::create(['nama' => 'Akun Simulasi '.$jenis, 'username' => 'tf-kolektif-'.$jenis,
                'peran' => $jenis, 'kata_sandi' => 'UjiKolektif2026', 'aktif' => true,
                'siswa_id' => $jenis === 'siswa' ? $anggota[0]->siswa_id : null]);
            $akun->daftarPeran()->attach(Peran::where('kode', 'koordinator_tahfidz')->sole());
            if ($jenis === 'orang_tua') {
                OrangTuaWali::create(['pengguna_id' => $akun->id, 'nama_lengkap' => $akun->nama]);
            }
            $this->actingAs($akun)->get($this->url($kelompok, $pertemuan))->assertForbidden();
            $this->post($this->url($kelompok, $pertemuan), $this->payload($anggota))->assertForbidden();
        }
        $this->assertDatabaseCount('setoran_tahfidz', 0);
    }

    public function test_anggota_dan_siswa_yang_dinonaktifkan_sebelum_simpan_tidak_bisa_menerima_nilai(): void
    {
        [$guru, $kelompok, $pertemuan, $anggota] = $this->data();
        $this->actingAs($guru);
        $payload = $this->payload($anggota);
        $anggota[1]->forceFill(['aktif' => false])->save();
        $this->post($this->url($kelompok, $pertemuan), $payload)->assertSessionHasErrors('nilai');
        $anggota[1]->forceFill(['aktif' => true])->save();
        $anggota[1]->siswa->update(['aktif' => false]);
        $this->post($this->url($kelompok, $pertemuan), $payload)->assertSessionHasErrors('nilai');
        $this->assertDatabaseCount('setoran_tahfidz', 0);
    }

    public function test_penyelesaian_mengirim_ringkasan_sekali_dan_menutup_setoran_baru(): void
    {
        Queue::fake();
        [$guru, $kelompok, $pertemuan, $anggota] = $this->data();
        $ortu = Pengguna::create(['nama' => 'Wali Simulasi', 'username' => 'wali-kolektif', 'peran' => 'orang_tua',
            'kata_sandi' => 'UjiKolektif2026', 'aktif' => true]);
        $wali = OrangTuaWali::create(['pengguna_id' => $ortu->id, 'nama_lengkap' => $ortu->nama]);
        $wali->siswa()->attach($anggota[0]->siswa_id, ['hubungan' => 'wali', 'utama' => true]);
        $payload = $this->payload($anggota);
        $url = $this->url($kelompok, $pertemuan);
        $this->actingAs($guru)->post($url, $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('notifikasi_pengguna', 0);
        foreach ([1, 2] as $ulang) {
            $this->post(route('tahfidz.pertemuan.selesaikan', [$kelompok, $pertemuan]))->assertSessionHasNoErrors();
        }
        $this->assertDatabaseCount('notifikasi_pengguna', 1);
        $this->assertStringContainsString('menyetorkan 2 surah', NotifikasiPengguna::sole()->pesan);
        $this->post($url, $payload)->assertSessionHasNoErrors();
        $this->post($url, [...$payload, 'token_batch' => (string) Str::uuid()])->assertSessionHasErrors('pertemuan');
        $this->get($url)->assertRedirect(route('tahfidz.pertemuan.show', [$kelompok, $pertemuan]));
        $this->assertDatabaseCount('setoran_tahfidz', 4);
    }

    public function test_tabel_dipaginasi_agar_banyak_surah_tidak_melewati_batas_input_php(): void
    {
        [$guru, $kelompok, $pertemuan, $anggota] = $this->data(18);
        $filter = ['surah' => range(78, 114)];
        $this->actingAs($guru);
        $full = $this->get($this->url($kelompok, $pertemuan, $filter))->assertOk()
            ->assertViewHas('anggota', fn ($items) => $items->count() === 15 && $items->total() === 18);
        $this->capture('full', $full->getContent());
        $this->get($this->url($kelompok, $pertemuan, [...$filter, 'page' => 2]))->assertOk()
            ->assertViewHas('anggota', fn ($items) => $items->count() === 3 && $items->first()->is($anggota[15]));
        $payload = ['surah' => range(78, 114), 'token_batch' => (string) Str::uuid(),
            'nilai' => $anggota->take(15)->mapWithKeys(fn ($row) => [$row->id => array_fill_keys(range(78, 114), '90')])->all()];
        $this->post($this->url($kelompok, $pertemuan), $payload)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('setoran_tahfidz', 555);
        $payload['nilai'][$anggota[15]->id] = [112 => '90'];
        $payload['token_batch'] = (string) Str::uuid();
        $this->post($this->url($kelompok, $pertemuan), $payload)->assertSessionHasErrors('nilai');
        $this->assertDatabaseCount('setoran_tahfidz', 555);
    }

    private function data(int $jumlah = 2): array
    {
        static $nomor = 0;
        $nomor++;
        $this->travelTo(now()->setDate(2026, 11, 19)->setTime(9, 0));
        $guru = $this->pegawai('Pembimbing Simulasi '.$nomor);
        $tahun = TahunPelajaran::firstOrCreate(['nama' => '2026/2027'], [
            'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true,
        ]);
        $kelompok = KelompokTahfidz::create(['tahun_pelajaran_id' => $tahun->id, 'pembimbing_pegawai_id' => $guru->pegawai_id,
            'nama' => 'Kelompok Simulasi '.$nomor, 'aktif' => true]);
        for ($i = 1; $i <= $jumlah; $i++) {
            $murid = Siswa::create(['nama_lengkap' => sprintf('Murid Simulasi %02d', $i), 'nis' => $nomor.'TF'.$i, 'aktif' => true]);
            $kelompok->anggota()->forceCreate(['siswa_id' => $murid->id, 'aktif' => true]);
        }
        $pertemuan = $kelompok->pertemuan()->create(['tanggal' => '2026-11-19']);

        return [$guru, $kelompok, $pertemuan, $kelompok->anggota()->with('siswa')->orderBy('id')->get()];
    }

    private function pegawai(string $nama): Pengguna
    {
        $pegawai = Pegawai::create(['nama_lengkap' => $nama, 'jenis_pegawai' => 'Guru', 'aktif' => true]);
        $akun = Pengguna::create(['nama' => $nama, 'username' => 'kolektif-tf-'.$pegawai->id, 'pegawai_id' => $pegawai->id,
            'kata_sandi' => 'UjiKolektif2026', 'peran' => 'pegawai', 'aktif' => true]);
        $akun->daftarPeran()->attach(Peran::where('kode', 'pegawai')->sole());

        return $akun;
    }

    private function payload($anggota): array
    {
        return ['token_batch' => (string) Str::uuid(), 'surah' => [112, 113, 114], 'nilai' => [
            $anggota[0]->id => [112 => '90,25', 113 => '0', 114 => ''],
            $anggota[1]->id => [112 => '85', 113 => null, 114 => '100'],
        ]];
    }

    private function url($kelompok, $pertemuan, array $filter = []): string
    {
        return route('tahfidz.kolektif.index', ['kelompok' => $kelompok, 'pertemuan' => $pertemuan, ...$filter]);
    }

    private function capture(string $nama, string $html): void
    {
        if (getenv('NUSA_CAPTURE_TAHFIDZ_KOLEKTIF')) {
            $path = storage_path('framework/testing/tahfidz-kolektif');
            File::ensureDirectoryExists($path);
            File::put($path.'/'.$nama.'.html', $html);
        }
    }
}
