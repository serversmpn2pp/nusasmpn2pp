<?php

namespace Tests\Feature\Api;

use App\Models\GuruMataPelajaran;
use App\Models\Izin;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JadwalMengajarSayaApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_endpoint_memerlukan_token_dan_izin_jadwal_pribadi(): void
    {
        $this->getJson(route('api.v1.jadwal-mengajar-saya'))->assertUnauthorized();

        $pegawai = $this->buatPegawai('Guru Tanpa Izin', '198101012020011001');
        $pengguna = $this->buatPengguna('Guru Tanpa Izin', 'guru.tanpa.izin', $pegawai);

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.jadwal-mengajar-saya'))
            ->assertForbidden();
    }

    public function test_jadwal_hanya_memuat_penugasan_guru_yang_sedang_login(): void
    {
        $data = $this->buatDataJadwal();

        $this->withToken($this->token($data['pengguna']))
            ->getJson(route('api.v1.jadwal-mengajar-saya'))
            ->assertOk()
            ->assertJsonPath('data.terhubung_pegawai', true)
            ->assertJsonPath('data.pegawai.id', $data['guru']->id)
            ->assertJsonPath('data.tahun_terpilih_id', $data['tahun_aktif']->id)
            ->assertJsonPath('data.ringkasan.jam_mengajar', 1)
            ->assertJsonPath('data.ringkasan.kelas', 1)
            ->assertJsonPath('data.ringkasan.mata_pelajaran', 1)
            ->assertJsonCount(6, 'data.hari')
            ->assertJsonPath('data.hari.0.kode', 'senin')
            ->assertJsonCount(1, 'data.hari.0.jadwal')
            ->assertJsonPath('data.hari.0.jadwal.0.mata_pelajaran.nama', 'Matematika Pribadi')
            ->assertJsonPath('data.hari.0.jadwal.0.kelas.nama', 'VIII.PRIBADI')
            ->assertJsonMissing(['nama' => 'IPA Guru Lain']);
    }

    public function test_tahun_pelajaran_dapat_diganti_tanpa_membuka_jadwal_guru_lain(): void
    {
        $data = $this->buatDataJadwal();

        $this->withToken($this->token($data['pengguna']))
            ->getJson(route('api.v1.jadwal-mengajar-saya', [
                'tahun_pelajaran_id' => $data['tahun_lama']->id,
            ]))
            ->assertOk()
            ->assertJsonPath('data.tahun_terpilih_id', $data['tahun_lama']->id)
            ->assertJsonPath('data.ringkasan.jam_mengajar', 1)
            ->assertJsonPath('data.hari.1.kode', 'selasa')
            ->assertJsonPath('data.hari.1.jadwal.0.mata_pelajaran.nama', 'Bahasa Indonesia Lama');
    }

    public function test_akun_tanpa_relasi_pegawai_mendapat_penjelasan_dan_jadwal_kosong(): void
    {
        $pengguna = $this->buatPenggunaDenganIzin(
            'Akun Belum Terhubung',
            'akun.belum.terhubung',
        );

        $this->withToken($this->token($pengguna))
            ->getJson(route('api.v1.jadwal-mengajar-saya'))
            ->assertOk()
            ->assertJsonPath('data.terhubung_pegawai', false)
            ->assertJsonPath('data.pegawai', null)
            ->assertJsonPath('data.ringkasan.jam_mengajar', 0)
            ->assertJsonPath(
                'data.peringatan.0',
                'Akun ini belum terhubung dengan data pegawai. Hubungi administrator.',
            );
    }

    private function buatDataJadwal(): array
    {
        $tahunAktif = TahunPelajaran::create([
            'nama' => '2026/2027 Jadwal Pribadi',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $tahunLama = TahunPelajaran::create([
            'nama' => '2025/2026 Jadwal Pribadi',
            'tanggal_mulai' => '2025-07-01',
            'tanggal_selesai' => '2026-06-30',
            'aktif' => false,
        ]);
        $guru = $this->buatPegawai('Guru Jadwal Pribadi', '198101012020011002');
        $guruLain = $this->buatPegawai('Guru Jadwal Lain', '198101012020011003');
        $pengguna = $this->buatPenggunaDenganIzin(
            'Guru Jadwal Pribadi',
            'guru.jadwal.pribadi',
            $guru,
        );
        $kelas = $this->buatKelas($tahunAktif, 'VIII.PRIBADI', 8);
        $kelasLain = $this->buatKelas($tahunAktif, 'VIII.LAIN', 8);
        $kelasLama = $this->buatKelas($tahunLama, 'VII.PRIBADI', 7);
        $matematika = $this->buatMataPelajaran('MTK-PRIBADI', 'Matematika Pribadi');
        $ipa = $this->buatMataPelajaran('IPA-LAIN', 'IPA Guru Lain');
        $bahasa = $this->buatMataPelajaran('BIND-LAMA', 'Bahasa Indonesia Lama');
        $senin = $this->buatJam('senin', 1, '07:30:00', '08:15:00');
        $selasa = $this->buatJam('selasa', 1, '07:30:00', '08:15:00');

        $penugasan = $this->buatPenugasan($tahunAktif, $kelas, $matematika, $guru);
        $penugasanLain = $this->buatPenugasan($tahunAktif, $kelasLain, $ipa, $guruLain);
        $penugasanLama = $this->buatPenugasan($tahunLama, $kelasLama, $bahasa, $guru);
        $this->buatJadwal($tahunAktif, $kelas, $senin, $penugasan);
        $this->buatJadwal($tahunAktif, $kelasLain, $senin, $penugasanLain);
        $this->buatJadwal($tahunLama, $kelasLama, $selasa, $penugasanLama);

        return [
            'tahun_aktif' => $tahunAktif,
            'tahun_lama' => $tahunLama,
            'guru' => $guru,
            'pengguna' => $pengguna,
        ];
    }

    private function buatPegawai(string $nama, string $nip): Pegawai
    {
        return Pegawai::create([
            'nama_lengkap' => $nama,
            'nip' => $nip,
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
    }

    private function buatPengguna(
        string $nama,
        string $username,
        ?Pegawai $pegawai = null,
    ): Pengguna {
        return Pengguna::create([
            'pegawai_id' => $pegawai?->id,
            'nama' => $nama,
            'username' => $username,
            'kata_sandi' => 'RahasiaNusa123',
            'peran' => 'pegawai',
            'aktif' => true,
            'akun_sistem' => false,
            'wajib_ganti_kata_sandi' => false,
        ]);
    }

    private function buatPenggunaDenganIzin(
        string $nama,
        string $username,
        ?Pegawai $pegawai = null,
    ): Pengguna {
        $peran = Peran::create([
            'nama' => 'Guru Jadwal Pribadi '.$username,
            'kode' => 'guru_jadwal_'.str($username)->slug('_'),
            'aktif' => true,
            'sistem' => false,
        ]);
        $peran->izin()->attach(Izin::where('kode', 'jadwal.pribadi')->firstOrFail());
        $pengguna = $this->buatPengguna($nama, $username, $pegawai);
        $pengguna->daftarPeran()->attach($peran);

        return $pengguna;
    }

    private function buatKelas(TahunPelajaran $tahun, string $nama, int $tingkat): Kelas
    {
        return Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => $nama,
            'tingkat' => $tingkat,
            'aktif' => true,
        ]);
    }

    private function buatMataPelajaran(string $kode, string $nama): MataPelajaran
    {
        return MataPelajaran::create([
            'kode' => $kode,
            'nama' => $nama,
            'kelompok' => 'Wajib',
            'aktif' => true,
        ]);
    }

    private function buatJam(
        string $hari,
        int $nomor,
        string $mulai,
        string $selesai,
    ): JamPelajaran {
        return JamPelajaran::create([
            'hari' => $hari,
            'nomor_jam' => $nomor,
            'label' => 'Jam '.$nomor,
            'jam_mulai' => $mulai,
            'jam_selesai' => $selesai,
            'jenis' => 'pelajaran',
            'aktif' => true,
        ]);
    }

    private function buatPenugasan(
        TahunPelajaran $tahun,
        Kelas $kelas,
        MataPelajaran $mataPelajaran,
        Pegawai $guru,
    ): GuruMataPelajaran {
        return GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'mata_pelajaran_id' => $mataPelajaran->id,
            'pegawai_id' => $guru->id,
            'jenis_penugasan' => 'pengampu',
            'aktif' => true,
        ]);
    }

    private function buatJadwal(
        TahunPelajaran $tahun,
        Kelas $kelas,
        JamPelajaran $jam,
        GuruMataPelajaran $penugasan,
    ): JadwalPelajaran {
        return JadwalPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'hari' => $jam->hari,
            'jam_pelajaran_id' => $jam->id,
            'guru_mata_pelajaran_id' => $penugasan->id,
            'aktif' => true,
        ]);
    }

    private function token(Pengguna $pengguna): string
    {
        return $pengguna->createToken('Pixel 7 Emulator', ['mobile'])->plainTextToken;
    }
}
