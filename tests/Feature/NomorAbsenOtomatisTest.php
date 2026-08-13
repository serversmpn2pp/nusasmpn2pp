<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NomorAbsenOtomatisTest extends TestCase
{
    use RefreshDatabase;

    public function test_nomor_absen_otomatis_mengikuti_nama_siswa_a_sampai_z(): void
    {
        [$tahun, $kelas] = $this->buatKelas('VII.A');
        $zaki = $this->buatSiswa('Zaki Pratama', '0010000001');
        $andi = $this->buatSiswa('Andi Saputra', '0010000002');
        $mira = $this->buatSiswa('Mira Lestari', '0010000003');

        $this->tempatkan($tahun, $kelas, $zaki, 20);
        $this->tempatkan($tahun, $kelas, $andi, null);
        $this->tempatkan($tahun, $kelas, $mira, 99);

        $this->assertUrutanKelas($kelas, [
            'Andi Saputra' => 1,
            'Mira Lestari' => 2,
            'Zaki Pratama' => 3,
        ]);
    }

    public function test_nomor_absen_disusun_ulang_setelah_siswa_dikeluarkan_atau_namanya_berubah(): void
    {
        [$tahun, $kelas] = $this->buatKelas('VII.B');
        $andi = $this->buatSiswa('Andi Saputra', '0020000001');
        $mira = $this->buatSiswa('Mira Lestari', '0020000002');
        $zaki = $this->buatSiswa('Zaki Pratama', '0020000003');

        $anggotaAndi = $this->tempatkan($tahun, $kelas, $andi, null);
        $this->tempatkan($tahun, $kelas, $mira, null);
        $this->tempatkan($tahun, $kelas, $zaki, null);

        $anggotaAndi->delete();

        $this->assertUrutanKelas($kelas, [
            'Mira Lestari' => 1,
            'Zaki Pratama' => 2,
        ]);

        $zaki->update(['nama_lengkap' => 'Budi Pratama']);

        $this->assertUrutanKelas($kelas, [
            'Budi Pratama' => 1,
            'Mira Lestari' => 2,
        ]);
    }

    public function test_nomor_absen_kelas_asal_dan_tujuan_diperbarui_saat_siswa_dipindahkan(): void
    {
        [$tahun, $kelasA] = $this->buatKelas('VIII.A');
        $kelasB = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VIII.B',
            'tingkat' => 8,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $andi = $this->buatSiswa('Andi Saputra', '0030000001');
        $mira = $this->buatSiswa('Mira Lestari', '0030000002');
        $zaki = $this->buatSiswa('Zaki Pratama', '0030000003');

        $this->tempatkan($tahun, $kelasA, $andi, null);
        $anggotaMira = $this->tempatkan($tahun, $kelasA, $mira, null);
        $this->tempatkan($tahun, $kelasA, $zaki, null);

        $anggotaMira->update([
            'kelas_id' => $kelasB->id,
            'nomor_absen' => null,
        ]);

        $this->assertUrutanKelas($kelasA, [
            'Andi Saputra' => 1,
            'Zaki Pratama' => 2,
        ]);
        $this->assertUrutanKelas($kelasB, [
            'Mira Lestari' => 1,
        ]);
    }

    private function buatKelas(string $nama): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027-'.$nama,
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => $nama,
            'tingkat' => 7,
            'kapasitas' => 32,
            'aktif' => true,
        ]);

        return [$tahun, $kelas];
    }

    private function buatSiswa(string $nama, string $nisn): Siswa
    {
        return Siswa::create([
            'nama_lengkap' => $nama,
            'nis' => fake()->unique()->numerify('26#####'),
            'nisn' => $nisn,
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
    }

    private function tempatkan(
        TahunPelajaran $tahun,
        Kelas $kelas,
        Siswa $siswa,
        ?int $nomorAbsen,
    ): AnggotaKelas {
        return AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => $nomorAbsen,
            'status_keanggotaan' => 'aktif',
        ]);
    }

    private function assertUrutanKelas(Kelas $kelas, array $urutan): void
    {
        $aktual = AnggotaKelas::query()
            ->with('siswa:id,nama_lengkap')
            ->where('kelas_id', $kelas->id)
            ->orderBy('nomor_absen')
            ->get()
            ->mapWithKeys(fn (AnggotaKelas $anggota) => [
                $anggota->siswa->nama_lengkap => $anggota->nomor_absen,
            ])
            ->all();

        $this->assertSame($urutan, $aktual);
    }
}
