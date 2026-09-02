<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Pengguna;
use App\Models\PenguranganPoinSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PenguranganPoinSiswaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_daftar_pengajuan_bukti_dan_menu_native_tersedia(): void
    {
        Storage::fake('public');
        [$administrator, $tahun, $kelas, $siswa] = $this->dataDasar(25);
        $token = $this->token($administrator);

        $this->getJson(route('api.v1.pengurangan-poin-siswa.index'))->assertUnauthorized();
        $response = $this->withToken($token)
            ->getJson(route('api.v1.pengurangan-poin-siswa.index', [
                'tahun_pelajaran_id' => $tahun->id,
                'kelas_id' => $kelas->id,
                'status' => 'semua',
                'kata_kunci' => 'native',
            ]))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.semua', 0)
            ->assertJsonPath('data.hak_akses.dapat_mengajukan', true)
            ->assertJsonPath('data.hak_akses.dapat_memutuskan', true)
            ->assertJsonPath('data.pilihan.siswa.0.id', $siswa->id)
            ->assertJsonPath('data.pilihan.siswa.0.saldo_poin', 25)
            ->assertJsonPath('data.pilihan.siswa.0.kelas.nama', $kelas->nama)
            ->assertJsonPath('data.tahun_pelajaran_aktif.id', $tahun->id);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $dibuat = $this->withToken($token)
            ->post(route('api.v1.pengurangan-poin-siswa.store'), [
                'siswa_id' => $siswa->id,
                'tanggal_kegiatan' => '2026-09-01',
                'jenis_kegiatan' => 'Teladan disiplin',
                'deskripsi' => 'Menjadi teladan kedisiplinan kelas.',
                'poin_pengurangan' => 10,
                'bukti' => UploadedFile::fake()->create('teladan.png', 100, 'image/png'),
            ], ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.siswa.id', $siswa->id)
            ->assertJsonPath('data.status', 'diajukan')
            ->assertJsonPath('data.poin_pengurangan', 10)
            ->assertJsonPath('data.bukti.tipe_file', 'image/png')
            ->assertJsonPath('data.dapat_diputuskan', true);

        $pengurangan = PenguranganPoinSiswa::findOrFail($dibuat->json('data.id'));
        Storage::disk('public')->assertExists($pengurangan->bukti);
        $this->withToken($token)
            ->get(route('api.v1.pengurangan-poin-siswa.bukti', $pengurangan))
            ->assertOk()
            ->assertHeader('content-disposition');

        $this->withToken($token)
            ->getJson(route('api.v1.pengurangan-poin-siswa.index'))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.semua', 1)
            ->assertJsonPath('data.ringkasan.diajukan', 1)
            ->assertJsonPath('data.items.0.id', $pengurangan->id);

        $this->withToken($token)
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'penghargaan-pengurangan-poin',
                'status' => 'tersedia',
                'rute' => '/pengurangan-poin-siswa',
            ]);
    }

    public function test_persetujuan_menerapkan_poin_tanpa_membuat_saldo_negatif_dan_tidak_dapat_diulang(): void
    {
        [$administrator, $tahun, , $siswa] = $this->dataDasar(12);
        $pengurangan = PenguranganPoinSiswa::create([
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'tanggal_kegiatan' => '2026-09-01',
            'jenis_kegiatan' => 'Juara tingkat kota/kabupaten',
            'poin_pengurangan' => 20,
            'status' => 'diajukan',
            'diajukan_oleh_pengguna_id' => $administrator->id,
        ]);
        $token = $this->token($administrator);

        $this->withToken($token)
            ->patchJson(route('api.v1.pengurangan-poin-siswa.putusan', $pengurangan), [
                'keputusan' => 'disetujui',
                'catatan_keputusan' => 'Prestasi telah diverifikasi.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'disetujui')
            ->assertJsonPath('data.poin_pengurangan', 12)
            ->assertJsonPath('data.catatan_keputusan', 'Prestasi telah diverifikasi.')
            ->assertJsonPath('data.dapat_diputuskan', false)
            ->assertJsonPath('message', 'Pengurangan disetujui. 12 poin diterapkan tanpa membuat saldo negatif.');

        $this->assertDatabaseHas('transaksi_poin_siswa', [
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'pengurangan_poin_siswa_id' => $pengurangan->id,
            'kunci_sumber' => 'reward:'.$pengurangan->id,
            'jenis' => 'pengurangan',
            'poin' => -12,
        ]);
        $this->assertSame(0, (int) TransaksiPoinSiswa::where('siswa_id', $siswa->id)->sum('poin'));

        $this->withToken($token)
            ->patchJson(route('api.v1.pengurangan-poin-siswa.putusan', $pengurangan), [
                'keputusan' => 'disetujui',
            ])
            ->assertUnprocessable();
        $this->assertSame(1, TransaksiPoinSiswa::where('kunci_sumber', 'reward:'.$pengurangan->id)->count());
    }

    public function test_pengajuan_menolak_siswa_tanpa_saldo_poin(): void
    {
        [$administrator, , , $siswa] = $this->dataDasar(0);

        $this->withToken($this->token($administrator))
            ->postJson(route('api.v1.pengurangan-poin-siswa.store'), [
                'siswa_id' => $siswa->id,
                'tanggal_kegiatan' => '2026-09-01',
                'jenis_kegiatan' => 'Aktif organisasi',
                'poin_pengurangan' => 10,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('siswa_id');
    }

    private function dataDasar(int $saldo): array
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VIII.PH',
            'tingkat' => 8,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $siswa = Siswa::create([
            'nama_lengkap' => 'Siswa Penghargaan Native',
            'nis' => 'NIS-PH-001',
            'nisn' => '0099887701',
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => '2026-07-01',
        ]);
        if ($saldo > 0) {
            TransaksiPoinSiswa::create([
                'siswa_id' => $siswa->id,
                'tahun_pelajaran_id' => $tahun->id,
                'kunci_sumber' => 'penghargaan-native:saldo:'.$saldo,
                'jenis' => 'pelanggaran',
                'poin' => $saldo,
                'keterangan' => 'Saldo awal pengujian penghargaan native.',
                'tercatat_pada' => now(),
                'dibuat_oleh_pengguna_id' => $administrator->id,
            ]);
        }

        return [$administrator, $tahun, $kelas, $siswa];
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Perangkat Penghargaan Native', ['mobile'])->plainTextToken;
    }
}
