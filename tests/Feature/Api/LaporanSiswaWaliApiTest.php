<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\PenugasanGuruWaliSiswa;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LaporanSiswaWaliApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guru_wali_hanya_melihat_laporan_siswa_dampingannya(): void
    {
        $data = $this->dataDasar();
        $laporanWali = $this->buatLaporan($data, $data['siswa_wali'], 'PB-WALI-001');
        $laporanLain = $this->buatLaporan($data, $data['siswa_lain'], 'PB-WALI-002');
        $token = $this->token($data['akun']);

        $this->getJson(route('api.v1.laporan-siswa-wali.index'))->assertUnauthorized();

        $response = $this->withToken($token)
            ->getJson(route('api.v1.laporan-siswa-wali.index', [
                'kata_kunci' => 'Andi',
                'kelas_id' => $data['kelas']->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.total', 1)
            ->assertJsonPath('data.paginasi.total', 1)
            ->assertJsonPath('data.items.0.id', $laporanWali->id)
            ->assertJsonPath('data.items.0.siswa.nama', 'Andi Siswa Wali')
            ->assertJsonPath('data.hak_akses.cakupan_luas', false)
            ->assertJsonPath('data.hak_akses.konteks_guru_wali', true)
            ->assertJsonCount(1, 'data.pilihan.kelas');
        $response->assertJsonMissing(['nomor_laporan' => $laporanLain->nomor_laporan]);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->withToken($token)
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'laporan-siswa-wali',
                'status' => 'tersedia',
                'rute' => '/laporan-siswa-wali',
            ])
            ->assertJsonMissing(['kode' => 'pemeriksaan-pengesahan']);

        $this->withToken($token)
            ->getJson(route('api.v1.pemeriksaan-pengesahan.index'))
            ->assertForbidden();

        $this->withToken($token)
            ->getJson(route('api.v1.laporkan-kejadian.referensi'))
            ->assertOk()
            ->assertJsonCount(2, 'data.siswa')
            ->assertJsonFragment(['nama' => 'Andi Siswa Wali'])
            ->assertJsonFragment(['nama' => 'Budi Bukan Siswa Wali']);
    }

    public function test_detail_dan_bukti_dibatasi_ke_siswa_dampingan_aktif(): void
    {
        Storage::fake('local');
        $data = $this->dataDasar();
        $laporanWali = $this->buatLaporan($data, $data['siswa_wali'], 'PB-WALI-011');
        $laporanLain = $this->buatLaporan($data, $data['siswa_lain'], 'PB-WALI-012');
        Storage::disk('local')->put('pembinaan/wali/bukti-wali.pdf', '%PDF-wali');
        Storage::disk('local')->put('pembinaan/lain/bukti-lain.pdf', '%PDF-lain');
        $buktiWali = $laporanWali->buktiLaporanPembinaanSiswa()->create([
            'jenis' => 'dokumen',
            'nama_file_asli' => 'bukti-wali.pdf',
            'lokasi_file' => 'pembinaan/wali/bukti-wali.pdf',
            'tipe_file' => 'application/pdf',
            'ukuran_file' => 9,
            'diunggah_oleh_pengguna_id' => $data['akun']->id,
            'diunggah_pada' => now(),
        ]);
        $buktiLain = $laporanLain->buktiLaporanPembinaanSiswa()->create([
            'jenis' => 'dokumen',
            'nama_file_asli' => 'bukti-lain.pdf',
            'lokasi_file' => 'pembinaan/lain/bukti-lain.pdf',
            'tipe_file' => 'application/pdf',
            'ukuran_file' => 9,
            'diunggah_oleh_pengguna_id' => $data['akun']->id,
            'diunggah_pada' => now(),
        ]);
        $token = $this->token($data['akun']);

        $this->withToken($token)
            ->getJson(route('api.v1.laporan-siswa-wali.show', $laporanWali))
            ->assertOk()
            ->assertJsonPath('data.laporan.id', $laporanWali->id)
            ->assertJsonPath('data.laporan.siswa.nama', 'Andi Siswa Wali');

        $this->withToken($token)
            ->getJson(route('api.v1.laporan-siswa-wali.show', $laporanLain))
            ->assertForbidden();
        $this->withToken($token)
            ->get(route('api.v1.laporan-siswa-wali.bukti', $buktiWali))
            ->assertOk()
            ->assertDownload('bukti-wali.pdf');
        $this->withToken($token)
            ->get(route('api.v1.laporan-siswa-wali.bukti', $buktiLain))
            ->assertForbidden();
    }

    public function test_akun_tanpa_peran_guru_wali_tidak_dapat_memakai_konteks_ini(): void
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();

        $this->withToken($this->token($administrator))
            ->getJson(route('api.v1.laporan-siswa-wali.index'))
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
            'nama' => 'VII.GW',
            'tingkat' => 7,
            'aktif' => true,
        ]);
        $siswaWali = $this->buatSiswa($tahun, $kelas, 'Andi Siswa Wali', '0099001001');
        $siswaLain = $this->buatSiswa($tahun, $kelas, 'Budi Bukan Siswa Wali', '0099001002');
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Guru Wali Laporan Native',
            'nip' => '198808082026081008',
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
        $akun->daftarPeran()->attach(Peran::where('kode', 'guru_wali')->firstOrFail());
        PenugasanGuruWaliSiswa::create([
            'siswa_id' => $siswaWali->id,
            'guru_wali_pegawai_id' => $pegawai->id,
            'tanggal_mulai' => '2026-07-15',
            'nomor_sk' => 'SK/GW/LAP/001',
            'aktif' => true,
        ]);

        return compact('tahun', 'kelas', 'siswaWali', 'siswaLain', 'pegawai', 'akun') + [
            'siswa_wali' => $siswaWali,
            'siswa_lain' => $siswaLain,
        ];
    }

    private function buatSiswa(TahunPelajaran $tahun, Kelas $kelas, string $nama, string $nisn): Siswa
    {
        $siswa = Siswa::create([
            'nama_lengkap' => $nama,
            'nis' => 'NIS-'.$nisn,
            'nisn' => $nisn,
            'jenis_kelamin' => 'L',
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'status_keanggotaan' => 'aktif',
        ]);

        return $siswa;
    }

    private function buatLaporan(array $data, Siswa $siswa, string $nomor): LaporanPembinaanSiswa
    {
        return LaporanPembinaanSiswa::create([
            'nomor_laporan' => $nomor,
            'jenis_laporan' => 'kejadian',
            'sumber_laporan' => 'manual',
            'tanggal_kejadian' => '2026-08-31',
            'waktu_kejadian' => '09:10',
            'tempat_kejadian' => 'Lingkungan sekolah',
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $data['kelas']->id,
            'pelapor_pegawai_id' => $data['pegawai']->id,
            'tingkat' => 'ringan',
            'status' => 'baru',
            'status_verifikasi' => 'diajukan',
            'total_poin' => 0,
            'kronologi' => 'Laporan untuk pemantauan khusus Guru Wali.',
            'dibuat_oleh_pengguna_id' => $data['akun']->id,
        ]);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Perangkat Laporan Siswa Wali', ['mobile'])->plainTextToken;
    }
}
