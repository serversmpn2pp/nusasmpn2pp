<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\ButirPelanggaranLaporan;
use App\Models\JenisPelanggaranSiswa;
use App\Models\KategoriPembinaanSiswa;
use App\Models\Kelas;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pengguna;
use App\Models\RiwayatProsesPembinaanSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;
use App\Services\AkunOrangTuaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PembinaanPoinAnakApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_orang_tua_dapat_memilih_anak_dan_hanya_melihat_laporan_serta_poin_yang_terhubung(): void
    {
        [$tahun, $kelas] = $this->buatTahunDanKelas();
        $anakUtama = $this->buatSiswa($tahun, $kelas, 'Anak Utama Mobile', '0011224401', 1);
        $anakKedua = $this->buatSiswa($tahun, $kelas, 'Anak Kedua Mobile', '0011224402', 2);
        $siswaLain = $this->buatSiswa($tahun, $kelas, 'Siswa Lain Mobile', '0011224403', 3);
        $akun = app(AkunOrangTuaService::class)->buat($anakUtama);
        $akun->update(['wajib_ganti_kata_sandi' => false]);
        $akun->orangTuaWali->siswa()->attach($anakKedua->id, [
            'hubungan' => 'anak',
            'utama' => false,
        ]);

        $laporanUtama = $this->buatLaporan($anakUtama, $tahun, $kelas, 'LP-ORT-001');
        $laporanKedua = $this->buatLaporan($anakKedua, $tahun, $kelas, 'LP-ORT-002');
        $this->buatLaporan($siswaLain, $tahun, $kelas, 'LP-ORT-TERTUTUP');
        $this->buatPoin($anakUtama, $tahun, 'poin-anak', 'pelanggaran', 15, 'Pelanggaran resmi anak', $laporanUtama);
        $this->buatPoin($anakUtama, $tahun, 'reward-anak', 'pengurangan', -5, 'Reward kegiatan positif');
        $this->buatPoin($siswaLain, $tahun, 'poin-lain', 'pelanggaran', 99, 'Poin siswa lain');

        $this->withToken($this->token($akun))
            ->getJson(route('api.v1.pembinaan-poin-anak.index'))
            ->assertOk()
            ->assertJsonCount(2, 'data.pilihan_siswa')
            ->assertJsonPath('data.siswa.id', $anakUtama->id)
            ->assertJsonPath('data.kelas.nama', $kelas->nama)
            ->assertJsonPath('data.ringkasan.laporan', 1)
            ->assertJsonPath('data.ringkasan.poin_pelanggaran', 15)
            ->assertJsonPath('data.ringkasan.pengurangan', 5)
            ->assertJsonPath('data.ringkasan.saldo', 10)
            ->assertJsonPath('data.laporan.0.id', $laporanUtama->id)
            ->assertJsonMissing(['nomor_laporan' => 'LP-ORT-002'])
            ->assertJsonMissing(['nomor_laporan' => 'LP-ORT-TERTUTUP']);

        $this->withToken($this->token($akun))
            ->getJson(route('api.v1.pembinaan-poin-anak.index', [
                'siswa_id' => $anakKedua->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.siswa.id', $anakKedua->id)
            ->assertJsonPath('data.laporan.0.id', $laporanKedua->id)
            ->assertJsonMissing(['nomor_laporan' => 'LP-ORT-001']);

        $this->withToken($this->token($akun))
            ->getJson(route('api.v1.pembinaan-poin-anak.index', [
                'tab' => 'poin',
            ]))
            ->assertOk()
            ->assertJsonCount(2, 'data.riwayat_poin')
            ->assertJsonFragment(['keterangan' => 'Pelanggaran resmi anak', 'poin' => 15])
            ->assertJsonFragment(['keterangan' => 'Reward kegiatan positif', 'poin' => -5])
            ->assertJsonMissing(['keterangan' => 'Poin siswa lain']);

        $this->withToken($this->token($akun))
            ->getJson(route('api.v1.pembinaan-poin-anak.index', [
                'siswa_id' => $siswaLain->id,
            ]))
            ->assertForbidden();
    }

    public function test_detail_hanya_memuat_keputusan_resmi_dan_linimasa_publik_anak(): void
    {
        [$tahun, $kelas] = $this->buatTahunDanKelas();
        $anak = $this->buatSiswa($tahun, $kelas, 'Anak Detail Mobile', '0011224410', 1);
        $siswaLain = $this->buatSiswa($tahun, $kelas, 'Siswa Detail Lain', '0011224411', 2);
        $akun = app(AkunOrangTuaService::class)->buat($anak);
        $akun->update(['wajib_ganti_kata_sandi' => false]);
        $laporan = $this->buatLaporan($anak, $tahun, $kelas, 'LP-ORT-010', [
            'status' => 'selesai',
            'status_verifikasi' => 'disahkan',
            'total_poin' => 12,
            'kronologi' => 'Kronologi yang boleh diketahui orang tua.',
            'catatan_rahasia' => 'Rahasia internal BK.',
        ]);
        $laporanLain = $this->buatLaporan($siswaLain, $tahun, $kelas, 'LP-ORT-011');
        $jenisPelanggaran = JenisPelanggaranSiswa::create([
            'kategori_pembinaan_siswa_id' => KategoriPembinaanSiswa::query()
                ->where('nama', 'Kedisiplinan')
                ->value('id'),
            'kode' => 'P-ORT-01',
            'nama' => 'Pelanggaran resmi untuk orang tua',
            'tingkat' => 'ringan',
            'poin' => 12,
            'urutan' => 1,
            'aktif' => true,
        ]);
        ButirPelanggaranLaporan::create([
            'laporan_pembinaan_siswa_id' => $laporan->id,
            'jenis_pelanggaran_siswa_id' => $jenisPelanggaran->id,
            'kode_pelanggaran' => 'P-ORT-01',
            'nama_pelanggaran' => 'Pelanggaran resmi untuk orang tua',
            'tingkat' => 'ringan',
            'poin' => 12,
            'catatan' => 'Catatan internal butir.',
        ]);
        RiwayatProsesPembinaanSiswa::create([
            'laporan_pembinaan_siswa_id' => $laporan->id,
            'kode_kegiatan' => 'poin_disahkan_wakil',
            'judul' => 'Judul internal tidak ditampilkan',
            'keterangan' => 'Keterangan internal tidak ditampilkan',
            'terjadi_pada' => now(),
        ]);

        $this->withToken($this->token($akun))
            ->getJson(route('api.v1.pembinaan-poin-anak.show', $laporan))
            ->assertOk()
            ->assertJsonPath('data.laporan.kronologi', 'Kronologi yang boleh diketahui orang tua.')
            ->assertJsonPath('data.keputusan.total_poin_resmi', 12)
            ->assertJsonPath('data.keputusan.butir_pelanggaran.0.nama', 'Pelanggaran resmi untuk orang tua')
            ->assertJsonFragment(['judul' => 'Poin disahkan Wakil Kesiswaan'])
            ->assertJsonPath('data.privasi', 'Rincian pemeriksaan internal dikelola oleh sekolah dan tidak ditampilkan pada akun orang tua.')
            ->assertJsonMissing(['catatan_rahasia' => 'Rahasia internal BK.'])
            ->assertJsonMissing(['catatan' => 'Catatan internal butir.'])
            ->assertJsonMissing(['judul' => 'Judul internal tidak ditampilkan'])
            ->assertJsonMissing(['keterangan' => 'Keterangan internal tidak ditampilkan']);

        $this->withToken($this->token($akun))
            ->getJson(route('api.v1.pembinaan-poin-anak.show', $laporanLain))
            ->assertNotFound();
    }

    public function test_menu_hanya_diberikan_kepada_orang_tua(): void
    {
        [$tahun, $kelas] = $this->buatTahunDanKelas();
        $anak = $this->buatSiswa($tahun, $kelas, 'Anak Menu Mobile', '0011224420', 1);
        $akun = app(AkunOrangTuaService::class)->buat($anak);
        $akun->update(['wajib_ganti_kata_sandi' => false]);
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->withToken($this->token($akun))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'pembinaan-poin-anak',
                'label' => 'Pembinaan & Poin Anak Saya',
                'rute' => '/pembinaan-poin-anak',
            ])
            ->assertJsonMissing(['kode' => 'progress-kasus-saya']);

        $this->app['auth']->forgetGuards();
        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonMissing(['kode' => 'pembinaan-poin-anak']);

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.pembinaan-poin-anak.index'))
            ->assertForbidden();
    }

    private function buatTahunDanKelas(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2040/2041',
            'tanggal_mulai' => '2040-07-01',
            'tanggal_selesai' => '2041-06-30',
            'aktif' => true,
        ]);
        $kelas = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.Parent',
            'tingkat' => 7,
            'kapasitas' => 32,
            'aktif' => true,
        ]);

        return [$tahun, $kelas];
    }

    private function buatSiswa(
        TahunPelajaran $tahun,
        Kelas $kelas,
        string $nama,
        string $nisn,
        int $nomorAbsen,
    ): Siswa {
        $siswa = Siswa::create([
            'nama_lengkap' => $nama,
            'nis' => '40'.$nomorAbsen,
            'nisn' => $nisn,
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => $nomorAbsen,
            'status_keanggotaan' => 'aktif',
        ]);

        return $siswa;
    }

    private function buatLaporan(
        Siswa $siswa,
        TahunPelajaran $tahun,
        Kelas $kelas,
        string $nomor,
        array $tambahan = [],
    ): LaporanPembinaanSiswa {
        return LaporanPembinaanSiswa::create(array_merge([
            'nomor_laporan' => $nomor,
            'jenis_laporan' => 'kejadian',
            'sumber_laporan' => 'manual',
            'tanggal_kejadian' => '2040-08-01',
            'tempat_kejadian' => 'Lingkungan sekolah',
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'tingkat' => 'ringan',
            'status' => 'diproses',
            'status_verifikasi' => 'pemeriksaan_bk',
            'total_poin' => 0,
            'kronologi' => 'Kejadian sedang ditangani sekolah.',
        ], $tambahan));
    }

    private function buatPoin(
        Siswa $siswa,
        TahunPelajaran $tahun,
        string $kunci,
        string $jenis,
        int $poin,
        string $keterangan,
        ?LaporanPembinaanSiswa $laporan = null,
    ): void {
        TransaksiPoinSiswa::create([
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'laporan_pembinaan_siswa_id' => $laporan?->id,
            'kunci_sumber' => $kunci,
            'jenis' => $jenis,
            'poin' => $poin,
            'keterangan' => $keterangan,
            'tercatat_pada' => now(),
        ]);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
