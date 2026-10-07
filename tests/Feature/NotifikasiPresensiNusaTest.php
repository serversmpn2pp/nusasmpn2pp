<?php

namespace Tests\Feature;

use App\Jobs\KirimNotifikasiAbsensiSiswa;
use App\Jobs\KirimPushNotification;
use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\NotifikasiAbsensiSiswa;
use App\Models\NotifikasiPengguna;
use App\Models\OrangTuaWali;
use App\Models\PengaturanAbsensi;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Absensi\ProsesScanAbsensi;
use App\Services\Mobile\TujuanNotifikasiMobileService;
use App\Services\Notifikasi\IsiPushNotifikasiService;
use App\Services\Notifikasi\NotifikasiAbsensiSiswaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NotifikasiPresensiNusaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::fake();
        Queue::fake();
        config()->set('services.firebase.push_enabled', true);
        // Nomor/kredensial serta sakelar WhatsApp tidak diperlukan untuk NUSA.
        config()->set('services.whatsapp.kirim_otomatis_absensi_siswa', false);
    }

    public function test_scan_memberi_notifikasi_ke_siswa_dan_orang_tua_terhubung_tanpa_whatsapp(): void
    {
        $siswa = $this->siswaSiapScan();
        $akunSiswa = $this->akun('siswa-uji', ['siswa_id' => $siswa->id, 'peran' => 'pegawai']);
        $ayah = $this->orangTua('ayah-uji', $siswa);
        $ibu = $this->orangTua('ibu-uji', $siswa);
        $this->akun('role-orang-tua-palsu', ['peran' => 'orang_tua']);
        $lain = Siswa::create(['nama_lengkap' => 'Anak lain', 'nisn' => '8877665544', 'aktif' => true]);
        $this->orangTua('orang-tua-lain', $lain);
        $nonaktif = $this->orangTua('orang-tua-nonaktif', $siswa);
        $nonaktif->update(['aktif' => false]);
        $kontradiktif = $this->orangTua('identitas-bukan-orang-tua', $siswa);
        $kontradiktif->update(['siswa_id' => $lain->id]);

        $hasil = app(ProsesScanAbsensi::class)->proses($siswa->nisn, Carbon::parse('2026-08-06 06:54:10'), 'masuk');
        $this->assertTrue($hasil['berhasil']);
        $notifikasi = NotifikasiPengguna::where('kunci_unik', 'like', 'presensi-masuk-%')->get();
        $this->assertEqualsCanonicalizing([$akunSiswa->id, $ayah->id, $ibu->id], $notifikasi->pluck('pengguna_id')->all());
        $this->assertCount(3, $notifikasi);
        foreach ($notifikasi as $item) {
            $parent = $item->pengguna_id !== $akunSiswa->id;
            $this->assertStringContainsString($siswa->nama_lengkap, $item->pesan);
            $this->assertSame(($parent ? 'Anak Anda' : 'Anda').' tercatat hadir tepat waktu pukul 06.54 WIB.', app(IsiPushNotifikasiService::class)->untuk($item)['body']);
            $this->assertSame(($parent ? '/kehadiran-anak-saya' : '/kehadiran-saya').'?siswa_id='.$siswa->id.'&bulan=2026-08', app(TujuanNotifikasiMobileService::class)->untuk($item));
        }
        $catatan = NotifikasiAbsensiSiswa::findOrFail($hasil['notifikasi_absensi_id']);
        $this->assertSame('nusa', $catatan->kanal);
        $this->assertSame('tersimpan', $catatan->status);
        $this->assertNull($catatan->dikirim_pada);
        $this->assertNull($catatan->nomor_tujuan);
        Queue::assertPushed(KirimPushNotification::class, 3);
        Queue::assertNotPushed(KirimNotifikasiAbsensiSiswa::class);
        Http::assertNothingSent();
    }

    public function test_scan_ulang_dan_pemanggilan_ulang_notifier_tidak_menggandakan_push(): void
    {
        $siswa = $this->siswaSiapScan();
        $this->orangTua('orang-tua-ulang', $siswa);
        $proses = app(ProsesScanAbsensi::class);
        $hasil = $proses->proses($siswa->nisn, Carbon::parse('2026-08-06 07:10:10'), 'masuk');
        $ulang = $proses->proses($siswa->nisn, Carbon::parse('2026-08-06 07:10:20'), 'masuk');
        $this->assertFalse($ulang['berhasil']);
        $catatan = NotifikasiAbsensiSiswa::findOrFail($hasil['notifikasi_absensi_id']);
        app(NotifikasiAbsensiSiswaService::class)->jadwalkanScanMasuk($hasil['absensi'], $catatan->logScanAbsensi);
        $this->assertSame(1, NotifikasiPengguna::where('kunci_unik', 'like', 'presensi-masuk-%')->count());
        $this->assertSame(1, NotifikasiAbsensiSiswa::where('kanal', 'nusa')->count());
        $this->assertSame('Anak Anda tercatat hadir terlambat 10 menit pukul 07.10 WIB.', app(IsiPushNotifikasiService::class)->untuk(NotifikasiPengguna::firstOrFail())['body']);
        Queue::assertPushed(KirimPushNotification::class, 1);
    }

    public function test_tanpa_akun_terhubung_scan_tetap_berhasil_tanpa_pengiriman(): void
    {
        $siswa = $this->siswaSiapScan();
        $hasil = app(ProsesScanAbsensi::class)->proses($siswa->nisn, Carbon::parse('2026-08-06 06:54:00'), 'masuk');
        $this->assertTrue($hasil['berhasil']);
        $this->assertSame('dilewati', NotifikasiAbsensiSiswa::findOrFail($hasil['notifikasi_absensi_id'])->status);
        $this->assertSame(0, NotifikasiPengguna::count());
        Queue::assertNothingPushed();
    }

    public function test_job_whatsapp_lama_tidak_mengirim_dan_riwayat_selesai_tetap_utuh(): void
    {
        $siswa = $this->siswaSiapScan();
        foreach (['menunggu', 'gagal', 'terkirim', 'simulasi', 'dilewati'] as $status) {
            $record = NotifikasiAbsensiSiswa::create(['siswa_id' => $siswa->id, 'tanggal' => '2026-08-06',
                'jenis_absensi' => 'masuk', 'jenis_pesan' => 'masuk_tepat_waktu', 'kanal' => 'whatsapp',
                'mode_pengiriman' => 'cloud', 'nomor_tujuan' => '628111111111', 'pesan' => 'Pesan lama', 'status' => $status]);
            (new KirimNotifikasiAbsensiSiswa($record->id))->handle();
            $this->assertSame(in_array($status, ['menunggu', 'gagal']) ? 'dilewati' : $status, $record->fresh()->status);
            $this->assertSame('Pesan lama', $record->fresh()->pesan);
        }
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    private function akun(string $username, array $data = []): Pengguna
    {
        return Pengguna::create($data + ['nama' => $username, 'username' => $username, 'kata_sandi' => 'RahasiaNusa123!',
            'peran' => 'pegawai', 'aktif' => true, 'akun_sistem' => false, 'wajib_ganti_kata_sandi' => false]);
    }

    private function orangTua(string $username, Siswa $siswa): Pengguna
    {
        $akun = $this->akun($username);
        $wali = OrangTuaWali::create(['pengguna_id' => $akun->id, 'nama_lengkap' => $username]);
        $wali->siswa()->attach($siswa->id, ['hubungan' => 'wali', 'utama' => true]);

        return $akun;
    }

    private function siswaSiapScan(): Siswa
    {
        $tahun = TahunPelajaran::create(['nama' => '2026/2027', 'tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30', 'aktif' => true]);
        $kelas = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'VII.A', 'tingkat' => 7, 'aktif' => true]);
        $siswa = Siswa::create(['nama_lengkap' => 'Nadia Uji Presensi', 'nisn' => '0011223344', 'aktif' => true]);
        AnggotaKelas::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'siswa_id' => $siswa->id,
            'nomor_absen' => 1, 'status_keanggotaan' => 'aktif', 'tanggal_masuk' => '2026-07-01']);
        PengaturanAbsensi::create(['hari' => 'kamis', 'urutan_hari' => 4, 'jam_scan_masuk_mulai' => '06:00', 'jam_masuk' => '07:00',
            'jam_scan_masuk_selesai' => '07:30', 'jam_scan_pulang_mulai' => '14:00', 'jam_pulang' => '14:10', 'jam_scan_pulang_selesai' => '15:00', 'aktif' => true]);

        return $siswa;
    }
}
