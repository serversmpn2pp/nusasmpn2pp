<?php

namespace Tests\Feature;

use App\Models\GuruMataPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\SoalCbt;
use App\Models\TahunPelajaran;
use PDO;
use Tests\TestCase;

class SoalCbtTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('Driver pdo_sqlite belum aktif pada PHP lokal.');
        }

        $this->artisan('migrate:fresh');
    }

    public function test_administrator_dapat_mengelola_bank_soal_cbt(): void
    {
        [$tahunPelajaran, $mataPelajaran] = $this->buatDataAkademik();
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->actingAs($administrator)
            ->get(route('soal-cbt.create'))
            ->assertOk()
            ->assertSee('Tambah soal CBT')
            ->assertSee('Opsi dan Kunci Jawaban');

        $this->actingAs($administrator)
            ->post(route('soal-cbt.store'), $this->dataSoal($tahunPelajaran, $mataPelajaran))
            ->assertRedirect();

        $soalCbt = SoalCbt::where('kode', 'SOAL-CBT-UJI-001')->firstOrFail();

        $this->assertSame('pilihan_ganda', $soalCbt->jenis_soal);
        $this->assertSame('B', $soalCbt->kunci_jawaban['jawaban']);
        $this->assertSame('2 Hz', $soalCbt->opsi['pilihan']['B']);

        $this->actingAs($administrator)
            ->get(route('soal-cbt.show', $soalCbt))
            ->assertOk()
            ->assertSee('SOAL-CBT-UJI-001')
            ->assertSee('Getaran yang terjadi');

        $this->actingAs($administrator)
            ->put(route('soal-cbt.update', $soalCbt), [
                ...$this->dataSoal($tahunPelajaran, $mataPelajaran),
                'jenis_soal' => 'pilihan_ganda_kompleks',
                'kunci_pg' => '',
                'kunci_pgk' => ['B', 'C'],
                'status' => 'siap',
            ])
            ->assertRedirect(route('soal-cbt.show', $soalCbt));

        $soalCbt->refresh();
        $this->assertSame('pilihan_ganda_kompleks', $soalCbt->jenis_soal);
        $this->assertSame(['B', 'C'], $soalCbt->kunci_jawaban['jawaban']);
        $this->assertSame('siap', $soalCbt->status);

        $this->actingAs($administrator)
            ->delete(route('soal-cbt.destroy', $soalCbt))
            ->assertRedirect(route('soal-cbt.index'));

        $this->assertSame('arsip', $soalCbt->fresh()->status);
        $this->assertFalse($soalCbt->fresh()->aktif);
    }

    public function test_guru_mapel_hanya_dapat_mengelola_soal_mapel_yang_diajar(): void
    {
        [$tahunPelajaran, $mataPelajaran, $pegawai] = $this->buatDataAkademik();
        $mataPelajaranLain = MataPelajaran::create([
            'kode' => 'IPA-8',
            'nama' => 'IPA Kelas VIII',
            'tingkat' => 8,
            'aktif' => true,
        ]);
        $guruMapel = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => 'Dewi Anggraini',
            'username' => '198201012010012001',
            'kata_sandi' => 'secret',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);
        $guruMapel->daftarPeran()->sync([Peran::where('kode', 'guru_mapel')->value('id')]);

        $this->actingAs($guruMapel)
            ->get(route('soal-cbt.create'))
            ->assertOk()
            ->assertSee('Matematika Kelas VIII')
            ->assertDontSee('IPA Kelas VIII');

        $this->actingAs($guruMapel)
            ->post(route('soal-cbt.store'), $this->dataSoal($tahunPelajaran, $mataPelajaran))
            ->assertRedirect();

        $this->actingAs($guruMapel)
            ->post(route('soal-cbt.store'), $this->dataSoal($tahunPelajaran, $mataPelajaranLain, 'SOAL-CBT-LAIN-001'))
            ->assertForbidden();
    }

    private function buatDataAkademik(): array
    {
        $tahunPelajaran = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahunPelajaran->id,
            'nama' => 'VIII.A',
            'tingkat' => 8,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $mataPelajaran = MataPelajaran::create([
            'kode' => 'MTK-8',
            'nama' => 'Matematika Kelas VIII',
            'tingkat' => 8,
            'aktif' => true,
        ]);
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Dewi Anggraini, S.Pd.',
            'nip' => '198201012010012001',
            'aktif' => true,
        ]);
        GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $tahunPelajaran->id,
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'pegawai_id' => $pegawai->id,
            'jenis_penugasan' => 'pengampu',
            'aktif' => true,
        ]);

        return [$tahunPelajaran, $mataPelajaran, $pegawai];
    }

    private function dataSoal(TahunPelajaran $tahunPelajaran, MataPelajaran $mataPelajaran, string $kode = 'SOAL-CBT-UJI-001'): array
    {
        return [
            'tahun_pelajaran_id' => $tahunPelajaran->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'tingkat' => 8,
            'kode' => $kode,
            'jenis_soal' => 'pilihan_ganda',
            'tingkat_kesulitan' => 'sedang',
            'kategori' => 'umum',
            'topik' => 'Getaran',
            'materi' => 'Frekuensi',
            'tujuan_pembelajaran' => 'Siswa dapat menentukan frekuensi getaran.',
            'stimulus' => null,
            'pertanyaan' => 'Getaran yang terjadi sebanyak 20 kali dalam 10 sekon memiliki frekuensi ....',
            'opsi' => [
                'A' => '0,5 Hz',
                'B' => '2 Hz',
                'C' => '10 Hz',
                'D' => '200 Hz',
            ],
            'kunci_pg' => 'B',
            'kunci_pgk' => [],
            'skor_maksimal' => 1,
            'pembahasan' => 'Frekuensi adalah jumlah getaran dibagi waktu.',
            'status' => 'draft',
            'aktif' => '1',
        ];
    }
}
