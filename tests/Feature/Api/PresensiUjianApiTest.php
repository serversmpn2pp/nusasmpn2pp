<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\JadwalUjianCbt;
use App\Models\JenisUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Models\KelasUjianCbt;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PesertaUjianCbt;
use App\Models\RuangKegiatanUjianCbt;
use App\Models\RuangUjianCbt;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\UjianCbt;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PresensiUjianApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_presensi_ujian_memerlukan_token_mobile(): void
    {
        $this->getJson(route('api.v1.presensi-ujian.index'))->assertUnauthorized();
    }

    public function test_administrator_dapat_scan_dan_mengoreksi_presensi_ruang(): void
    {
        Carbon::setTestNow('2026-09-06 07:15:00');

        try {
            $data = $this->fondasi();
            $token = $this->token($data['admin']);

            $index = $this->withToken($token)
                ->getJson(route('api.v1.presensi-ujian.index'))
                ->assertOk()
                ->assertJsonPath('data.ringkasan.jumlah_ruang', 2)
                ->assertJsonPath('data.ringkasan.jumlah_peserta', 2)
                ->assertJsonPath('data.ringkasan.jumlah_hadir', 0)
                ->assertJsonCount(2, 'data.ruang_hari_ini')
                ->assertJsonPath('data.ruang_hari_ini.0.kode', 'LAB-1');
            $this->assertStringContainsString('no-store', (string) $index->headers->get('Cache-Control'));

            $this->withToken($token)
                ->getJson(route('api.v1.presensi-ujian.show', $data['ruang_satu']))
                ->assertOk()
                ->assertJsonPath('data.ruang.nama', 'Labor Komputer 1')
                ->assertJsonPath('data.ruang.peran_saya', 'Pengelola CBT')
                ->assertJsonPath('data.ringkasan.peserta', 1)
                ->assertJsonPath('data.peserta.0.nama_lengkap', 'Siswa Presensi Satu')
                ->assertJsonPath('data.peserta.0.status', 'belum_absen');

            $this->withToken($token)
                ->postJson(route('api.v1.presensi-ujian.scan', $data['ruang_satu']), [
                    'isi_scan' => 'NISN: '.$data['siswa_satu']->nisn,
                ])
                ->assertOk()
                ->assertJsonPath('data.berhasil', true)
                ->assertJsonPath('data.baru', true)
                ->assertJsonPath('data.peserta.nomor_meja', 1)
                ->assertJsonPath('data.ringkasan.hadir', 1);

            $this->withToken($token)
                ->postJson(route('api.v1.presensi-ujian.scan', $data['ruang_satu']), [
                    'isi_scan' => $data['siswa_satu']->nisn,
                ])
                ->assertOk()
                ->assertJsonPath('data.berhasil', true)
                ->assertJsonPath('data.baru', false)
                ->assertJsonPath('data.status', 'sudah_hadir');

            $this->withToken($token)
                ->postJson(route('api.v1.presensi-ujian.scan', $data['ruang_satu']), [
                    'isi_scan' => $data['siswa_dua']->nisn,
                ])
                ->assertUnprocessable()
                ->assertJsonPath('data.status', 'salah_ruang')
                ->assertJsonPath('data.ruang_seharusnya', 'Labor Komputer 2');

            $this->withToken($token)
                ->patchJson(route('api.v1.presensi-ujian.manual', [
                    $data['ruang_satu'],
                    $data['peserta_satu'],
                ]), [
                    'status' => 'terlambat',
                    'catatan' => 'Datang setelah bel masuk.',
                ])
                ->assertOk()
                ->assertJsonPath('data.peserta.status', 'terlambat')
                ->assertJsonPath('data.detail.peserta.0.catatan', 'Datang setelah bel masuk.')
                ->assertJsonPath('data.detail.ringkasan.hadir', 1);

            $this->assertDatabaseHas('peserta_ujian_cbt', [
                'id' => $data['peserta_satu']->id,
                'status_kehadiran_ujian' => 'terlambat',
                'catatan_kehadiran_ujian' => 'Datang setelah bel masuk.',
                'absen_ujian_oleh_pengguna_id' => $data['admin']->id,
            ]);
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_guru_hanya_melihat_ruang_yang_diawasi(): void
    {
        $data = $this->fondasi();
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Guru Pengawas Presensi',
            'nip' => '198901012026090006',
            'jenis_kelamin' => 'P',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $akun = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip,
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
        $akun->daftarPeran()->attach(Peran::query()->where('kode', 'guru_mapel')->firstOrFail()->id);
        $data['ruang_satu']->update(['pengawas_utama_pegawai_id' => $pegawai->id]);
        $token = $this->token($akun);

        $this->withToken($token)
            ->getJson(route('api.v1.presensi-ujian.index'))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.jumlah_ruang', 1)
            ->assertJsonPath('data.ruang_hari_ini.0.id', $data['ruang_satu']->id);

        $this->withToken($token)
            ->getJson(route('api.v1.presensi-ujian.show', $data['ruang_dua']))
            ->assertForbidden();
    }

    private function fondasi(): array
    {
        $admin = Pengguna::query()->where('username', 'administrator')->firstOrFail();
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $jenis = JenisUjianCbt::query()->firstOrFail();
        $mapel = MataPelajaran::create([
            'kode' => 'MTK-PRESENSI-NATIVE',
            'nama' => 'Matematika',
            'tingkat' => 9,
            'kkm' => 75,
            'aktif' => true,
        ]);
        $kegiatan = KegiatanUjianCbt::create([
            'jenis_ujian_cbt_id' => $jenis->id,
            'tahun_pelajaran_id' => $tahun->id,
            'kode' => 'UT-PRESENSI-NATIVE',
            'nama' => 'Ujian Presensi Native',
            'semester' => 'ganjil',
            'tanggal_mulai' => now()->toDateString(),
            'tanggal_selesai' => now()->toDateString(),
            'status' => 'aktif',
            'dibuat_oleh_pengguna_id' => $admin->id,
        ]);
        $jadwal = JadwalUjianCbt::create([
            'kegiatan_ujian_cbt_id' => $kegiatan->id,
            'mata_pelajaran_id' => $mapel->id,
            'tanggal' => now()->toDateString(),
            'waktu_mulai' => '07:30',
            'waktu_selesai' => '09:00',
            'label_sesi' => 'Sesi Pagi',
            'tingkat' => 9,
            'urutan' => 1,
            'status' => 'siap',
        ]);
        $ujian = UjianCbt::create([
            'alur' => 'terpusat',
            'jenis_ujian_cbt_id' => $jenis->id,
            'tahun_pelajaran_id' => $tahun->id,
            'mata_pelajaran_id' => $mapel->id,
            'kode' => 'PAKET-PRESENSI-NATIVE',
            'nama' => 'Paket Matematika',
            'semester' => 'ganjil',
            'tingkat' => 9,
            'tanggal_mulai' => now()->startOfDay()->addHours(7),
            'tanggal_selesai' => now()->startOfDay()->addHours(9),
            'durasi_menit' => 90,
            'jumlah_soal' => 20,
            'status' => 'terjadwal',
        ]);
        $jadwal->update(['ujian_cbt_id' => $ujian->id]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'IX.A',
            'tingkat' => 9,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $kelasUjian = KelasUjianCbt::create([
            'ujian_cbt_id' => $ujian->id,
            'kelas_id' => $kelas->id,
        ]);

        $ruang = collect([1, 2])->mapWithKeys(function (int $nomor) use ($kegiatan, $jadwal, $ujian) {
            $ruangKegiatan = RuangKegiatanUjianCbt::create([
                'kegiatan_ujian_cbt_id' => $kegiatan->id,
                'kode' => 'LAB-'.$nomor,
                'nama' => 'Labor Komputer '.$nomor,
                'lokasi' => 'Lantai '.$nomor,
                'kapasitas' => 30,
                'urutan' => $nomor,
                'aktif' => true,
            ]);

            return [$nomor => RuangUjianCbt::create([
                'ujian_cbt_id' => $ujian->id,
                'jadwal_ujian_cbt_id' => $jadwal->id,
                'ruang_kegiatan_ujian_cbt_id' => $ruangKegiatan->id,
                'kode' => 'LAB-'.$nomor,
                'nama' => 'Labor Komputer '.$nomor,
                'lokasi' => 'Lantai '.$nomor,
                'kapasitas' => 30,
                'status_bukti' => 'belum_diunggah',
                'status' => 'siap',
            ])];
        });

        $siswa = collect([1, 2])->mapWithKeys(function (int $nomor) use ($tahun, $kelas, $kelasUjian, $ujian, $ruang) {
            $siswa = Siswa::create([
                'nama_lengkap' => 'Siswa Presensi '.($nomor === 1 ? 'Satu' : 'Dua'),
                'nis' => '260900'.$nomor,
                'nisn' => '009900900'.$nomor,
                'jenis_kelamin' => $nomor === 1 ? 'L' : 'P',
                'aktif' => true,
            ]);
            $anggota = AnggotaKelas::create([
                'tahun_pelajaran_id' => $tahun->id,
                'kelas_id' => $kelas->id,
                'siswa_id' => $siswa->id,
                'nomor_absen' => $nomor,
                'status_keanggotaan' => 'aktif',
            ]);
            $peserta = PesertaUjianCbt::create([
                'ujian_cbt_id' => $ujian->id,
                'kelas_ujian_cbt_id' => $kelasUjian->id,
                'ruang_ujian_cbt_id' => $ruang[$nomor]->id,
                'anggota_kelas_id' => $anggota->id,
                'nomor_peserta' => 'UT-2600'.$nomor,
                'nomor_meja' => 1,
                'status' => 'aktif',
                'status_kehadiran_ujian' => 'belum_absen',
                'menit_tersisa' => 90,
            ]);

            return [$nomor => ['siswa' => $siswa, 'peserta' => $peserta]];
        });

        return [
            'admin' => $admin,
            'ruang_satu' => $ruang[1],
            'ruang_dua' => $ruang[2],
            'siswa_satu' => $siswa[1]['siswa'],
            'siswa_dua' => $siswa[2]['siswa'],
            'peserta_satu' => $siswa[1]['peserta'],
        ];
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel Presensi Ujian', ['mobile'])->plainTextToken;
    }
}
