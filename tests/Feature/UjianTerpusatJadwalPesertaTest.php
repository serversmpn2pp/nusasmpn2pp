<?php

namespace Tests\Feature;

use App\Models\JadwalUjianCbt;
use App\Models\JenisUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Models\KelompokPesertaKegiatanUjianCbt;
use App\Models\MataPelajaran;
use App\Models\Pengguna;
use App\Models\RuangKegiatanUjianCbt;
use App\Models\SesiKegiatanUjianCbt;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use PDO;
use Tests\TestCase;

class UjianTerpusatJadwalPesertaTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('Driver pdo_sqlite belum aktif pada PHP lokal.');
        }

        $this->artisan('migrate:fresh');
    }

    public function test_peserta_dibagi_berdasarkan_kelas_nama_dan_kapasitas_ruang(): void
    {
        [$admin, $tahun, $kegiatan, $sesi, $ruang] = $this->buatFondasi();
        $kelasA = $this->buatKelas($tahun, 'VII.A', 7);
        $kelasB = $this->buatKelas($tahun, 'VII.B', 7);
        $this->buatSiswa($tahun, $kelasA, ['Citra', 'Aulia', 'Bella'], 7000);
        $this->buatSiswa($tahun, $kelasB, ['Damar', 'Eka'], 7100);

        $this->actingAs($admin)
            ->post(route('ujian-terpusat.peserta.atur', $kegiatan), [
                'tingkat' => 7,
                'sesi_kegiatan_ujian_cbt_id' => $sesi->id,
                'kelas' => [$kelasA->id, $kelasB->id],
                'ruang' => [$ruang[0]->id, $ruang[1]->id],
            ])
            ->assertRedirect(route('ujian-terpusat.pelaksanaan.index', [$kegiatan, 'tahap' => 5]));

        $kelompok = KelompokPesertaKegiatanUjianCbt::query()->firstOrFail();
        $this->assertSame(0, $kelompok->jumlah_peserta);
        $this->assertDatabaseCount('penempatan_peserta_ujian_cbt', 0);

        $this->actingAs($admin)
            ->post(route('ujian-terpusat.peserta.bangkitkan', [$kegiatan, $kelompok]))
            ->assertRedirect(route('ujian-terpusat.pelaksanaan.index', [$kegiatan, 'tahap' => 6]));

        $kelompok->refresh();
        $penempatan = $kelompok->penempatanPesertaUjianCbt()
            ->with('anggotaKelas.siswa')
            ->orderBy('id')
            ->get();

        $this->assertSame(5, $kelompok->jumlah_peserta);
        $this->assertSame(5, $kelompok->total_kapasitas);
        $this->assertSame(['Aulia', 'Bella', 'Citra', 'Damar', 'Eka'], $penempatan->pluck('anggotaKelas.siswa.nama_lengkap')->all());
        $this->assertSame([$ruang[0]->id, $ruang[0]->id, $ruang[0]->id, $ruang[1]->id, $ruang[1]->id], $penempatan->pluck('ruang_kegiatan_ujian_cbt_id')->all());
        $this->assertSame([1, 2, 3, 1, 2], $penempatan->pluck('nomor_meja')->all());
        $this->assertSame([
            'SAS-2627-01-S01-R01-M001',
            'SAS-2627-01-S01-R01-M002',
            'SAS-2627-01-S01-R01-M003',
            'SAS-2627-01-S01-R02-M001',
            'SAS-2627-01-S01-R02-M002',
        ], $penempatan->pluck('kode_meja')->all());

        $this->actingAs($admin)
            ->get(route('ujian-terpusat.peserta.show', [$kegiatan, $kelompok]))
            ->assertOk()
            ->assertSee('Daftar peserta ujian')
            ->assertSee('Susunan siswa tingkat 7')
            ->assertSeeText('Kode meja')
            ->assertSee('SAS-2627-01-S01-R01-M001')
            ->assertDontSeeText('Nomor peserta')
            ->assertDontSee('UT-2026-001-T7-001')
            ->assertSee('Aulia')
            ->assertSee('VII.B');

        $this->actingAs($admin)
            ->get(route('ujian-terpusat.peserta.label-meja', [$kegiatan, $kelompok, $ruang[0]]))
            ->assertOk()
            ->assertSeeText('8 label per lembar A4')
            ->assertSeeText('SMP NEGERI 2 PADANG PANJANG')
            ->assertSee('images/kartu-pelajar/logo-smpn2pp.png')
            ->assertSee('SAS-2627-01-S01-R01-M001')
            ->assertSee('Aulia')
            ->assertSee('9000007001')
            ->assertDontSee('UT-2026-001-T7-001');

        $this->actingAs($admin)
            ->get(route('ujian-terpusat.pelaksanaan.index', [$kegiatan, 'tahap' => 5]))
            ->assertOk()
            ->assertSeeText('Penetapan kelas, sesi, dan ruang')
            ->assertDontSeeText('Pembagian peserta otomatis');

        $this->actingAs($admin)
            ->get(route('ujian-terpusat.pelaksanaan.index', [$kegiatan, 'tahap' => 6]))
            ->assertOk()
            ->assertSeeText('Pembagian peserta otomatis')
            ->assertSeeText('Cetak label R01')
            ->assertSeeText('Lanjut ke Jadwal Ujian');

        $kegiatan->delete();
        $this->assertDatabaseMissing('kelompok_peserta_kegiatan_ujian_cbt', ['id' => $kelompok->id]);
        $this->assertDatabaseCount('penempatan_peserta_ujian_cbt', 0);
    }

    public function test_ruang_tidak_dapat_dipakai_dua_tingkat_dalam_sesi_yang_sama(): void
    {
        [$admin, $tahun, $kegiatan, $sesi, $ruang] = $this->buatFondasi();
        $kelas7 = $this->buatKelas($tahun, 'VII.A', 7);
        $kelas8 = $this->buatKelas($tahun, 'VIII.A', 8);
        $this->buatSiswa($tahun, $kelas7, ['Siswa Tujuh'], 7200);
        $this->buatSiswa($tahun, $kelas8, ['Siswa Delapan'], 7300);

        $this->actingAs($admin)->post(route('ujian-terpusat.peserta.atur', $kegiatan), [
            'tingkat' => 7,
            'sesi_kegiatan_ujian_cbt_id' => $sesi->id,
            'kelas' => [$kelas7->id],
            'ruang' => [$ruang[0]->id],
        ]);

        $this->actingAs($admin)
            ->from(route('ujian-terpusat.pelaksanaan.index', [$kegiatan, 'tahap' => 5]))
            ->post(route('ujian-terpusat.peserta.atur', $kegiatan), [
                'tingkat' => 8,
                'sesi_kegiatan_ujian_cbt_id' => $sesi->id,
                'kelas' => [$kelas8->id],
                'ruang' => [$ruang[0]->id],
            ])
            ->assertRedirect(route('ujian-terpusat.pelaksanaan.index', [$kegiatan, 'tahap' => 5]))
            ->assertSessionHasErrors('ruang');
    }

    public function test_jadwal_dapat_dibuat_sekaligus_untuk_beberapa_tingkat(): void
    {
        [$admin, $tahun, $kegiatan, $sesi, $ruang] = $this->buatFondasi();
        $kelas7 = $this->buatKelas($tahun, 'VII.A', 7);
        $kelas8 = $this->buatKelas($tahun, 'VIII.A', 8);
        $this->buatSiswa($tahun, $kelas7, ['Siswa Tujuh'], 7400);
        $this->buatSiswa($tahun, $kelas8, ['Siswa Delapan'], 7500);
        $mapel = MataPelajaran::create([
            'kode' => 'BIND',
            'nama' => 'Bahasa Indonesia',
            'kkm' => 78,
            'aktif' => true,
        ]);

        foreach ([[7, $kelas7, $ruang[0]], [8, $kelas8, $ruang[2]]] as [$tingkat, $kelas, $ruangTingkat]) {
            $this->actingAs($admin)->post(route('ujian-terpusat.peserta.atur', $kegiatan), [
                'tingkat' => $tingkat,
                'sesi_kegiatan_ujian_cbt_id' => $sesi->id,
                'kelas' => [$kelas->id],
                'ruang' => [$ruangTingkat->id],
            ]);

            $kelompok = KelompokPesertaKegiatanUjianCbt::query()
                ->where('kegiatan_ujian_cbt_id', $kegiatan->id)
                ->where('tingkat', $tingkat)
                ->firstOrFail();

            $this->actingAs($admin)->post(route('ujian-terpusat.peserta.bangkitkan', [$kegiatan, $kelompok]));
        }

        $this->actingAs($admin)
            ->post(route('ujian-terpusat.jadwal.store', $kegiatan), [
                'tanggal' => '2026-12-01',
                'mata_pelajaran_id' => $mapel->id,
                'waktu_mulai' => '07:00',
                'waktu_selesai' => '08:30',
                'tingkat' => [7, 8],
                'keterangan' => 'Hari pertama',
            ])
            ->assertRedirect(route('ujian-terpusat.pelaksanaan.index', [$kegiatan, 'tahap' => 7]));

        $jadwal = JadwalUjianCbt::query()->orderBy('tingkat')->get();
        $this->assertCount(2, $jadwal);
        $this->assertSame([7, 8], $jadwal->pluck('tingkat')->all());
        $this->assertSame([$sesi->id, $sesi->id], $jadwal->pluck('sesi_kegiatan_ujian_cbt_id')->all());
        $this->assertSame(['07:00', '07:00'], $jadwal->pluck('waktu_mulai')->map(fn ($waktu) => substr($waktu, 0, 5))->all());
        $this->assertSame(['08:30', '08:30'], $jadwal->pluck('waktu_selesai')->map(fn ($waktu) => substr($waktu, 0, 5))->all());
        $this->assertSame(90, $jadwal[0]->durasiMenit());
        $this->assertSame([1, 2], $jadwal->pluck('urutan')->all());
        $this->assertSame(1, $jadwal[0]->kelas()->count());
        $this->assertSame(1, $jadwal[1]->kelas()->count());

        $this->actingAs($admin)
            ->get(route('ujian-terpusat.pelaksanaan.index', [$kegiatan, 'tahap' => 7]))
            ->assertOk()
            ->assertSee('Bahasa Indonesia')
            ->assertSee('Ruang 1')
            ->assertSee('Ruang 3');
    }

    public function test_sesi_tidak_memerlukan_jam_dan_jam_jadwal_tidak_mengikuti_sesi_lama(): void
    {
        [$admin, , $kegiatan, $sesi] = $this->buatFondasi();

        $this->actingAs($admin)
            ->post(route('ujian-terpusat.sesi.store', $kegiatan), [
                'nama' => 'Sesi Kedua',
                'aktif' => true,
            ])
            ->assertRedirect();

        $sesiKedua = SesiKegiatanUjianCbt::query()
            ->where('kegiatan_ujian_cbt_id', $kegiatan->id)
            ->where('nama', 'Sesi Kedua')
            ->firstOrFail();
        $this->assertNull($sesiKedua->waktu_mulai);
        $this->assertNull($sesiKedua->waktu_selesai);

        $this->actingAs($admin)
            ->put(route('ujian-terpusat.sesi.update', [$kegiatan, $sesi]), [
                'nama' => 'Gelombang Pertama',
                'aktif' => true,
            ])
            ->assertRedirect();

        $sesi->refresh();
        $this->assertSame('Gelombang Pertama', $sesi->nama);
        $this->assertSame('07:30', substr($sesi->waktu_mulai, 0, 5));
        $this->assertSame('09:30', substr($sesi->waktu_selesai, 0, 5));
    }

    public function test_tingkat_yang_sama_dapat_memiliki_beberapa_mapel_pada_hari_yang_sama_tanpa_bentrok(): void
    {
        [$admin, $tahun, $kegiatan, $sesi, $ruang] = $this->buatFondasi();
        $kelas8 = $this->buatKelas($tahun, 'VIII.A', 8);
        $kelas9 = $this->buatKelas($tahun, 'IX.A', 9);
        $this->buatSiswa($tahun, $kelas8, ['Siswa Delapan'], 7600);
        $this->buatSiswa($tahun, $kelas9, ['Siswa Sembilan'], 7700);
        $bahasaIndonesia = MataPelajaran::create([
            'kode' => 'BIND-HARIAN',
            'nama' => 'Bahasa Indonesia',
            'kkm' => 78,
            'aktif' => true,
        ]);
        $pendidikanAgama = MataPelajaran::create([
            'kode' => 'PAI-HARIAN',
            'nama' => 'Pendidikan Agama Islam',
            'kkm' => 78,
            'aktif' => true,
        ]);

        foreach ([[8, $kelas8, $ruang[0]], [9, $kelas9, $ruang[2]]] as [$tingkat, $kelas, $ruangTingkat]) {
            $this->actingAs($admin)->post(route('ujian-terpusat.peserta.atur', $kegiatan), [
                'tingkat' => $tingkat,
                'sesi_kegiatan_ujian_cbt_id' => $sesi->id,
                'kelas' => [$kelas->id],
                'ruang' => [$ruangTingkat->id],
            ]);
            $kelompok = KelompokPesertaKegiatanUjianCbt::query()
                ->where('kegiatan_ujian_cbt_id', $kegiatan->id)
                ->where('tingkat', $tingkat)
                ->firstOrFail();
            $this->actingAs($admin)->post(route('ujian-terpusat.peserta.bangkitkan', [$kegiatan, $kelompok]));
        }

        $this->actingAs($admin)
            ->post(route('ujian-terpusat.jadwal.store', $kegiatan), [
                'tanggal' => '2026-12-01',
                'mata_pelajaran_id' => $bahasaIndonesia->id,
                'waktu_mulai' => '07:00',
                'waktu_selesai' => '08:00',
                'tingkat' => [8, 9],
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('ujian-terpusat.jadwal.store', $kegiatan), [
                'tanggal' => '2026-12-01',
                'mata_pelajaran_id' => $pendidikanAgama->id,
                'waktu_mulai' => '08:15',
                'waktu_selesai' => '09:15',
                'tingkat' => [8, 9],
            ])
            ->assertRedirect();

        $this->assertDatabaseCount('jadwal_ujian_cbt', 4);
        foreach ([8, 9] as $tingkat) {
            $this->assertDatabaseHas('jadwal_ujian_cbt', [
                'kegiatan_ujian_cbt_id' => $kegiatan->id,
                'mata_pelajaran_id' => $bahasaIndonesia->id,
                'tingkat' => $tingkat,
                'waktu_mulai' => '07:00',
                'waktu_selesai' => '08:00',
            ]);
            $this->assertDatabaseHas('jadwal_ujian_cbt', [
                'kegiatan_ujian_cbt_id' => $kegiatan->id,
                'mata_pelajaran_id' => $pendidikanAgama->id,
                'tingkat' => $tingkat,
                'waktu_mulai' => '08:15',
                'waktu_selesai' => '09:15',
            ]);
        }

        $this->actingAs($admin)
            ->from(route('ujian-terpusat.pelaksanaan.index', [$kegiatan, 'tahap' => 7]))
            ->post(route('ujian-terpusat.jadwal.store', $kegiatan), [
                'tanggal' => '2026-12-01',
                'mata_pelajaran_id' => $bahasaIndonesia->id,
                'waktu_mulai' => '08:30',
                'waktu_selesai' => '09:30',
                'tingkat' => [8, 9],
            ])
            ->assertSessionHasErrors('waktu_mulai');

        $this->assertDatabaseCount('jadwal_ujian_cbt', 4);
    }

    public function test_ruang_dapat_dipakai_sesi_berikutnya_setelah_jadwal_sebelumnya_selesai(): void
    {
        [$admin, $tahun, $kegiatan, $sesiPertama, $ruang] = $this->buatFondasi();
        $sesiKedua = SesiKegiatanUjianCbt::create([
            'kegiatan_ujian_cbt_id' => $kegiatan->id,
            'kode' => 'S02',
            'nama' => 'Sesi Kedua',
            'urutan' => 2,
            'aktif' => true,
        ]);
        $mapel = MataPelajaran::create([
            'kode' => 'MTK-JUMAT',
            'nama' => 'Matematika Jumat',
            'kkm' => 78,
            'aktif' => true,
        ]);

        foreach ([7, 8, 9] as $tingkat) {
            $kelas = $this->buatKelas($tahun, "Kelas {$tingkat}", $tingkat);
            $this->buatSiswa($tahun, $kelas, ["Siswa {$tingkat}"], 8000 + $tingkat);
            $sesi = $tingkat === 7 ? $sesiKedua : $sesiPertama;
            $ruangTingkat = $tingkat === 9 ? $ruang[1] : $ruang[0];

            $this->actingAs($admin)->post(route('ujian-terpusat.peserta.atur', $kegiatan), [
                'tingkat' => $tingkat,
                'sesi_kegiatan_ujian_cbt_id' => $sesi->id,
                'kelas' => [$kelas->id],
                'ruang' => [$ruangTingkat->id],
            ]);
            $kelompok = KelompokPesertaKegiatanUjianCbt::query()
                ->where('kegiatan_ujian_cbt_id', $kegiatan->id)
                ->where('tingkat', $tingkat)
                ->firstOrFail();
            $this->actingAs($admin)->post(route('ujian-terpusat.peserta.bangkitkan', [$kegiatan, $kelompok]));
        }

        $this->actingAs($admin)
            ->post(route('ujian-terpusat.jadwal.store', $kegiatan), [
                'tanggal' => '2026-12-04',
                'mata_pelajaran_id' => $mapel->id,
                'waktu_mulai' => '07:00',
                'waktu_selesai' => '08:30',
                'tingkat' => [8, 9],
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->from(route('ujian-terpusat.pelaksanaan.index', [$kegiatan, 'tahap' => 7]))
            ->post(route('ujian-terpusat.jadwal.store', $kegiatan), [
                'tanggal' => '2026-12-04',
                'mata_pelajaran_id' => $mapel->id,
                'waktu_mulai' => '08:00',
                'waktu_selesai' => '09:30',
                'tingkat' => [7],
            ])
            ->assertSessionHasErrors('waktu_mulai');

        $this->actingAs($admin)
            ->post(route('ujian-terpusat.jadwal.store', $kegiatan), [
                'tanggal' => '2026-12-04',
                'mata_pelajaran_id' => $mapel->id,
                'waktu_mulai' => '08:45',
                'waktu_selesai' => '10:15',
                'tingkat' => [7],
            ])
            ->assertRedirect();

        $jadwal = JadwalUjianCbt::query()->orderBy('tingkat')->get();
        $this->assertCount(3, $jadwal);
        $this->assertSame('08:45', substr($jadwal->firstWhere('tingkat', 7)->waktu_mulai, 0, 5));
        $this->assertSame('10:15', substr($jadwal->firstWhere('tingkat', 7)->waktu_selesai, 0, 5));
        $this->assertSame(90, $jadwal->firstWhere('tingkat', 7)->durasiMenit());
        $this->assertSame('07:00', substr($jadwal->firstWhere('tingkat', 8)->waktu_mulai, 0, 5));
        $this->assertSame('07:00', substr($jadwal->firstWhere('tingkat', 9)->waktu_mulai, 0, 5));
    }

    private function buatFondasi(): array
    {
        $admin = Pengguna::create([
            'nama' => 'Administrator Uji',
            'username' => 'administrator-uji-jadwal',
            'kata_sandi' => 'rahasia123',
            'wajib_ganti_kata_sandi' => false,
            'peran' => 'administrator',
            'aktif' => true,
            'akun_sistem' => true,
        ]);
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $jenis = JenisUjianCbt::query()->where('kode', 'SAS')->firstOrFail();
        $kegiatan = KegiatanUjianCbt::create([
            'jenis_ujian_cbt_id' => $jenis->id,
            'tahun_pelajaran_id' => $tahun->id,
            'kode' => 'UT-2026-001',
            'nama' => 'SAS Ganjil',
            'semester' => 'ganjil',
            'tanggal_mulai' => '2026-12-01',
            'tanggal_selesai' => '2026-12-08',
            'status' => 'draft',
            'dibuat_oleh_pengguna_id' => $admin->id,
        ]);
        $sesi = SesiKegiatanUjianCbt::create([
            'kegiatan_ujian_cbt_id' => $kegiatan->id,
            'kode' => 'S01',
            'nama' => 'Sesi Pagi',
            'waktu_mulai' => '07:30',
            'waktu_selesai' => '09:30',
            'urutan' => 1,
            'aktif' => true,
        ]);
        $ruang = collect([
            ['R01', 'Ruang 1', 3],
            ['R02', 'Ruang 2', 2],
            ['R03', 'Ruang 3', 2],
        ])->map(fn ($item, $index) => RuangKegiatanUjianCbt::create([
            'kegiatan_ujian_cbt_id' => $kegiatan->id,
            'kode' => $item[0],
            'nama' => $item[1],
            'kapasitas' => $item[2],
            'urutan' => $index + 1,
            'aktif' => true,
        ]));

        return [$admin, $tahun, $kegiatan, $sesi, $ruang];
    }

    private function buatKelas(TahunPelajaran $tahun, string $nama, int $tingkat): Kelas
    {
        return Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => $nama,
            'tingkat' => $tingkat,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
    }

    private function buatSiswa(TahunPelajaran $tahun, Kelas $kelas, array $nama, int $awal): void
    {
        foreach ($nama as $index => $namaSiswa) {
            $nomor = $awal + $index;
            $siswa = Siswa::create([
                'nama_lengkap' => $namaSiswa,
                'nis' => 'N'.$nomor,
                'nisn' => '900000'.$nomor,
                'jenis_kelamin' => $index % 2 ? 'P' : 'L',
                'aktif' => true,
            ]);
            $kelas->anggotaKelas()->create([
                'tahun_pelajaran_id' => $tahun->id,
                'siswa_id' => $siswa->id,
                'nomor_absen' => $index + 1,
                'status_keanggotaan' => 'aktif',
                'tanggal_masuk' => '2026-07-01',
            ]);
        }
    }
}
