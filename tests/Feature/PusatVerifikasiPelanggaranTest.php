<?php

namespace Tests\Feature;

use App\Models\ButirPelanggaranLaporan;
use App\Models\JenisPelanggaranSiswa;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\VerifikasiBkPelanggaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PusatVerifikasiPelanggaranTest extends TestCase
{
    use RefreshDatabase;

    public function test_bk_hanya_melihat_antrean_pemeriksaan_fakta(): void
    {
        [$tahun, $siswa, $jenis] = $this->dataDasar();
        [$pegawaiBk, $akunBk] = $this->buatAkunPegawai('BK Penguji', '198001012010011001', 'bk');
        $menungguBk = $this->buatLaporan('PB-BK-001', 'diajukan', $tahun, $siswa, $jenis);
        $menungguPersetujuan = $this->buatLaporan('PB-SETUJU-001', 'menunggu_persetujuan', $tahun, $siswa, $jenis);
        DB::table('laporan_pembinaan_siswa')->where('id', $menungguBk->id)->update([
            'created_at' => now()->subDays(3),
            'updated_at' => now()->subDays(3),
        ]);

        $this->actingAs($akunBk)
            ->get(route('pusat-verifikasi-pelanggaran.index'))
            ->assertOk()
            ->assertSee($menungguBk->nomor_laporan)
            ->assertDontSee($menungguPersetujuan->nomor_laporan);

        $this->get(route('pusat-verifikasi-pelanggaran.index', ['antrean' => 'terlambat']))
            ->assertOk()
            ->assertSee($menungguBk->nomor_laporan)
            ->assertSee('3 hari diproses');
    }

    public function test_wali_kelas_hanya_melihat_persetujuan_kelasnya(): void
    {
        [$tahun, $siswa, $jenis] = $this->dataDasar();
        [$wali, $akunWali] = $this->buatAkunPegawai('Wali Kelas Penguji', '198101012011011002', 'wali_kelas');
        [$waliLain] = $this->buatAkunPegawai('Wali Kelas Lain', '198201012012011003', 'wali_kelas');
        $milikWali = $this->buatLaporan('PB-WALI-001', 'menunggu_persetujuan', $tahun, $siswa, $jenis, $wali);
        $milikOrangLain = $this->buatLaporan('PB-WALI-002', 'menunggu_persetujuan', $tahun, $siswa, $jenis, $waliLain);

        $this->actingAs($akunWali)
            ->get(route('pusat-verifikasi-pelanggaran.index'))
            ->assertOk()
            ->assertSee($milikWali->nomor_laporan)
            ->assertDontSee($milikOrangLain->nomor_laporan);
    }

    public function test_wakil_kesiswaan_melihat_konflik_dan_kebutuhan_pengganti(): void
    {
        [$tahun, $siswa, $jenis] = $this->dataDasar();
        [$wali] = $this->buatAkunPegawai('Wali Rangkap', '198301012013011004', 'wali_kelas');
        [, $akunWakil] = $this->buatAkunPegawai('Wakil Kesiswaan', '198401012014011005', 'wakil_pimpinan_kesiswaan');
        $konflik = $this->buatLaporan('PB-MUSY-001', 'perlu_musyawarah', $tahun, $siswa, $jenis, $wali);
        $pengganti = $this->buatLaporan('PB-GANTI-001', 'disetujui_sebagian', $tahun, $siswa, $jenis, $wali, $wali);

        $this->actingAs($akunWakil)
            ->get(route('pusat-verifikasi-pelanggaran.index', ['antrean' => 'musyawarah']))
            ->assertOk()
            ->assertSee($konflik->nomor_laporan)
            ->assertSee($pengganti->nomor_laporan);
    }

    public function test_urutan_verifikasi_dan_dua_pegawai_berbeda_ditegakkan(): void
    {
        [$tahun, $siswa, $jenis] = $this->dataDasar();
        [$pegawaiRangkap, $akunRangkap] = $this->buatAkunPegawai('Wali Rangkap Uji', '198501012015011006', 'wali_kelas');
        [$pegawaiWakil, $akunWakil] = $this->buatAkunPegawai('Wakil Pengganti Uji', '198601012016011007', 'wakil_pimpinan_kesiswaan');
        $laporan = $this->buatLaporan('PB-URUT-001', 'menunggu_persetujuan', $tahun, $siswa, $jenis, $pegawaiRangkap, $pegawaiRangkap);
        VerifikasiBkPelanggaran::create([
            'laporan_pembinaan_siswa_id' => $laporan->id,
            'pengguna_id' => Pengguna::where('username', 'administrator')->value('id'),
            'hasil' => 'terbukti',
            'catatan' => 'Fakta lengkap.',
            'diverifikasi_pada' => now(),
        ]);

        $this->actingAs($akunRangkap)->post(route('verifikasi-pelanggaran.persetujuan', $laporan), [
            'jenis_persetujuan' => 'wali_kelas',
            'keputusan' => 'setuju',
            'catatan' => 'Setuju sebagai wali kelas.',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('disetujui_sebagian', $laporan->fresh()->status_verifikasi);

        $this->post(route('verifikasi-pelanggaran.persetujuan', $laporan), [
            'jenis_persetujuan' => 'guru_wali',
            'keputusan' => 'setuju',
            'catatan' => 'Mencoba menyetujui untuk kedua kali.',
        ])->assertSessionHasErrors('keputusan');
        $this->assertSame(1, $laporan->persetujuanPelanggaran()->count());

        $this->actingAs($akunWakil)->post(route('verifikasi-pelanggaran.persetujuan', $laporan), [
            'jenis_persetujuan' => 'wakil_kesiswaan',
            'keputusan' => 'setuju',
            'catatan' => 'Menyetujui sebagai pegawai pengganti.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('disahkan', $laporan->fresh()->status_verifikasi);
        $this->assertDatabaseHas('transaksi_poin_siswa', ['kunci_sumber' => 'pelanggaran:' . $laporan->id]);
        $this->assertDatabaseHas('notifikasi_pengguna', [
            'pengguna_id' => $akunWakil->id,
            'judul' => 'Penyetuju pengganti diperlukan',
        ]);

        $this->actingAs(Pengguna::where('username', 'administrator')->firstOrFail())
            ->post(route('verifikasi-pelanggaran.bk', $laporan), [
                'hasil' => 'terbukti',
                'catatan' => 'Pemeriksaan terlambat.',
            ])->assertStatus(422);
    }

    private function dataDasar(): array
    {
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $siswa = Siswa::create(['nama_lengkap' => 'Siswa Antrean Uji', 'nisn' => '0088776601', 'aktif' => true]);
        $jenis = JenisPelanggaranSiswa::where('aktif', true)->firstOrFail();

        return [$tahun, $siswa, $jenis];
    }

    private function buatAkunPegawai(string $nama, string $nip, string $kodePeran): array
    {
        $pegawai = Pegawai::create(['nama_lengkap' => $nama, 'nip' => $nip, 'aktif' => true]);
        $pengguna = Pengguna::create([
            'pegawai_id' => $pegawai->id,
            'nama' => $nama,
            'username' => $nip,
            'kata_sandi' => 'KataSandi-Uji-2026',
            'peran' => 'pegawai',
            'aktif' => true,
        ]);
        $pengguna->daftarPeran()->attach(Peran::where('kode', $kodePeran)->firstOrFail());

        return [$pegawai, $pengguna];
    }

    private function buatLaporan(
        string $nomor,
        string $status,
        TahunPelajaran $tahun,
        Siswa $siswa,
        JenisPelanggaranSiswa $jenis,
        ?Pegawai $waliKelas = null,
        ?Pegawai $guruWali = null,
    ): LaporanPembinaanSiswa {
        $administrator = Pengguna::where('username', 'administrator')->firstOrFail();
        $laporan = LaporanPembinaanSiswa::create([
            'nomor_laporan' => $nomor,
            'jenis_laporan' => 'pelanggaran',
            'tanggal_kejadian' => now()->toDateString(),
            'siswa_id' => $siswa->id,
            'kategori_pembinaan_siswa_id' => $jenis->kategori_pembinaan_siswa_id,
            'tahun_pelajaran_id' => $tahun->id,
            'wali_kelas_pegawai_id' => $waliKelas?->id,
            'guru_wali_pegawai_id' => $guruWali?->id,
            'tingkat' => $jenis->tingkat,
            'status' => 'baru',
            'status_verifikasi' => $status,
            'total_poin' => $jenis->poin,
            'kronologi' => 'Kronologi untuk pengujian pusat verifikasi.',
            'dibuat_oleh_pengguna_id' => $administrator->id,
        ]);
        ButirPelanggaranLaporan::create([
            'laporan_pembinaan_siswa_id' => $laporan->id,
            'jenis_pelanggaran_siswa_id' => $jenis->id,
            'kode_pelanggaran' => $jenis->kode,
            'nama_pelanggaran' => $jenis->nama,
            'tingkat' => $jenis->tingkat,
            'poin' => $jenis->poin,
        ]);

        return $laporan;
    }
}
