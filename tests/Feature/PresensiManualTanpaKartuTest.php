<?php

namespace Tests\Feature;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\JadwalPiketGuru;
use App\Models\Kelas;
use App\Models\MataPelajaran;
use App\Models\NotifikasiPengguna;
use App\Models\OrangTuaWali;
use App\Models\Pegawai;
use App\Models\PengaturanAbsensi;
use App\Models\PengaturanPoinKeterlambatan;
use App\Models\PengecualianPresensiSiswa;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;
use App\Services\Absensi\KoreksiPresensiSiswaService;
use App\Services\Absensi\ProsesScanAbsensi;
use App\Services\Mobile\TujuanNotifikasiMobileService;
use App\Services\Notifikasi\IsiPushNotifikasiService;
use App\Services\Pembinaan\PoinPresensiOtomatisService;
use App\Services\Piket\CatatKehadiranSiswaPiketService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

class PresensiManualTanpaKartuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-05 06:55:00');
        Http::fake();
        Queue::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_piket_memberi_minimal_lima_menit_dan_poin_sekali_meski_scan_otomatis_nonaktif(): void
    {
        $d = $this->data();
        $service = app(CatatKehadiranSiswaPiketService::class);
        $a = $service->catat($d['guru'], $d['anggota'], 'hadir', 'Dikonfirmasi petugas.', 'tidak_membawa_kartu');
        $this->assertSame('06:55:00', $a->jam_masuk);
        $this->assertSame(5, $a->menit_terlambat);
        $this->assertSame(15, $a->poin_keterlambatan_terhitung);
        Carbon::setTestNow('2026-11-05 08:30:00');
        $service->catat($d['guru'], $d['anggota'], 'hadir', 'Dikonfirmasi petugas.', 'tidak_membawa_kartu');
        app(PoinPresensiOtomatisService::class)->sinkronkanAbsensi($a->fresh());
        $this->assertSame('06:55:00', $a->fresh()->jam_masuk);
        $this->assertDatabaseCount('laporan_pembinaan_siswa', 1);
        $this->assertSame(15, (int) TransaksiPoinSiswa::sum('poin'));
        $this->assertDatabaseCount('transaksi_poin_siswa', 1);
        $this->assertSame(2, NotifikasiPengguna::where('kunci_unik', 'like', 'presensi-manual-%')->count());
    }

    public function test_keterlambatan_sebenarnya_yang_lebih_besar_tetap_dihitung_dan_poin_mengikuti_pengaturan(): void
    {
        $d = $this->data();
        $d['pengaturan']->update(['poin_terlambat' => 20]);
        Carbon::setTestNow('2026-11-05 07:18:01');
        $a = app(CatatKehadiranSiswaPiketService::class)->catat($d['guru'], $d['anggota'], 'hadir', 'Kartu hilang.', 'kartu_hilang');
        $this->assertSame('07:18:01', $a->jam_masuk);
        $this->assertSame(19, $a->menit_terlambat);
        $this->assertSame(20, $a->poin_keterlambatan_terhitung);
        $this->assertStringContainsString('20 poin', $a->catatanKehadiran());
        $this->assertStringContainsString('Kartu pelajar hilang', $a->catatanKehadiran());
    }

    public function test_koreksi_admin_dari_api_dan_web_memakai_aturan_sama_tanpa_poin_ganda(): void
    {
        $d = $this->data();
        $payload = $this->payload() + ['alasan_hadir_manual' => 'kartu_hilang', 'sanksi_manual_menit' => 0, 'poin_keterlambatan_terhitung' => 0];
        $this->withToken($d['admin']->createToken('Tes presensi')->plainTextToken)
            ->patchJson(route('api.v1.rekap-presensi-siswa.update', $d['anggota']), $payload)->assertOk();
        $this->actingAs($d['admin'])->put(route('rekap-absensi-harian.koreksi.update', $d['anggota']), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();
        $a = AbsensiSiswa::firstOrFail();
        $this->assertSame(5, $a->menit_terlambat);
        $this->assertSame(15, $a->poin_keterlambatan_terhitung);
        $this->assertSame('kartu_hilang', $a->alasan_hadir_manual);
        $this->assertDatabaseCount('transaksi_poin_siswa', 1);
    }

    public function test_perubahan_menjadi_sakit_membatalkan_poin_dan_membersihkan_penanda_manual(): void
    {
        $d = $this->data();
        $service = app(CatatKehadiranSiswaPiketService::class);
        $a = $service->catat($d['guru'], $d['anggota'], 'hadir', 'Konfirmasi awal.', 'kartu_hilang');
        $service->catat($d['guru'], $d['anggota'], 'sakit', 'Ternyata tidak hadir; surat sakit diterima.');
        $a->refresh();
        $this->assertNull($a->jam_masuk);
        $this->assertNull($a->alasan_hadir_manual);
        $this->assertSame(0, $a->sanksi_manual_menit);
        $this->assertSame(0, $a->poin_keterlambatan_terhitung);
        $this->assertSame(0, (int) TransaksiPoinSiswa::sum('poin'));
        $this->assertNull($a->keteranganSanksiManual());
    }

    public function test_koreksi_scan_asli_tidak_terkena_sanksi_manual_dan_riwayat_lama_tidak_diberi_poin_baru(): void
    {
        $d = $this->data();
        $hasil = app(ProsesScanAbsensi::class)->proses($d['siswa']->nisn, now(), 'masuk');
        $this->assertTrue($hasil['berhasil']);
        $service = app(KoreksiPresensiSiswaService::class);
        $service->koreksi($d['admin'], $d['anggota'], $this->payload());
        $service->koreksi($d['admin'], $d['anggota'], $this->payload());
        $a = AbsensiSiswa::firstOrFail();
        $this->assertSame(0, $a->sanksi_manual_menit);
        $this->assertSame(0, $a->menit_terlambat);
        $this->assertDatabaseCount('transaksi_poin_siswa', 0);
        $a->update(['sumber' => 'guru_piket', 'jam_masuk' => null, 'status_masuk' => 'manual']);
        app(PoinPresensiOtomatisService::class)->sinkronkanAbsensi($a->fresh());
        $this->assertSame(0, $a->fresh()->sanksi_manual_menit);
        $this->assertDatabaseCount('transaksi_poin_siswa', 0);
    }

    public function test_pengecualian_presensi_dan_poin_nol_tidak_mengaku_mendapat_lima_belas_poin(): void
    {
        $d = $this->data();
        PengecualianPresensiSiswa::create(['tahun_pelajaran_id' => $d['tahun']->id, 'tanggal_mulai' => '2026-11-05',
            'tanggal_selesai' => '2026-11-05', 'jenis' => 'pjj', 'alasan' => 'PJJ bencana asap', 'aktif' => true,
            'kunci_pratinjau' => (string) Str::uuid(), 'dampak_pratinjau' => []]);
        $a = app(CatatKehadiranSiswaPiketService::class)->catat($d['guru'], $d['anggota'], 'hadir', 'Dikonfirmasi.', 'kartu_hilang');
        $this->assertSame(0, $a->poin_keterlambatan_terhitung);
        $this->assertStringNotContainsString('mendapat 15 poin', $a->catatanKehadiran());
        $this->assertDatabaseCount('transaksi_poin_siswa', 0);
    }

    public function test_alasan_dan_poin_tampil_untuk_siswa_dan_orang_tua_terhubung_saja(): void
    {
        $d = $this->data();
        $a = app(CatatKehadiranSiswaPiketService::class)->catat($d['guru'], $d['anggota'], 'hadir', 'Catatan privat petugas.', 'kartu_hilang');
        foreach ([$d['akunSiswa'], $d['orangTua']] as $akun) {
            $this->withToken($akun->createToken('Tes kehadiran')->plainTextToken)
                ->getJson(route('api.v1.kehadiran-saya', ['siswa_id' => $d['siswa']->id, 'bulan' => '2026-11']))
                ->assertOk()->assertJsonPath('data.hari_ini.menit_terlambat', 5)
                ->assertJsonPath('data.riwayat.0.poin_keterlambatan', 15)
                ->assertJsonPath('data.hari_ini.keterangan_sanksi_manual', $a->keteranganSanksiManual());
        }
        $this->actingAs($d['orangTua'])->get(route('presensi-anak.index', ['siswa_id' => $d['siswa']->id, 'bulan' => '2026-11']))
            ->assertOk()->assertSee('Kartu pelajar hilang')->assertSee('15 poin')->assertSee('hubungi admin sekolah');
        $items = NotifikasiPengguna::where('kunci_unik', 'like', 'presensi-manual-%')->get();
        $this->assertEqualsCanonicalizing([$d['akunSiswa']->id, $d['orangTua']->id], $items->pluck('pengguna_id')->all());
        foreach ($items as $n) {
            $this->assertStringContainsString('15 poin', $n->pesan);
            $this->assertStringContainsString('Kartu pelajar hilang', $n->pesan);
            $push = app(IsiPushNotifikasiService::class)->untuk($n);
            $this->assertStringContainsString('15 poin', $push['body']);
            $this->assertStringNotContainsString('privat', $push['body']);
            $this->assertStringNotContainsString($d['siswa']->nama_lengkap, $push['body']);
            $this->assertStringStartsWith($n->pengguna_id === $d['orangTua']->id ? '/kehadiran-anak-saya?' : '/kehadiran-saya?', app(TujuanNotifikasiMobileService::class)->untuk($n));
        }
        Http::assertNothingSent();
    }

    public function test_scan_pulang_tidak_menghilangkan_sanksi_manual_dan_koreksi_izin_membatalkannya(): void
    {
        $d = $this->data();
        $a = app(CatatKehadiranSiswaPiketService::class)->catat($d['guru'], $d['anggota'], 'hadir', 'Dikonfirmasi.', 'tidak_membawa_kartu');
        Carbon::setTestNow('2026-11-05 14:15:00');
        $scan = app(ProsesScanAbsensi::class)->proses($d['siswa']->nisn, now(), 'pulang');
        $this->assertTrue($scan['berhasil']);
        $this->assertSame('scan', $a->fresh()->sumber);
        $this->assertSame(5, $a->fresh()->menit_terlambat);
        $this->assertSame(15, (int) TransaksiPoinSiswa::sum('poin'));
        $service = app(KoreksiPresensiSiswaService::class);
        $service->koreksi($d['admin'], $d['anggota'], $this->payload());
        $this->assertSame(5, $a->fresh()->sanksi_manual_menit);
        $service->koreksi($d['admin'], $d['anggota'], ['tanggal' => '2026-11-05', 'status_kehadiran' => 'izin', 'catatan' => 'Koreksi setelah konfirmasi orang tua.']);
        $this->assertSame(0, $a->fresh()->poin_keterlambatan_terhitung);
        $this->assertSame(0, (int) TransaksiPoinSiswa::sum('poin'));
    }

    public function test_poin_nol_dihormati_dan_tanpa_pengaturan_memakai_nilai_bawaan_lima_belas(): void
    {
        $d = $this->data();
        $d['pengaturan']->update(['poin_terlambat' => 0]);
        $service = app(CatatKehadiranSiswaPiketService::class);
        $a = $service->catat($d['guru'], $d['anggota'], 'hadir', 'Dikonfirmasi.', 'kartu_hilang');
        $this->assertSame(0, $a->poin_keterlambatan_terhitung);
        $this->assertDatabaseCount('transaksi_poin_siswa', 0);
        $d['pengaturan']->delete();
        $a = $service->catat($d['guru'], $d['anggota'], 'hadir', 'Dikonfirmasi.', 'kartu_hilang');
        $this->assertSame(15, $a->poin_keterlambatan_terhitung);
    }

    public function test_api_menolak_alasan_tidak_valid_dan_akun_siswa_atau_orang_tua_tidak_bisa_mencatat(): void
    {
        $d = $this->data();
        $this->withToken($d['guru']->createToken('Tes piket')->plainTextToken)
            ->getJson(route('api.v1.piket-saya.index'))->assertOk()
            ->assertJsonCount(2, 'data.aturan_hadir_manual.alasan')
            ->assertJsonPath('data.aturan_hadir_manual.alasan.0.kode', 'tidak_membawa_kartu')
            ->assertJsonPath('data.aturan_hadir_manual.alasan.1.kode', 'kartu_hilang');
        foreach (['bebas_sanksi', 'input_manual_petugas'] as $alasan) {
            $this->patchJson(route('api.v1.piket-saya.kehadiran.update', $d['anggota']), [
                'status_kehadiran' => 'hadir', 'catatan' => 'Dikonfirmasi.', 'alasan_hadir_manual' => $alasan,
            ])->assertUnprocessable()->assertJsonValidationErrors('alasan_hadir_manual');
        }
        foreach ([$d['akunSiswa'], $d['orangTua']] as $akun) {
            $this->withToken($akun->createToken('Tes akses')->plainTextToken)
                ->patchJson(route('api.v1.rekap-presensi-siswa.update', $d['anggota']), $this->payload())->assertForbidden();
        }
        $this->assertDatabaseCount('absensi_siswa', 0);
    }

    private function payload(): array
    {
        return ['tanggal' => '2026-11-05', 'status_kehadiran' => 'hadir', 'jam_masuk' => '06:55:00', 'catatan' => 'Dikonfirmasi petugas sekolah.'];
    }

    private function data(): array
    {
        $admin = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
        $kelas = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'IX.D', 'tingkat' => 9, 'aktif' => true]);
        $siswa = Siswa::create(['nama_lengkap' => 'Siswa Tanpa Kartu', 'nisn' => '0099887766', 'aktif' => true]);
        $anggota = AnggotaKelas::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'siswa_id' => $siswa->id, 'status_keanggotaan' => 'aktif', 'tanggal_masuk' => '2026-07-01']);
        $pengaturan = PengaturanPoinKeterlambatan::create(['tahun_pelajaran_id' => $tahun->id, 'aktif' => false, 'otomatis_langsung' => false, 'poin_terlambat' => 15, 'poin_alfa' => 25]);
        PengaturanAbsensi::create(['hari' => 'kamis', 'urutan_hari' => 4, 'jam_scan_masuk_mulai' => '06:00', 'jam_masuk' => '07:00', 'jam_scan_masuk_selesai' => '09:00', 'jam_scan_pulang_mulai' => '14:00', 'jam_pulang' => '14:10', 'jam_scan_pulang_selesai' => '16:00', 'aktif' => true]);
        $pegawai = Pegawai::create(['nama_lengkap' => 'Guru Piket Uji', 'nip' => '19801111', 'jenis_pegawai' => 'Guru', 'aktif' => true]);
        $mapel = MataPelajaran::create(['kode' => 'UJI', 'nama' => 'Uji', 'kelompok' => 'Umum', 'aktif' => true]);
        GuruMataPelajaran::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'pegawai_id' => $pegawai->id, 'mata_pelajaran_id' => $mapel->id, 'jenis_penugasan' => 'pengampu', 'aktif' => true]);
        $guru = $this->akun('guru-manual', ['pegawai_id' => $pegawai->id, 'peran' => 'pegawai']);
        $guru->daftarPeran()->attach(Peran::whereIn('kode', ['pegawai', 'guru_mapel'])->pluck('id'));
        JadwalPiketGuru::create(['tahun_pelajaran_id' => $tahun->id, 'pegawai_id' => $pegawai->id, 'hari' => 'kamis', 'aktif' => true]);
        $akunSiswa = $this->akun('siswa-manual', ['siswa_id' => $siswa->id, 'peran' => 'siswa']);
        $orangTua = $this->akun('orang-tua-manual', ['peran' => 'orang_tua']);
        $profil = OrangTuaWali::create(['pengguna_id' => $orangTua->id, 'nama_lengkap' => 'Orang Tua Uji']);
        $profil->siswa()->attach($siswa->id, ['hubungan' => 'ayah', 'utama' => true]);
        $this->akun('orang-tua-tanpa-relasi', ['peran' => 'orang_tua']);

        return compact('admin', 'tahun', 'kelas', 'siswa', 'anggota', 'pengaturan', 'guru', 'akunSiswa', 'orangTua');
    }

    private function akun(string $username, array $data): Pengguna
    {
        return Pengguna::create($data + ['nama' => $username, 'username' => $username, 'kata_sandi' => 'Uji-Presensi-2026', 'aktif' => true, 'akun_sistem' => false, 'wajib_ganti_kata_sandi' => false]);
    }
}
