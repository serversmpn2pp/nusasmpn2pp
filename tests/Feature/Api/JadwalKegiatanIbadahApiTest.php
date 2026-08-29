<?php

namespace Tests\Feature\Api;

use App\Models\JadwalKegiatanIbadah;
use App\Models\KegiatanIbadah;
use App\Models\Pengguna;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JadwalKegiatanIbadahApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_jadwal_ibadah_memerlukan_token_dan_izin(): void
    {
        $this->getJson(route('api.v1.jadwal-kegiatan-ibadah.index'))
            ->assertUnauthorized();

        $pengguna = Pengguna::create([
            'nama' => 'Guru Tanpa Izin Jadwal Ibadah',
            'username' => 'guru.tanpa.izin.jadwal.ibadah',
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.jadwal-kegiatan-ibadah.index'))
            ->assertForbidden();
    }

    public function test_daftar_memilih_tahun_aktif_dan_menyediakan_enam_hari(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahunLama = $this->buatTahun('2030/2031', false);
        $tahunAktif = $this->buatTahun('2031/2032', true);
        $kegiatan = KegiatanIbadah::where('kode', 'sholat_duhur')->firstOrFail();
        $this->buatJadwal($kegiatan, $tahunAktif, 'senin', true);

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.jadwal-kegiatan-ibadah.index'))
            ->assertOk()
            ->assertJsonPath('data.filter.tahun_pelajaran_id', $tahunAktif->id)
            ->assertJsonPath('data.filter.kegiatan_ibadah_id', $kegiatan->id)
            ->assertJsonPath('data.ringkasan.jumlah_hari', 6)
            ->assertJsonPath('data.ringkasan.sudah_diatur', 1)
            ->assertJsonPath('data.ringkasan.aktif', 1)
            ->assertJsonCount(6, 'data.referensi.hari')
            ->assertJsonPath('data.items.0.hari', 'senin')
            ->assertJsonPath('data.items.0.jam_scan_mulai', '11:45')
            ->assertJsonFragment(['id' => $tahunLama->id, 'nama' => '2030/2031', 'aktif' => false]);
    }

    public function test_administrator_dapat_menerapkan_jadwal_ke_beberapa_hari(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = $this->buatTahun('2032/2033', true);
        $kegiatan = KegiatanIbadah::where('kode', 'sholat_duhur')->firstOrFail();

        $payload = [
            'kegiatan_ibadah_id' => $kegiatan->id,
            'tahun_pelajaran_id' => $tahun->id,
            'hari' => ['senin', 'selasa', 'rabu'],
            'jam_scan_mulai' => '11:45',
            'jam_pelaksanaan' => '12:15',
            'jam_scan_selesai' => '13:15',
            'aktif' => true,
            'keterangan' => '  Mushalla sekolah  ',
        ];

        $this->withToken($this->token($administrator))
            ->postJson(route('api.v1.jadwal-kegiatan-ibadah.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('pesan', '3 jadwal kegiatan ibadah berhasil diterapkan.');

        $this->assertSame(3, JadwalKegiatanIbadah::where('kegiatan_ibadah_id', $kegiatan->id)->count());
        $this->assertDatabaseHas('jadwal_kegiatan_ibadah', [
            'tahun_pelajaran_id' => $tahun->id,
            'hari' => 'rabu',
            'urutan_hari' => 3,
            'keterangan' => 'Mushalla sekolah',
            'aktif' => true,
        ]);

        $payload['hari'] = ['senin'];
        $payload['jam_pelaksanaan'] = '12:20';
        $this->withToken($this->token($administrator))
            ->postJson(route('api.v1.jadwal-kegiatan-ibadah.store'), $payload)
            ->assertCreated();

        $this->assertSame(3, JadwalKegiatanIbadah::where('kegiatan_ibadah_id', $kegiatan->id)->count());
        $this->assertDatabaseHas('jadwal_kegiatan_ibadah', [
            'hari' => 'senin',
            'jam_pelaksanaan' => '12:20',
        ]);
    }

    public function test_waktu_dan_kegiatan_nonaktif_divalidasi(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = $this->buatTahun('2033/2034', true);
        $kegiatan = KegiatanIbadah::where('kode', 'sholat_duhur')->firstOrFail();
        $token = $this->token($administrator);
        $payload = [
            'kegiatan_ibadah_id' => $kegiatan->id,
            'tahun_pelajaran_id' => $tahun->id,
            'hari' => ['senin'],
            'jam_scan_mulai' => '12:30',
            'jam_pelaksanaan' => '12:15',
            'jam_scan_selesai' => '13:00',
            'aktif' => true,
        ];

        $this->withToken($token)
            ->postJson(route('api.v1.jadwal-kegiatan-ibadah.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('jam_pelaksanaan');

        $kegiatan->update(['aktif' => false]);
        $payload['jam_scan_mulai'] = '11:45';
        $this->withToken($token)
            ->postJson(route('api.v1.jadwal-kegiatan-ibadah.store'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('kegiatan_ibadah_id');
    }

    public function test_administrator_dapat_mengubah_dan_menonaktifkan_satu_hari(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = $this->buatTahun('2034/2035', true);
        $kegiatan = KegiatanIbadah::where('kode', 'sholat_duhur')->firstOrFail();
        $jadwal = $this->buatJadwal($kegiatan, $tahun, 'kamis', true);
        $token = $this->token($administrator);

        $this->withToken($token)
            ->patchJson(route('api.v1.jadwal-kegiatan-ibadah.update', $jadwal), [
                'jam_scan_mulai' => '11:50',
                'jam_pelaksanaan' => '12:20',
                'jam_scan_selesai' => '13:10',
                'aktif' => true,
                'keterangan' => 'Lokasi baru',
            ])
            ->assertOk()
            ->assertJsonPath('pesan', 'Jadwal Kamis berhasil diperbarui.');

        $this->assertDatabaseHas('jadwal_kegiatan_ibadah', [
            'id' => $jadwal->id,
            'jam_scan_mulai' => '11:50',
            'jam_pelaksanaan' => '12:20',
            'keterangan' => 'Lokasi baru',
        ]);

        $this->withToken($token)
            ->deleteJson(route('api.v1.jadwal-kegiatan-ibadah.destroy', $jadwal))
            ->assertOk();

        $this->assertDatabaseHas('jadwal_kegiatan_ibadah', ['id' => $jadwal->id, 'aktif' => false]);
    }

    private function buatTahun(string $nama, bool $aktif): TahunPelajaran
    {
        $mulai = (int) substr($nama, 0, 4);

        return TahunPelajaran::create([
            'nama' => $nama,
            'tanggal_mulai' => $mulai.'-07-01',
            'tanggal_selesai' => ($mulai + 1).'-06-30',
            'aktif' => $aktif,
        ]);
    }

    private function buatJadwal(
        KegiatanIbadah $kegiatan,
        TahunPelajaran $tahun,
        string $hari,
        bool $aktif,
    ): JadwalKegiatanIbadah {
        return JadwalKegiatanIbadah::create([
            'kegiatan_ibadah_id' => $kegiatan->id,
            'tahun_pelajaran_id' => $tahun->id,
            'hari' => $hari,
            'urutan_hari' => JadwalKegiatanIbadah::DAFTAR_HARI[$hari]['urutan'],
            'jam_scan_mulai' => '11:45',
            'jam_pelaksanaan' => '12:15',
            'jam_scan_selesai' => '13:15',
            'aktif' => $aktif,
            'keterangan' => 'Mushalla sekolah',
        ]);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
