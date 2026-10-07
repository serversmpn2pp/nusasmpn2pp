<?php

namespace Tests\Feature;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\AturanSanksiPoin;
use App\Models\Kelas;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pegawai;
use App\Models\PengaturanAbsensi;
use App\Models\PengaturanPoinKeterlambatan;
use App\Models\Pengguna;
use App\Models\PenguranganPoinSiswa;
use App\Models\PenugasanGuruBkTingkat;
use App\Models\Peran;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;
use App\Services\Absensi\KoreksiPresensiSiswaService;
use App\Services\Absensi\PengecualianPresensiSiswaService;
use App\Services\Absensi\ProsesScanAbsensi;
use App\Services\Pembinaan\PoinPresensiOtomatisService;
use App\Services\Pembinaan\ProsesPoinKeterlambatanService;
use App\Services\Pembinaan\ProsesPoinSiswaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class PoinPresensiLangsungTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-10-07 07:00:01'));
    }

    public function test_aktivasi_tanggal_server_dan_pengulangan_tidak_menggeser_tanggal_awal(): void
    {
        $data = $this->data(false);
        $this->artisan('pembinaan:aktifkan-poin-presensi', ['--terlambat' => 15, '--alfa' => 25])->assertSuccessful();
        $this->assertDatabaseHas('pengaturan_poin_keterlambatan', [
            'tahun_pelajaran_id' => $data['tahun']->id, 'otomatis_langsung' => true,
            'poin_terlambat' => 15, 'poin_alfa' => 25,
        ]);
        $this->assertSame('2026-10-07', $data['pengaturan']->fresh()->berlaku_mulai->toDateString());
        $this->travelTo(Carbon::parse('2026-10-08 07:00:00'));
        $this->artisan('pembinaan:aktifkan-poin-presensi')->assertSuccessful();
        $this->assertSame('2026-10-07', $data['pengaturan']->fresh()->berlaku_mulai->toDateString());
    }

    public function test_sebelum_aktivasi_tidak_dihukum_meski_sinkronisasi_dipaksa(): void
    {
        $data = $this->data();
        $absensi = $this->absensi($data, '2026-10-06', 'hadir', '07:15:00');
        app(ProsesPoinKeterlambatanService::class)->prosesTanggal('2026-10-06', $data['tahun']->id, paksa: true);
        app(ProsesPoinKeterlambatanService::class)->sinkronkanAbsensi($absensi);
        $this->assertDatabaseCount('transaksi_poin_siswa', 0);
        $this->assertDatabaseCount('laporan_pembinaan_siswa', 0);
    }

    public function test_scan_detik_pertama_langsung_15_poin_tanpa_persetujuan_dan_tidak_ganda(): void
    {
        $data = $this->data();
        $hasil = app(ProsesScanAbsensi::class)->proses($data['siswa']->nisn, now(), 'masuk');
        $this->assertTrue($hasil['berhasil']);
        $this->assertSame('terlambat', $hasil['absensi']->status_masuk);
        $this->assertSame(1, $hasil['absensi']->menit_terlambat);
        $this->assertSame('07:00:01', $hasil['absensi']->jam_masuk);
        $laporan = LaporanPembinaanSiswa::firstOrFail();
        $this->assertSame('disahkan', $laporan->status_verifikasi);
        $this->assertSame(15, $laporan->total_poin);
        $this->assertSame('Poin otomatis', $laporan->labelStatusVerifikasi());
        $this->assertSame(0, $laporan->verifikasiBkPelanggaran()->count());
        $this->assertSame(0, $laporan->persetujuanPelanggaran()->count());
        app(PoinPresensiOtomatisService::class)->prosesSemua();
        app(ProsesScanAbsensi::class)->proses($data['siswa']->nisn, now()->addSeconds(20), 'masuk');
        $this->assertDatabaseCount('laporan_pembinaan_siswa', 1);
        $this->assertDatabaseCount('transaksi_poin_siswa', 1);
        $this->assertSame(15, (int) TransaksiPoinSiswa::sum('poin'));
    }

    public function test_tepat_pada_batas_tidak_diberi_poin(): void
    {
        $data = $this->data();
        $hasil = app(ProsesScanAbsensi::class)->proses($data['siswa']->nisn, Carbon::parse('2026-10-07 07:00:00'), 'masuk');
        $this->assertTrue($hasil['berhasil']);
        $this->assertSame('tepat_waktu', $hasil['absensi']->status_masuk);
        $this->assertDatabaseCount('transaksi_poin_siswa', 0);
    }

    public function test_terlambat_lama_tetap_15_bukan_rentang_lama(): void
    {
        $data = $this->data();
        $absensi = $this->absensi($data, '2026-10-07', 'hadir', '07:40:01');
        app(ProsesPoinKeterlambatanService::class)->sinkronkanAbsensi($absensi);
        $this->assertSame(15, (int) TransaksiPoinSiswa::sum('poin'));
        $this->assertSame(41, $absensi->fresh()->menit_terlambat);
    }

    public function test_alfa_setelah_hari_berakhir_tanpa_membuat_absensi_palsu(): void
    {
        $data = $this->data();
        app(PoinPresensiOtomatisService::class)->prosesSemua();
        $this->assertDatabaseCount('transaksi_poin_siswa', 0);
        $this->travelTo(Carbon::parse('2026-10-08 00:01:00'));
        $this->artisan('pembinaan:proses-poin-presensi')->assertSuccessful();
        $this->assertDatabaseCount('absensi_siswa', 0);
        $this->assertSame('alfa', LaporanPembinaanSiswa::firstOrFail()->jenis_presensi_otomatis);
        $this->assertSame(25, (int) TransaksiPoinSiswa::sum('poin'));
        $this->actingAs($data['admin'])->get(route('rekap-absensi-harian.index', ['tanggal' => '2026-10-07']))
            ->assertOk()->assertSee('Poin otomatis')->assertSee('25 poin');
        $this->artisan('pembinaan:proses-poin-presensi')->assertSuccessful();
        $this->assertDatabaseCount('transaksi_poin_siswa', 1);
    }

    public function test_sakit_izin_hadir_tanpa_scan_pulang_dan_libur_tidak_diberi_poin_alfa(): void
    {
        $data = $this->data();
        $this->absensi($data, '2026-10-07', 'sakit');
        $this->absensi($data, '2026-10-08', 'izin');
        $this->absensi($data, '2026-10-14', 'hadir', '06:55:00');
        $this->travelTo(Carbon::parse('2026-10-15 07:00:00'));
        $service = app(PoinPresensiOtomatisService::class);
        foreach (['2026-10-07', '2026-10-08', '2026-10-10', '2026-10-14'] as $tanggal) {
            $service->prosesTanggal($tanggal, $data['tahun']->id);
        }
        $this->assertDatabaseCount('transaksi_poin_siswa', 0);
    }

    public function test_alfa_manual_juga_menunggu_hari_berakhir(): void
    {
        $data = $this->data();
        $absensi = $this->absensi($data, '2026-10-07', 'alfa');
        app(ProsesPoinKeterlambatanService::class)->sinkronkanAbsensi($absensi);
        $this->assertDatabaseCount('transaksi_poin_siswa', 0);
        $this->travelTo(Carbon::parse('2026-10-08 06:00:00'));
        app(PoinPresensiOtomatisService::class)->prosesSemua();
        $this->assertSame(25, (int) TransaksiPoinSiswa::sum('poin'));
    }

    public function test_koreksi_alfa_ke_izin_mengembalikan_poin_tanpa_mengurangi_kejadian_lain(): void
    {
        $data = $this->data();
        $this->travelTo(Carbon::parse('2026-10-09 07:00:00'));
        app(PoinPresensiOtomatisService::class)->prosesSemua();
        $this->assertSame(50, (int) TransaksiPoinSiswa::sum('poin'));
        $this->actingAs($data['admin'])->put(route('rekap-absensi-harian.koreksi.update', $data['anggota']), [
            'tanggal' => '2026-10-07', 'status_kehadiran' => 'izin', 'catatan' => 'Surat izin dokter diterima.',
        ])->assertSessionHasNoErrors();
        $this->assertSame(25, (int) TransaksiPoinSiswa::sum('poin'));
        app(PoinPresensiOtomatisService::class)->prosesTanggal('2026-10-07', $data['tahun']->id);
        $this->assertSame(25, (int) TransaksiPoinSiswa::sum('poin'));
    }

    public function test_alasan_diterima_poin_hilang_dan_tidak_muncul_lagi_walau_jam_dikoreksi(): void
    {
        $data = $this->data();
        $absensi = $this->absensi($data, '2026-10-07', 'hadir', '07:00:01');
        app(ProsesPoinKeterlambatanService::class)->sinkronkanAbsensi($absensi);
        $laporan = LaporanPembinaanSiswa::firstOrFail();
        $this->actingAs($data['admin'])->post(route('poin-presensi.koreksi', $laporan), ['alasan' => 'Ditugaskan sekolah membantu kegiatan.'])
            ->assertSessionHasNoErrors();
        $this->assertNotNull($laporan->fresh()->poin_dikecualikan_pada);
        $this->assertSame('07:00:01', $absensi->fresh()->jam_masuk);
        $this->assertSame(0, (int) TransaksiPoinSiswa::sum('poin'));
        app(KoreksiPresensiSiswaService::class)->koreksi($data['admin'], $data['anggota'], [
            'tanggal' => '2026-10-07', 'status_kehadiran' => 'hadir', 'jam_masuk' => '07:20:01', 'catatan' => 'Waktu kegiatan dikonfirmasi.',
        ]);
        $this->post(route('poin-presensi.koreksi', $laporan), ['alasan' => 'Diproses ulang.'])->assertSessionHasNoErrors();
        app(PoinPresensiOtomatisService::class)->prosesSemua();
        $this->assertSame(0, (int) TransaksiPoinSiswa::sum('poin'));
        $this->assertDatabaseCount('transaksi_poin_siswa', 2);
        $this->assertSame(1, $laporan->riwayatProsesPembinaanSiswa()->where('kode_kegiatan', 'alasan_presensi_diterima')->count());
    }

    public function test_alasan_kosong_ditolak_dan_rute_hapus_lama_tidak_bisa_melewati_koreksi(): void
    {
        $data = $this->data();
        app(ProsesPoinKeterlambatanService::class)->sinkronkanAbsensi($this->absensi($data, '2026-10-07', 'hadir', '07:01:00'));
        $laporan = LaporanPembinaanSiswa::firstOrFail();
        $this->actingAs($data['admin'])->post(route('poin-presensi.koreksi', $laporan), ['alasan' => '   '])->assertSessionHasErrors('alasan');
        $this->delete(route('laporan-pembinaan-siswa.destroy', $laporan))->assertStatus(422);
        $this->assertSame(15, (int) TransaksiPoinSiswa::sum('poin'));
        $this->put(route('rekap-absensi-harian.koreksi.update', $data['anggota']), [
            'tanggal' => '2026-10-07', 'status_kehadiran' => 'hadir', 'jam_masuk' => '07:00:00',
        ])->assertSessionHasErrors('catatan');
    }

    public function test_koreksi_bk_dibatasi_tingkat_dan_siswa_tidak_boleh_mengubah_poin(): void
    {
        $data = $this->data();
        app(ProsesPoinKeterlambatanService::class)->sinkronkanAbsensi($this->absensi($data, '2026-10-07', 'hadir', '07:01:00'));
        $laporan = LaporanPembinaanSiswa::firstOrFail();
        $pegawai = Pegawai::create(['nama_lengkap' => 'BK Tingkat 7', 'aktif' => true]);
        $bk = Pengguna::create(['nama' => 'BK Tingkat 7', 'username' => 'bk-tujuh', 'kata_sandi' => 'Uji-12345', 'peran' => 'pegawai', 'pegawai_id' => $pegawai->id, 'aktif' => true]);
        $bk->daftarPeran()->attach(Peran::where('kode', 'bk')->firstOrFail());
        PenugasanGuruBkTingkat::create(['tahun_pelajaran_id' => $data['tahun']->id, 'pegawai_id' => $pegawai->id, 'tingkat' => 7, 'aktif' => true, 'tanggal_mulai' => '2026-07-01']);
        $this->actingAs($bk)->post(route('poin-presensi.koreksi', $laporan), ['alasan' => 'Alasan sah.'])->assertForbidden();
        $siswa = Pengguna::create(['nama' => 'Siswa', 'username' => 'siswa-uji-poin', 'kata_sandi' => 'Uji-12345', 'peran' => 'siswa', 'siswa_id' => $data['siswa']->id, 'aktif' => true]);
        $this->actingAs($siswa)->post(route('poin-presensi.koreksi', $laporan), ['alasan' => 'Batalkan.'])->assertForbidden();
        $this->assertSame(15, (int) TransaksiPoinSiswa::sum('poin'));
        $this->actingAs($data['admin'])->get(route('laporan-pembinaan-siswa.show', $laporan))
            ->assertOk()->assertSee('Terima alasan dan batalkan poin');
    }

    public function test_pengecualian_sebelum_dan_sesudah_pemrosesan_tidak_menghapus_jam_scan(): void
    {
        $data = $this->data();
        $absensi = $this->absensi($data, '2026-10-07', 'hadir', '07:10:01');
        app(ProsesPoinKeterlambatanService::class)->sinkronkanAbsensi($absensi);
        $this->assertSame(15, (int) TransaksiPoinSiswa::sum('poin'));
        $service = app(PengecualianPresensiSiswaService::class);
        $preview = $service->pratinjau($data['admin'], ['tahun_pelajaran_id' => $data['tahun']->id, 'kelas_id' => null,
            'tanggal_mulai' => '2026-10-07', 'tanggal_selesai' => '2026-10-08', 'jenis' => 'pjj', 'alasan' => 'Bencana asap.']);
        $service->terapkan($data['admin'], $preview['token']);
        $this->assertSame(0, (int) TransaksiPoinSiswa::sum('poin'));
        $this->assertSame('07:10:01', $absensi->fresh()->jam_masuk);
        $this->assertSame(11, $absensi->fresh()->menit_terlambat);
        $this->travelTo(Carbon::parse('2026-10-09 06:00:00'));
        app(PoinPresensiOtomatisService::class)->prosesSemua();
        $this->assertSame(0, (int) TransaksiPoinSiswa::sum('poin'));
    }

    public function test_scheduler_mengejar_hari_terlewat_hanya_sejak_aktivasi(): void
    {
        $data = $this->data();
        $this->travelTo(Carbon::parse('2026-10-10 07:00:00'));
        $this->artisan('pembinaan:proses-poin-presensi')->assertSuccessful();
        $this->assertSame(50, (int) TransaksiPoinSiswa::sum('poin'));
        $this->assertSame('2026-10-09', $data['pengaturan']->fresh()->alfa_diproses_sampai->toDateString());
        $this->artisan('pembinaan:proses-poin-presensi')->assertSuccessful();
        $this->assertDatabaseCount('transaksi_poin_siswa', 2);
    }

    public function test_api_pengaturan_dan_koreksi_memakai_aturan_yang_sama(): void
    {
        $data = $this->data(false);
        $token = $data['admin']->createToken('Uji poin')->plainTextToken;
        $this->withToken($token)->putJson(route('api.v1.pengaturan-poin-keterlambatan.update', $data['tahun']), [
            'aktif' => true, 'otomatis_langsung' => true, 'poin_terlambat' => 15, 'poin_alfa' => 25,
            'berlaku_mulai' => '2026-07-01', 'rentang' => [['menit_mulai' => 1, 'menit_selesai' => null, 'poin' => 15]],
        ])->assertOk();
        $this->assertSame('2026-10-07', $data['pengaturan']->fresh()->berlaku_mulai->toDateString());
        $this->getJson(route('api.v1.pengaturan-poin-keterlambatan.index'))
            ->assertOk()->assertJsonPath('data.items.0.berlaku_mulai', '2026-10-07')->assertJsonPath('data.items.0.poin_alfa', 25);
        app(ProsesPoinKeterlambatanService::class)->sinkronkanAbsensi($this->absensi($data, '2026-10-07', 'hadir', '07:01:00'));
        $laporan = LaporanPembinaanSiswa::firstOrFail();
        $this->postJson(route('api.v1.poin-presensi.koreksi', $laporan), ['alasan' => 'Tugas sekolah.'])
            ->assertOk()->assertJsonPath('data.total_poin', 0);
        $this->assertSame(0, (int) TransaksiPoinSiswa::sum('poin'));
    }

    public function test_koreksi_membatalkan_sanksi_yang_belum_diproses_dan_bisa_terpicu_lagi(): void
    {
        $data = $this->data();
        AturanSanksiPoin::query()->update(['aktif' => false]);
        $aturan = AturanSanksiPoin::create(['batas_poin' => 15, 'nama' => 'Pembinaan presensi', 'deskripsi' => 'Pembinaan setelah batas poin tercapai.', 'aktif' => true, 'urutan' => 1]);
        app(ProsesPoinKeterlambatanService::class)->sinkronkanAbsensi($this->absensi($data, '2026-10-07', 'hadir', '07:01:00'));
        $sanksi = $aturan->sanksiPoinSiswa()->firstOrFail();
        $this->assertSame('menunggu', $sanksi->status);
        app(PoinPresensiOtomatisService::class)->terimaAlasan(LaporanPembinaanSiswa::firstOrFail(), $data['admin']->id, 'Tugas sekolah.');
        $this->assertSame('dibatalkan', $sanksi->fresh()->status);
        $this->travelTo(Carbon::parse('2026-10-08 07:10:00'));
        app(ProsesPoinKeterlambatanService::class)->sinkronkanAbsensi($this->absensi($data, '2026-10-08', 'hadir', '07:10:00'));
        $this->assertSame('menunggu', $sanksi->fresh()->status);
        $this->assertSame(1, $aturan->sanksiPoinSiswa()->count());
    }

    public function test_formulir_pengaturan_mode_dan_koreksi_presensi_menjaga_detik(): void
    {
        $data = $this->data();
        app(ProsesPoinKeterlambatanService::class)->sinkronkanAbsensi($this->absensi($data, '2026-10-07', 'hadir', '07:00:01'));
        $laporan = LaporanPembinaanSiswa::firstOrFail();
        $this->actingAs($data['admin']);
        $pages = [
            'settings' => $this->get(route('pengaturan-poin-keterlambatan.edit', $data['tahun']))->assertOk()->assertSee('Poin langsung tanpa persetujuan'),
            'correction' => $this->get(route('laporan-pembinaan-siswa.show', $laporan))->assertOk()->assertSee('Terima alasan dan batalkan poin'),
            'attendance' => $this->get(route('rekap-absensi-harian.koreksi.edit', [$data['anggota'], 'tanggal' => '2026-10-07']))
                ->assertOk()->assertSee('07:00:01')->assertSee('step="1"', false),
        ];
        if (getenv('NUSA_CAPTURE_POIN_PRESENSI')) {
            $directory = storage_path('framework/testing/poin-presensi');
            File::ensureDirectoryExists($directory);
            foreach ($pages as $name => $response) {
                File::put($directory.'/'.$name.'.html', $response->getContent());
            }
        }
        $this->put(route('rekap-absensi-harian.koreksi.update', $data['anggota']), [
            'tanggal' => '2026-10-07', 'status_kehadiran' => 'hadir', 'jam_masuk' => '07:00:00', 'catatan' => 'Detik pada mesin dikoreksi.',
        ])->assertSessionHasNoErrors();
        $this->assertSame(0, (int) TransaksiPoinSiswa::sum('poin'));
    }

    public function test_pembatalan_setelah_penghargaan_tidak_menyimpan_saldo_negatif_untuk_pelanggaran_berikutnya(): void
    {
        $data = $this->data();
        app(ProsesPoinKeterlambatanService::class)->sinkronkanAbsensi($this->absensi($data, '2026-10-07', 'hadir', '07:01:00'));
        $reward = PenguranganPoinSiswa::create(['siswa_id' => $data['siswa']->id, 'tahun_pelajaran_id' => $data['tahun']->id,
            'tanggal_kegiatan' => '2026-10-07', 'jenis_kegiatan' => 'Penghargaan', 'poin_pengurangan' => 10, 'status' => 'diajukan']);
        app(ProsesPoinSiswaService::class)->setujuiPengurangan($reward, null, 'Penghargaan disetujui.');
        $this->assertSame(5, (int) TransaksiPoinSiswa::sum('poin'));
        $laporan = LaporanPembinaanSiswa::firstOrFail();
        app(PoinPresensiOtomatisService::class)->terimaAlasan($laporan, $data['admin']->id, 'Presensi semula ternyata keliru.');
        $this->assertSame(0, (int) TransaksiPoinSiswa::sum('poin'));
        $this->assertSame(0, (int) $laporan->transaksiPoinSiswa()->sum('poin'));
        $this->travelTo(Carbon::parse('2026-10-08 07:01:00'));
        app(ProsesPoinKeterlambatanService::class)->sinkronkanAbsensi($this->absensi($data, '2026-10-08', 'hadir', '07:01:00'));
        $this->assertSame(15, (int) TransaksiPoinSiswa::sum('poin'));
    }

    public function test_pengaturan_web_tidak_menggeser_aktivasi_dan_api_menampilkan_poin(): void
    {
        $data = $this->data();
        $this->travelTo(Carbon::parse('2026-10-08 07:00:00'));
        $payload = ['aktif' => true, 'otomatis_langsung' => true, 'poin_terlambat' => 15, 'poin_alfa' => 25,
            'rentang' => [['menit_mulai' => 1, 'menit_selesai' => null, 'poin' => 15]]];
        $this->actingAs($data['admin'])->put(route('pengaturan-poin-keterlambatan.update', $data['tahun']), $payload)->assertSessionHasNoErrors();
        $this->assertSame('2026-10-07', $data['pengaturan']->fresh()->berlaku_mulai->toDateString());
        app(PoinPresensiOtomatisService::class)->prosesSemua();
        $token = $data['admin']->createToken('Rekap poin')->plainTextToken;
        $this->withToken($token)->getJson(route('api.v1.rekap-presensi-siswa.index', ['tanggal' => '2026-10-07']))
            ->assertOk()->assertJsonPath('data.items.0.poin_presensi.poin', 25)->assertJsonPath('data.items.0.poin_presensi.dapat_koreksi', true);
        $payload['otomatis_langsung'] = false;
        $this->put(route('pengaturan-poin-keterlambatan.update', $data['tahun']), $payload)->assertSessionHasNoErrors();
        $this->travelTo(Carbon::parse('2026-10-14 07:00:00'));
        $payload['otomatis_langsung'] = true;
        $this->put(route('pengaturan-poin-keterlambatan.update', $data['tahun']), $payload)->assertSessionHasNoErrors();
        $this->assertSame('2026-10-14', $data['pengaturan']->fresh()->berlaku_mulai->toDateString());
        app(PoinPresensiOtomatisService::class)->prosesSemua();
        $this->assertSame(25, (int) TransaksiPoinSiswa::sum('poin'));
    }

    private function data(bool $aktif = true): array
    {
        $admin = Pengguna::where('username', 'administrator')->firstOrFail();
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
        $kelas = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'VIII.A', 'tingkat' => 8, 'aktif' => true]);
        $siswa = Siswa::create(['nama_lengkap' => 'Siswa Poin Presensi', 'nisn' => '0987654321', 'aktif' => true]);
        $anggota = AnggotaKelas::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'siswa_id' => $siswa->id,
            'nomor_absen' => 1, 'status_keanggotaan' => 'aktif', 'tanggal_masuk' => '2026-07-01']);
        $pengaturan = PengaturanPoinKeterlambatan::create(['tahun_pelajaran_id' => $tahun->id, 'aktif' => true,
            'otomatis_langsung' => $aktif, 'berlaku_mulai' => $aktif ? '2026-10-07' : null,
            'alfa_diproses_sampai' => $aktif ? '2026-10-06' : null, 'poin_terlambat' => 15, 'poin_alfa' => 25]);
        $pengaturan->rentangPoinKeterlambatan()->create(['menit_mulai' => 1, 'menit_selesai' => null, 'poin' => 80, 'urutan' => 1]);
        foreach (['rabu' => 3, 'kamis' => 4] as $hari => $urutan) {
            PengaturanAbsensi::create(['hari' => $hari, 'urutan_hari' => $urutan, 'jam_scan_masuk_mulai' => '06:00',
                'jam_masuk' => '07:00', 'jam_scan_masuk_selesai' => '08:00', 'jam_scan_pulang_mulai' => '14:00',
                'jam_pulang' => '14:10', 'jam_scan_pulang_selesai' => '15:00', 'aktif' => true]);
        }

        return compact('admin', 'tahun', 'kelas', 'siswa', 'anggota', 'pengaturan');
    }

    private function absensi(array $data, string $tanggal, string $status, ?string $jam = null): AbsensiSiswa
    {
        return AbsensiSiswa::create(['tanggal' => $tanggal, 'tahun_pelajaran_id' => $data['tahun']->id,
            'kelas_id' => $data['kelas']->id, 'siswa_id' => $data['siswa']->id, 'anggota_kelas_id' => $data['anggota']->id,
            'status_kehadiran' => $status, 'jam_masuk' => $jam, 'status_masuk' => $jam ? 'terlambat' : null,
            'menit_terlambat' => $jam ? (int) substr($jam, 3, 2) : 0, 'sumber' => 'scan']);
    }
}
