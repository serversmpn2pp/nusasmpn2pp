<?php

namespace Tests\Feature;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\PenguranganPoinSiswa;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PenguranganPoinSiswaTest extends TestCase
{
    use RefreshDatabase;

    public function test_halaman_hanya_menampilkan_siswa_dengan_saldo_poin_dan_dapat_difilter_per_kelas(): void
    {
        [$administrator, $tahun, $kelasA, $kelasB] = $this->buatDataDasar();
        $siswaPoinA = $this->buatSiswaDalamKelas($tahun, $kelasA, 'Siswa Poin Kelas A', '0011000001');
        $siswaNolA = $this->buatSiswaDalamKelas($tahun, $kelasA, 'Siswa Nol Kelas A', '0011000002');
        $siswaPoinB = $this->buatSiswaDalamKelas($tahun, $kelasB, 'Siswa Poin Kelas B', '0011000003');
        $siswaTanpaPoin = $this->buatSiswaDalamKelas($tahun, $kelasA, 'Siswa Tanpa Poin', '0011000004');

        $this->catatPoin($siswaPoinA, $tahun, 25, 'poin:a');
        $this->catatPoin($siswaNolA, $tahun, 10, 'poin:nol:masuk');
        $this->catatPoin($siswaNolA, $tahun, -10, 'poin:nol:keluar');
        $this->catatPoin($siswaPoinB, $tahun, 15, 'poin:b');

        $this->actingAs($administrator)
            ->get(route('pengurangan-poin-siswa.index'))
            ->assertOk()
            ->assertSee('Siswa Poin Kelas A')
            ->assertSee('Siswa Poin Kelas B')
            ->assertDontSee('Siswa Nol Kelas A')
            ->assertDontSee('Siswa Tanpa Poin')
            ->assertDontSee('name="tahun_pelajaran_id"', false)
            ->assertSee('25 poin');

        $this->get(route('pengurangan-poin-siswa.index', ['kelas_id' => $kelasA->id]))
            ->assertOk()
            ->assertSee('Siswa Poin Kelas A')
            ->assertDontSee('Siswa Poin Kelas B');
    }

    public function test_pengajuan_otomatis_memakai_tahun_aktif_dan_menolak_siswa_tanpa_saldo(): void
    {
        [$administrator, $tahun, $kelasA] = $this->buatDataDasar();
        $siswaPoin = $this->buatSiswaDalamKelas($tahun, $kelasA, 'Siswa Saldo Positif', '0011000011');
        $siswaTanpaPoin = $this->buatSiswaDalamKelas($tahun, $kelasA, 'Siswa Saldo Kosong', '0011000012');
        $this->catatPoin($siswaPoin, $tahun, 20, 'poin:positif');

        $this->actingAs($administrator)
            ->post(route('pengurangan-poin-siswa.store'), [
                'siswa_id' => $siswaPoin->id,
                'tahun_pelajaran_id' => 999999,
                'tanggal_kegiatan' => '2026-08-06',
                'jenis_kegiatan' => 'Teladan disiplin',
                'poin_pengurangan' => 10,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('pengurangan_poin_siswa', [
            'siswa_id' => $siswaPoin->id,
            'tahun_pelajaran_id' => $tahun->id,
            'poin_pengurangan' => 10,
        ]);

        $this->from(route('pengurangan-poin-siswa.index'))
            ->post(route('pengurangan-poin-siswa.store'), [
                'siswa_id' => $siswaTanpaPoin->id,
                'tanggal_kegiatan' => '2026-08-06',
                'jenis_kegiatan' => 'Teladan disiplin',
                'poin_pengurangan' => 10,
            ])
            ->assertRedirect(route('pengurangan-poin-siswa.index'))
            ->assertSessionHasErrors('siswa_id');
    }

    public function test_wakil_kesiswaan_mendapat_notifikasi_saat_reward_diajukan(): void
    {
        [$administrator, $tahun, $kelasA] = $this->buatDataDasar();
        $siswa = $this->buatSiswaDalamKelas($tahun, $kelasA, 'Siswa Reward Notifikasi', '0011000021');
        $this->catatPoin($siswa, $tahun, 20, 'poin:notifikasi');

        $pegawaiWakil = Pegawai::create([
            'nama_lengkap' => 'Wakil Kesiswaan Penerima',
            'nip' => '197801012006041001',
            'aktif' => true,
        ]);
        $akunWakil = Pengguna::create([
            'pegawai_id' => $pegawaiWakil->id,
            'nama' => $pegawaiWakil->nama_lengkap,
            'username' => $pegawaiWakil->nip,
            'kata_sandi' => 'KataSandi-Uji-2026',
            'peran' => 'pegawai',
            'aktif' => true,
        ]);
        $akunWakil->daftarPeran()->attach(Peran::where('kode', 'wakil_pimpinan_kesiswaan')->firstOrFail());

        $this->actingAs($administrator)
            ->post(route('pengurangan-poin-siswa.store'), [
                'siswa_id' => $siswa->id,
                'tanggal_kegiatan' => '2026-08-06',
                'jenis_kegiatan' => 'Teladan disiplin',
                'poin_pengurangan' => 10,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $penguranganId = (int) PenguranganPoinSiswa::query()->value('id');
        $this->assertDatabaseHas('notifikasi_pengguna', [
            'pengguna_id' => $akunWakil->id,
            'jenis' => 'peringatan',
            'judul' => 'Pengajuan reward menunggu persetujuan',
            'tautan' => route('pengurangan-poin-siswa.index', ['status' => 'diajukan'], false),
            'kunci_unik' => "pengurangan-poin-diajukan:{$penguranganId}",
        ]);
        $this->assertDatabaseMissing('notifikasi_pengguna', [
            'pengguna_id' => $administrator->id,
            'kunci_unik' => "pengurangan-poin-diajukan:{$penguranganId}",
        ]);
    }

    private function buatDataDasar(): array
    {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $kelasA = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.A',
            'tingkat' => 7,
            'kapasitas' => 32,
            'aktif' => true,
        ]);
        $kelasB = Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => 'VII.B',
            'tingkat' => 7,
            'kapasitas' => 32,
            'aktif' => true,
        ]);

        return [$administrator, $tahun, $kelasA, $kelasB];
    }

    private function buatSiswaDalamKelas(TahunPelajaran $tahun, Kelas $kelas, string $nama, string $nisn): Siswa
    {
        $siswa = Siswa::create([
            'nama_lengkap' => $nama,
            'nisn' => $nisn,
            'aktif' => true,
        ]);
        AnggotaKelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $kelas->id,
            'siswa_id' => $siswa->id,
            'status_keanggotaan' => 'aktif',
            'tanggal_masuk' => $tahun->tanggal_mulai,
        ]);

        return $siswa;
    }

    private function catatPoin(Siswa $siswa, TahunPelajaran $tahun, int $poin, string $kunci): void
    {
        TransaksiPoinSiswa::create([
            'siswa_id' => $siswa->id,
            'tahun_pelajaran_id' => $tahun->id,
            'kunci_sumber' => $kunci,
            'jenis' => $poin > 0 ? 'pelanggaran' : 'pengurangan',
            'poin' => $poin,
            'keterangan' => 'Data uji saldo poin.',
            'tercatat_pada' => now(),
        ]);
    }
}
