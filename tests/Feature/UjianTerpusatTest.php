<?php

namespace Tests\Feature;

use App\Models\JenisUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\TahunPelajaran;
use PDO;
use Tests\TestCase;

class UjianTerpusatTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('Driver pdo_sqlite belum aktif pada PHP lokal.');
        }

        $this->artisan('migrate:fresh');
    }

    public function test_administrator_membuat_fondasi_ujian_terpusat_dan_menentukan_panitia_sesi_ruang(): void
    {
        $admin = $this->buatAdministrator();
        $tahun = $this->buatTahunPelajaran();
        $jenis = JenisUjianCbt::query()->where('kode', 'SAS')->firstOrFail();
        [$pegawaiPanitia, $akunPanitia] = $this->buatAkunPegawai('Panitia Ujian', '19870001');

        $this->actingAs($admin)
            ->post(route('ujian-terpusat.store'), $this->dataKegiatan($tahun, $jenis))
            ->assertRedirect();

        $kegiatan = KegiatanUjianCbt::query()->firstOrFail();
        $this->assertSame('UT-2026-001', $kegiatan->kode);

        $this->actingAs($admin)
            ->post(route('ujian-terpusat.panitia.store', $kegiatan), [
                'pegawai_id' => $pegawaiPanitia->id,
                'jabatan' => 'proktor',
            ])
            ->assertRedirect();
        $this->actingAs($admin)
            ->post(route('ujian-terpusat.sesi.store', $kegiatan), [
                'nama' => 'Sesi Pagi',
                'waktu_mulai' => '07:30',
                'waktu_selesai' => '09:30',
                'aktif' => '1',
            ])
            ->assertRedirect();
        $this->actingAs($admin)
            ->post(route('ujian-terpusat.ruang.store', $kegiatan), [
                'nama' => 'Ruang 1',
                'lokasi' => 'Kelas VII.A',
                'kapasitas' => 20,
                'aktif' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('panitia_ujian_cbt', [
            'kegiatan_ujian_cbt_id' => $kegiatan->id,
            'pegawai_id' => $pegawaiPanitia->id,
            'jabatan' => 'proktor',
        ]);
        $this->assertDatabaseHas('sesi_kegiatan_ujian_cbt', [
            'kegiatan_ujian_cbt_id' => $kegiatan->id,
            'kode' => 'S01',
        ]);
        $this->assertDatabaseHas('ruang_kegiatan_ujian_cbt', [
            'kegiatan_ujian_cbt_id' => $kegiatan->id,
            'kode' => 'R01',
            'kapasitas' => 20,
        ]);
        $this->assertTrue($akunPanitia->fresh()->memilikiPeran('panitia_ujian'));

        $this->actingAs($admin)
            ->get(route('ujian-terpusat.show', $kegiatan))
            ->assertOk()
            ->assertSee('Ruang kerja panitia')
            ->assertSee('Panitia Ujian')
            ->assertDontSee('Sesi Pagi')
            ->assertDontSee('Ruang 1')
            ->assertDontSee('Edit informasi')
            ->assertSee('Lanjut ke Sesi')
            ->assertDontSee('Paket CBT');

        $this->actingAs($admin)
            ->get(route('ujian-terpusat.show', ['kegiatanUjianCbt' => $kegiatan, 'tahap' => 3]))
            ->assertOk()
            ->assertSee('Sesi Pagi')
            ->assertDontSee('Panitia Ujian')
            ->assertDontSee('Ruang 1')
            ->assertSee('Kembali ke Panitia')
            ->assertSee('Lanjut ke Ruang');

        $this->actingAs($admin)
            ->get(route('ujian-terpusat.show', ['kegiatanUjianCbt' => $kegiatan, 'tahap' => 4]))
            ->assertOk()
            ->assertSee('Ruang 1')
            ->assertDontSee('Panitia Ujian')
            ->assertDontSee('Sesi Pagi')
            ->assertSee('Kembali ke Sesi')
            ->assertSeeText('Lanjut ke Penetapan Ruang');
    }

    public function test_panitia_hanya_melihat_kegiatan_tempat_dirinya_ditugaskan(): void
    {
        $admin = $this->buatAdministrator();
        $tahun = $this->buatTahunPelajaran();
        $jenis = JenisUjianCbt::query()->where('kode', 'STS')->firstOrFail();
        [$pegawaiPanitia, $akunPanitia] = $this->buatAkunPegawai('Panitia Terbatas', '19870002');
        $kegiatanSendiri = $this->buatKegiatan($admin, $tahun, $jenis, 'STS Ganjil');
        $kegiatanLain = $this->buatKegiatan($admin, $tahun, $jenis, 'STS Susulan');

        $this->actingAs($admin)->post(route('ujian-terpusat.panitia.store', $kegiatanSendiri), [
            'pegawai_id' => $pegawaiPanitia->id,
            'jabatan' => 'anggota',
        ]);

        $this->actingAs($akunPanitia)
            ->get(route('ujian-terpusat.index'))
            ->assertOk()
            ->assertSee('STS Ganjil')
            ->assertDontSee('STS Susulan');
        $this->actingAs($akunPanitia)
            ->get(route('ujian-terpusat.show', $kegiatanSendiri))
            ->assertOk();
        $this->actingAs($akunPanitia)
            ->get(route('ujian-terpusat.show', $kegiatanLain))
            ->assertForbidden();
        $this->actingAs($akunPanitia)
            ->post(route('ujian-terpusat.sesi.store', $kegiatanSendiri), [
                'nama' => 'Sesi Siang',
                'waktu_mulai' => '10:00',
                'waktu_selesai' => '12:00',
                'aktif' => '1',
            ])
            ->assertRedirect();
    }

    public function test_form_ujian_terpusat_tidak_lagi_meminta_mapel_paket_token_dan_kelas(): void
    {
        $admin = $this->buatAdministrator();
        $this->buatTahunPelajaran();

        $response = $this->actingAs($admin)
            ->get(route('ujian-terpusat.create'))
            ->assertOk()
            ->assertSee('Buat kegiatan ujian')
            ->assertSee('Nama kegiatan');

        $response->assertSee('name="nama"', false)
            ->assertDontSee('name="mata_pelajaran_id"', false)
            ->assertDontSee('name="token"', false)
            ->assertDontSee('name="kelas_peserta', false)
            ->assertDontSee('name="jumlah_soal"', false);
    }

    private function buatAdministrator(): Pengguna
    {
        return Pengguna::create([
            'nama' => 'Administrator Uji',
            'username' => 'administrator-uji',
            'kata_sandi' => 'rahasia123',
            'wajib_ganti_kata_sandi' => false,
            'peran' => 'administrator',
            'aktif' => true,
            'akun_sistem' => true,
        ]);
    }

    private function buatAkunPegawai(string $nama, string $nip): array
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => $nama,
            'nip' => $nip,
            'jenis_kelamin' => 'L',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $akun = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $nama,
            'username' => $nip,
            'kata_sandi' => 'rahasia123',
            'wajib_ganti_kata_sandi' => false,
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);

        return [$pegawai, $akun];
    }

    private function buatTahunPelajaran(): TahunPelajaran
    {
        return TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
    }

    private function buatKegiatan(Pengguna $admin, TahunPelajaran $tahun, JenisUjianCbt $jenis, string $nama): KegiatanUjianCbt
    {
        $this->actingAs($admin)->post(route('ujian-terpusat.store'), [
            ...$this->dataKegiatan($tahun, $jenis),
            'nama' => $nama,
        ]);

        return KegiatanUjianCbt::query()->where('nama', $nama)->firstOrFail();
    }

    private function dataKegiatan(TahunPelajaran $tahun, JenisUjianCbt $jenis): array
    {
        return [
            'jenis_ujian_cbt_id' => $jenis->id,
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'SAS Ganjil 2026/2027',
            'semester' => 'ganjil',
            'tanggal_mulai' => '2026-12-01',
            'tanggal_selesai' => '2026-12-08',
            'status' => 'draft',
        ];
    }
}
