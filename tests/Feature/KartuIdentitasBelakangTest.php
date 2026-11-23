<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class KartuIdentitasBelakangTest extends TestCase
{
    use RefreshDatabase;

    public function test_aset_kartu_pelajar_android_sama_dengan_aset_baku_web(): void
    {
        foreach (['front.jpg', 'back.jpg', 'default-user.png'] as $file) {
            $this->assertFileEquals(
                public_path('images/kartu-pelajar/'.$file),
                base_path('mobile/assets/images/kartu-pelajar/'.$file),
            );
        }
        foreach (['logo-smpn2pp.png', 'logo-nusa.png'] as $file) {
            $this->assertFileEquals(
                public_path('images/kartu-pelajar/'.$file),
                base_path('mobile/assets/images/'.$file),
            );
        }
    }

    public function test_bagian_belakang_kartu_pelajar_menampilkan_nama_dan_nisn(): void
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.A',
            'tingkat' => 7,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $siswa = Siswa::create([
            'nama_lengkap' => 'Anugrah Kartu Pelajar Mobile',
            'nis' => '260001',
            'nisn' => '0000000001',
            'jenis_kelamin' => 'L',
            'tempat_lahir' => 'Padang Panjang',
            'tanggal_lahir' => '2012-11-03',
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => 1,
            'status_keanggotaan' => 'aktif',
        ]);

        $response = $this->actingAs(Pengguna::where('username', 'administrator')->firstOrFail())
            ->get(route('kartu-pelajar.index', [
                'tahun_pelajaran_id' => $tahun->id,
                'kelas_id' => $kelas->id,
                'siswa_id' => $siswa->id,
            ]))
            ->assertOk()
            ->assertSeeInOrder([
                'class="qr-name-card"',
                'Anugrah Kartu Pelajar Mobile',
                'NISN 0000000001',
            ], false)
            ->assertSee('.qr-code-card svg rect', false)
            ->assertSee('fill: #fff;', false)
            ->assertSee('.app-sidebar,', false)
            ->assertSee('.content-shell {', false)
            ->assertSee('Cetak kartu')
            ->assertSee('data-card-export-root', false)
            ->assertSee('data-card-side="depan"', false)
            ->assertSee('data-card-side="belakang"', false)
            ->assertSeeInOrder(['Pilih format kartu', 'PDF', 'PNG', 'JPEG']);
        if (getenv('NUSA_CAPTURE_STUDENT_CARD')) {
            $folder = storage_path('framework/testing/student-card');
            File::ensureDirectoryExists($folder);
            File::put($folder.'/web.html', $response->getContent());
        }
    }

    public function test_bagian_belakang_kartu_pegawai_menampilkan_nama_dan_nip(): void
    {
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Antonius Pitra Dana Arista, M.T.',
            'nip' => '199211032019021001',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);

        $this->actingAs(Pengguna::where('username', 'administrator')->firstOrFail())
            ->get(route('kartu-pegawai.index', [
                'pegawai_id' => $pegawai->id,
            ]))
            ->assertOk()
            ->assertSeeInOrder([
                'class="employee-qr-name"',
                'Antonius Pitra Dana Arista, M.T.',
                'NIP 199211032019021001',
            ], false)
            ->assertSee('.employee-qr-code svg rect', false)
            ->assertSee('fill: #fff;', false)
            ->assertSee('data-card-export-root', false)
            ->assertSee('data-card-side="depan"', false)
            ->assertSee('data-card-side="belakang"', false)
            ->assertSeeInOrder(['Pilih format kartu', 'PDF', 'PNG', 'JPEG']);
    }
}
