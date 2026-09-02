<?php

namespace Tests\Feature\Api;

use App\Models\AnggotaKelas;
use App\Models\KategoriPembinaanSiswa;
use App\Models\Kelas;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\PenugasanGuruWaliSiswa;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiswaWaliSayaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_guru_wali_melihat_ringkasan_filter_dan_menu_siswa_dampingannya(): void
    {
        $data = $this->dataDasar();
        TransaksiPoinSiswa::create([
            'siswa_id' => $data['siswa_tujuh']->id,
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kunci_sumber' => 'siswa-wali-native:poin:1',
            'jenis' => 'pelanggaran',
            'poin' => 12,
            'keterangan' => 'Poin resmi untuk pemantauan Guru Wali.',
            'tercatat_pada' => now(),
        ]);
        $token = $this->token($data['akun']);

        $this->getJson(route('api.v1.siswa-wali-saya.index'))->assertUnauthorized();
        $response = $this->withToken($token)
            ->getJson(route('api.v1.siswa-wali-saya.index', [
                'kata_kunci' => 'andi',
                'tingkat' => 7,
                'kelas_id' => $data['kelas_tujuh']->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.ringkasan.jumlah_siswa', 2)
            ->assertJsonPath('data.ringkasan.jumlah_kelas', 2)
            ->assertJsonPath('data.ringkasan.laki_laki', 1)
            ->assertJsonPath('data.ringkasan.perempuan', 1)
            ->assertJsonPath('data.ringkasan.memiliki_poin', 1)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $data['siswa_tujuh']->id)
            ->assertJsonPath('data.items.0.kelas.nama', 'VII.SW')
            ->assertJsonPath('data.items.0.total_poin', 12)
            ->assertJsonPath('data.items.0.tanggal_mulai_didampingi', '2026-07-15')
            ->assertJsonPath('data.tahun_pelajaran.nama', '2026/2027')
            ->assertJsonPath('data.filter.tingkat', 7)
            ->assertJsonPath('data.hak_akses.dapat_melihat_rekap_poin', true);
        $response->assertJsonMissing(['nama' => $data['siswa_lain']->nama_lengkap]);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $this->withToken($token)
            ->getJson(route('api.v1.menu'))
            ->assertOk()
            ->assertJsonFragment([
                'kode' => 'siswa-wali-saya',
                'status' => 'tersedia',
                'rute' => '/siswa-wali-saya',
            ]);

    }

    public function test_detail_memuat_identitas_kontak_dan_pemantauan_siswa_wali(): void
    {
        $data = $this->dataDasar();
        $siswa = $data['siswa_tujuh'];
        $siswa->update([
            'nik' => '1374010203040005',
            'tempat_lahir' => 'Padang Panjang',
            'tanggal_lahir' => '2013-05-04',
            'agama' => 'Islam',
            'nama_ayah' => 'Ayah Andi',
            'nomor_wa_ayah' => '081234567890',
            'nama_ibu' => 'Ibu Andi',
            'nomor_wa_ibu' => '081234567891',
            'kontak_absensi_utama' => 'ayah',
            'alamat' => 'Padang Panjang',
        ]);
        TransaksiPoinSiswa::create([
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kunci_sumber' => 'siswa-wali-native:poin:detail',
            'jenis' => 'pelanggaran',
            'poin' => 8,
            'keterangan' => 'Poin resmi detail siswa wali.',
            'tercatat_pada' => now(),
        ]);
        $laporan = LaporanPembinaanSiswa::create([
            'nomor_laporan' => 'PB-SW-NATIVE-001',
            'jenis_laporan' => 'pelanggaran',
            'tanggal_kejadian' => '2026-08-20',
            'siswa_id' => $siswa->id,
            'kategori_pembinaan_siswa_id' => KategoriPembinaanSiswa::firstOrFail()->id,
            'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $data['kelas_tujuh']->id,
            'anggota_kelas_id' => $data['anggota_tujuh']->id,
            'tingkat' => 'ringan',
            'status' => 'diproses',
            'status_verifikasi' => 'pemeriksaan_bk',
            'total_poin' => 8,
            'kronologi' => 'Laporan untuk pemantauan Guru Wali.',
        ]);

        $this->withToken($this->token($data['akun']))
            ->getJson(route('api.v1.siswa-wali-saya.show', $siswa))
            ->assertOk()
            ->assertJsonPath('data.siswa.id', $siswa->id)
            ->assertJsonPath('data.siswa.nik', '1374010203040005')
            ->assertJsonPath('data.siswa.orang_tua_wali.nama_ayah', 'Ayah Andi')
            ->assertJsonPath('data.siswa.orang_tua_wali.nomor_wa_ayah', '081234567890')
            ->assertJsonPath('data.siswa.alamat', 'Padang Panjang')
            ->assertJsonPath('data.kelas.nama', 'VII.SW')
            ->assertJsonPath('data.kelas.nomor_absen', 1)
            ->assertJsonPath('data.penugasan.nomor_sk', 'SK/GW/SW/001')
            ->assertJsonPath('data.ringkasan.total_poin', 8)
            ->assertJsonPath('data.ringkasan.jumlah_laporan', 1)
            ->assertJsonPath('data.laporan_terbaru.0.id', $laporan->id)
            ->assertJsonPath('data.laporan_terbaru.0.label_status', 'Pemeriksaan BK');
    }

    public function test_guru_wali_dilarang_membuka_siswa_di_luar_penugasan(): void
    {
        $data = $this->dataDasar();

        $this->withToken($this->token($data['akun']))
            ->getJson(route('api.v1.siswa-wali-saya.show', $data['siswa_lain']))
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
        $kelasTujuh = $this->buatKelas($tahun, 'VII.SW', 7);
        $kelasDelapan = $this->buatKelas($tahun, 'VIII.SW', 8);
        [$siswaTujuh, $anggotaTujuh] = $this->buatSiswa($tahun, $kelasTujuh, 'Andi Siswa Wali Native', '0077111001', 'L', 1);
        [$siswaDelapan] = $this->buatSiswa($tahun, $kelasDelapan, 'Bella Siswa Wali Native', '0088111002', 'P', 2);
        [$siswaLain] = $this->buatSiswa($tahun, $kelasTujuh, 'Citra Bukan Siswa Wali', '0077111003', 'P', 3);
        $pegawai = Pegawai::create([
            'nama_lengkap' => 'Guru Wali Native Saya',
            'nip' => '198909092026091009',
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
        foreach ([$siswaTujuh, $siswaDelapan] as $siswa) {
            PenugasanGuruWaliSiswa::create([
                'siswa_id' => $siswa->id,
                'guru_wali_pegawai_id' => $pegawai->id,
                'tanggal_mulai' => '2026-07-15',
                'nomor_sk' => 'SK/GW/SW/001',
                'catatan' => 'Pendampingan aktif Guru Wali.',
                'aktif' => true,
            ]);
        }

        return [
            'tahun' => $tahun,
            'kelas_tujuh' => $kelasTujuh,
            'kelas_delapan' => $kelasDelapan,
            'siswa_tujuh' => $siswaTujuh,
            'siswa_delapan' => $siswaDelapan,
            'siswa_lain' => $siswaLain,
            'anggota_tujuh' => $anggotaTujuh,
            'pegawai' => $pegawai,
            'akun' => $akun,
        ];
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

    private function buatSiswa(
        TahunPelajaran $tahun,
        Kelas $kelas,
        string $nama,
        string $nisn,
        string $jenisKelamin,
        int $nomorAbsen,
    ): array {
        $siswa = Siswa::create([
            'nama_lengkap' => $nama,
            'nis' => 'NIS-'.$nisn,
            'nisn' => $nisn,
            'jenis_kelamin' => $jenisKelamin,
            'aktif' => true,
        ]);
        $anggota = AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'nomor_absen' => $nomorAbsen,
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => $tahun->tanggal_mulai,
        ]);

        return [$siswa, $anggota];
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Perangkat Siswa Wali Saya', ['mobile'])->plainTextToken;
    }
}
