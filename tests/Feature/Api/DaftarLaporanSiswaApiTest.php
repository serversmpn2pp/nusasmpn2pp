<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DaftarLaporanSiswaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_melihat_ringkasan_filter_daftar_dan_detail_lengkap(): void
    {
        Storage::fake('local');
        $data = $this->dataDasar();
        $laporanPelapor = $this->buatLaporan($data, $data['akun'], 'PB-20260830-0001', 'diajukan');
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $laporanAdmin = $this->buatLaporan($data, $administrator, 'PB-20260830-0002', 'disahkan');
        $laporanPelapor->saksiLaporanPembinaanSiswa()->create([
            'jenis_saksi' => 'pegawai',
            'nama_saksi' => 'Saksi Guru Mobile',
            'pernyataan' => 'Melihat kejadian secara langsung.',
            'dibuat_oleh_pengguna_id' => $data['akun']->id,
        ]);
        Storage::disk('local')->put('pembinaan/1/bukti/bukti.pdf', '%PDF-uji');
        $bukti = $laporanPelapor->buktiLaporanPembinaanSiswa()->create([
            'jenis' => 'dokumen',
            'nama_file_asli' => 'bukti-kejadian.pdf',
            'lokasi_file' => 'pembinaan/1/bukti/bukti.pdf',
            'tipe_file' => 'application/pdf',
            'ukuran_file' => 2048,
            'keterangan' => 'Bukti koridor sekolah.',
            'diunggah_oleh_pengguna_id' => $data['akun']->id,
            'diunggah_pada' => now(),
        ]);
        $laporanPelapor->riwayatProsesPembinaanSiswa()->create([
            'kode_kegiatan' => 'laporan_dibuat',
            'judul' => 'Laporan dibuat',
            'keterangan' => 'Laporan siap diperiksa.',
            'status_sesudah' => 'diajukan',
            'pengguna_id' => $data['akun']->id,
            'terjadi_pada' => now(),
        ]);

        $token = $this->token($administrator);
        $response = $this->withToken($token)
            ->getJson(route('api.v1.laporan-siswa.index', [
                'kata_kunci' => 'Siswa Laporan Native',
                'jenis_laporan' => 'kejadian',
                'status_verifikasi' => 'diajukan',
            ]))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.total', 2)
            ->assertJsonPath('data.ringkasan.menunggu_bk', 1)
            ->assertJsonPath('data.ringkasan.disahkan', 1)
            ->assertJsonPath('data.paginasi.total', 1)
            ->assertJsonPath('data.items.0.nomor_laporan', 'PB-20260830-0001')
            ->assertJsonPath('data.items.0.siswa.nama', 'Siswa Laporan Native')
            ->assertJsonPath('data.items.0.jumlah_bukti', 1)
            ->assertJsonPath('data.hak_akses.cakupan_luas', true);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->withToken($token)
            ->getJson(route('api.v1.laporan-siswa.show', $laporanPelapor))
            ->assertOk()
            ->assertJsonPath('data.laporan.kronologi', 'Kronologi laporan siswa untuk aplikasi native.')
            ->assertJsonPath('data.laporan.pelapor.nama', 'Pegawai Pelapor Laporan Native')
            ->assertJsonPath('data.saksi.0.nama', 'Saksi Guru Mobile')
            ->assertJsonPath('data.bukti.0.nama_file', 'bukti-kejadian.pdf')
            ->assertJsonPath('data.linimasa.0.judul', 'Laporan dibuat');

        $this->withToken($token)
            ->get(route('api.v1.laporan-siswa.bukti', $bukti))
            ->assertOk()
            ->assertDownload('bukti-kejadian.pdf');

        $this->assertSame('PB-20260830-0002', $laporanAdmin->nomor_laporan);
    }

    public function test_pelapor_hanya_melihat_laporan_dalam_cakupannya(): void
    {
        $data = $this->dataDasar();
        $laporanSaya = $this->buatLaporan($data, $data['akun'], 'PB-20260830-0011', 'diajukan');
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $laporanLain = $this->buatLaporan($data, $administrator, 'PB-20260830-0012', 'diajukan');
        $token = $this->token($data['akun']);

        $this->withToken($token)
            ->getJson(route('api.v1.laporan-siswa.index'))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.total', 1)
            ->assertJsonPath('data.paginasi.total', 1)
            ->assertJsonPath('data.items.0.id', $laporanSaya->id)
            ->assertJsonPath('data.hak_akses.cakupan_luas', false);

        $this->withToken($token)
            ->getJson(route('api.v1.laporan-siswa.show', $laporanLain))
            ->assertForbidden();
    }

    public function test_endpoint_memerlukan_token_dan_izin_laporan(): void
    {
        $this->getJson(route('api.v1.laporan-siswa.index'))->assertUnauthorized();

        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Pegawai Tanpa Izin Daftar Laporan',
            'nip' => '198707072017071007',
            'aktif' => true,
        ]);
        $akun = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip,
            'kata_sandi' => 'KataSandi-Uji-2026',
            'wajib_ganti_kata_sandi' => false,
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);

        $this->withToken($this->token($akun))
            ->getJson(route('api.v1.laporan-siswa.index'))
            ->assertForbidden();
    }

    private function dataDasar(): array
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
            'aktif' => true,
        ]);
        $siswa = Siswa::create([
            'nama_lengkap' => 'Siswa Laporan Native',
            'nis' => 'LAP-001',
            'nisn' => '0088550001',
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'status_keanggotaan' => 'aktif',
        ]);
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Pegawai Pelapor Laporan Native',
            'nip' => '198606062016061006',
            'aktif' => true,
        ]);
        $akun = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $pegawai->nama_lengkap,
            'username' => $pegawai->nip,
            'kata_sandi' => 'KataSandi-Uji-2026',
            'wajib_ganti_kata_sandi' => false,
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
        ]);
        $akun->daftarPeran()->attach(Peran::where('kode', 'pegawai')->firstOrFail());

        return compact('tahun', 'kelas', 'siswa', 'pegawai', 'akun');
    }

    private function buatLaporan(array $data, Pengguna $pembuat, string $nomor, string $verifikasi): LaporanPembinaanSiswa
    {
        return LaporanPembinaanSiswa::create([
            'nomor_laporan' => $nomor,
            'jenis_laporan' => 'kejadian',
            'sumber_laporan' => 'manual',
            'tanggal_kejadian' => '2026-08-30',
            'waktu_kejadian' => '08:10',
            'tempat_kejadian' => 'Koridor sekolah',
            'siswa_id' => $data['siswa']->id,
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $data['kelas']->id,
            'pelapor_pegawai_id' => $pembuat->pegawai_id,
            'tingkat' => 'ringan',
            'status' => $verifikasi === 'disahkan' ? 'selesai' : 'baru',
            'status_verifikasi' => $verifikasi,
            'tahap_batas_proses' => $verifikasi === 'diajukan' ? 'pemeriksaan_bk' : null,
            'batas_proses_pada' => $verifikasi === 'diajukan' ? now()->addDays(2) : null,
            'total_poin' => 0,
            'kronologi' => 'Kronologi laporan siswa untuk aplikasi native.',
            'tindakan_awal' => 'Siswa diarahkan kembali ke kelas.',
            'dibuat_oleh_pengguna_id' => $pembuat->id,
        ]);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Perangkat Daftar Laporan Siswa', ['mobile'])->plainTextToken;
    }
}
