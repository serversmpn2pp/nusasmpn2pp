<?php

namespace Tests\Feature;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\Kelas;
use App\Models\KelompokTahfidz;
use App\Models\KomponenNilai;
use App\Models\LaporanPembinaanSiswa;
use App\Models\LogScanAbsensi;
use App\Models\MataPelajaran;
use App\Models\NilaiSiswa;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\PenugasanGuruBkTingkat;
use App\Models\PenugasanGuruWaliSiswa;
use App\Models\Peran;
use App\Models\RiwayatPerubahanAbsensiSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ProfilSiswaTerpaduTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_by_name_nis_and_nisn_and_read_each_tab(): void
    {
        [$tahun, $kelas, $siswa] = $this->data();
        $this->actingAs($this->user('administrator'));
        foreach (['murid contoh', '6111', '0012345678'] as $q) {
            $response = $this->get(route('profil-siswa-terpadu.index', ['kelas' => $kelas->id, 'q' => $q]))
                ->assertOk()->assertSee('Murid Contoh Satu')->assertDontSee('Murid Contoh Dua')
                ->assertDontSee('1111111122222222')->assertDontSee('080000000121');
            $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        }
        $daftar = $this->get(route('profil-siswa-terpadu.index'))->assertOk()->assertSee('Profil Siswa Terpadu');
        if ($folder = getenv('NUSA_PROFIL_PREVIEW_PATH')) {
            file_put_contents($folder.'/profil-directory.html', $daftar->getContent());
        }
        $this->tab($siswa, 'data')->assertOk()->assertSee('1111111122222222')->assertSee('080000000121');
        foreach (['ringkasan', 'presensi', 'akademik', 'ujian', 'pembinaan', 'ibadah', 'lainnya'] as $tab) {
            $this->tab($siswa, $tab)->assertOk();
        }
        $this->tab($siswa, 'lainnya')->assertSee('Prestasi terverifikasi')->assertSee('Peminjaman barang');
        $this->tab($siswa, 'data')->assertSee('Riwayat kelas')->assertSee($tahun->nama);
        $this->get(route('siswa.show', $siswa))->assertOk()->assertSee('Lihat profil terpadu');
    }

    public function test_restricted_account_cannot_search_or_open_profile_and_profiles_are_read_only(): void
    {
        [, , $siswa] = $this->data();
        $url = route('profil-siswa-terpadu.index');
        $this->get($url)->assertRedirect();
        foreach (['satpam', 'siswa', 'orang_tua'] as $role) {
            $this->actingAs($this->user($role, in_array($role, ['siswa', 'orang_tua'], true) ? null : true));
            $this->get($url)->assertForbidden();
            $this->tab($siswa, 'ringkasan')->assertForbidden();
        }
        $this->actingAs($this->user('administrator'));
        $this->post(route('profil-siswa-terpadu.show', $siswa), ['nama_lengkap' => 'Tidak boleh'])->assertMethodNotAllowed();
        $this->assertDatabaseHas('siswa', ['id' => $siswa->id, 'nama_lengkap' => 'Murid Contoh Satu']);
    }

    public function test_admin_can_select_a_historical_year_without_mixing_its_components_with_the_active_year(): void
    {
        [$tahun, $kelas, $siswa] = $this->data();
        $arsip = $this->buatTahun('2025/2026', '2025-07-01', '2026-06-30', false);
        $kelasArsip = Kelas::create(['tahun_pelajaran_id' => $arsip->id, 'nama' => 'VII.A Arsip', 'tingkat' => 7, 'aktif' => false]);
        AnggotaKelas::create(['tahun_pelajaran_id' => $arsip->id, 'kelas_id' => $kelasArsip->id, 'siswa_id' => $siswa->id,
            'nomor_absen' => 2, 'status_keanggotaan' => 'aktif']);
        $guru = $this->user('guru_mapel');
        $this->komponen($this->penugasan($arsip, $kelasArsip, $guru, 'Bahasa Inggris'), 'Tugas dari arsip');
        $this->komponen($this->penugasan($tahun, $kelas, $guru, 'IPA'), 'Tugas tahun aktif');
        $this->actingAs($this->user('administrator'));

        $this->tab($siswa, 'akademik', ['tahun' => $arsip->id])->assertOk()
            ->assertSee('VII.A Arsip')->assertSee('Tugas dari arsip')->assertDontSee('Tugas tahun aktif');
        $this->tab($siswa, 'akademik', ['tahun' => $tahun->id])->assertOk()
            ->assertSee('Tugas tahun aktif')->assertDontSee('Tugas dari arsip');
    }

    public function test_teacher_sees_only_own_components_and_zero_is_not_missing(): void
    {
        [$tahun, $kelas, $siswa, , $siswaLain] = $this->data();
        $guru = $this->user('guru_mapel');
        $guruLain = $this->user('guru_mapel');
        $mapel = $this->penugasan($tahun, $kelas, $guru, 'Informatika');
        $asing = $this->penugasan($tahun, $kelas, $guruLain, 'Matematika');
        $nol = $this->komponen($mapel, 'Proyek nilai nol');
        $this->komponen($mapel, 'Proyek belum tercatat');
        $this->komponen($asing, 'Soal milik pengampu lain');
        NilaiSiswa::create(['komponen_nilai_id' => $nol->id, 'siswa_id' => $siswa->id, 'nilai' => 0]);
        $this->actingAs($guru);

        $this->get(route('profil-siswa-terpadu.index'))->assertOk()->assertSee($siswa->nama_lengkap)->assertDontSee($siswaLain->nama_lengkap);
        $akademik = $this->tab($siswa, 'akademik')->assertOk()->assertSee('Proyek nilai nol')->assertSee('Proyek belum tercatat')
            ->assertDontSee('Soal milik pengampu lain');
        if ($folder = getenv('NUSA_PROFIL_PREVIEW_PATH')) {
            file_put_contents($folder.'/profil-academic.html', $akademik->getContent());
        }
        $this->tab($siswa, 'akademik', ['kosong' => 1])->assertOk()->assertSee('Proyek belum tercatat')->assertDontSee('Proyek nilai nol');
        $this->tab($siswaLain, 'akademik')->assertNotFound();
        $this->tab($siswa, 'data')->assertForbidden();
        $this->tab($siswa, 'pembinaan')->assertForbidden();
        $this->tab($siswa, 'ibadah')->assertForbidden();
    }

    public function test_multiple_roles_do_not_grant_homeroom_privileges_for_students_only_taught_in_another_class(): void
    {
        [$tahun, $kelas, $siswa, $kelasLain, $siswaLain] = $this->data();
        $guru = $this->user('wali_kelas');
        $guru->daftarPeran()->attach(Peran::where('kode', 'guru_mapel')->firstOrFail());
        $kelas->update(['wali_kelas_id' => $guru->pegawai_id]);
        $this->penugasan($tahun, $kelasLain, $guru, 'Informatika');
        $this->actingAs($guru);

        $this->tab($siswa, 'presensi')->assertOk();
        $this->tab($siswa, 'data')->assertOk()->assertDontSee('1111111122222222')->assertDontSee('080000000121');
        $this->tab($siswaLain, 'akademik')->assertOk();
        $this->tab($siswaLain, 'presensi')->assertForbidden();
        $this->tab($siswaLain, 'pembinaan')->assertForbidden();
        $this->tab($siswaLain, 'data')->assertForbidden();
        $tahunLama = $this->buatTahun('2025/2026', '2025-07-01', '2026-06-30', false);
        $this->tab($siswa, 'ringkasan', ['tahun' => $tahunLama->id])->assertNotFound();
    }

    public function test_guru_wali_and_bk_are_scoped_and_only_case_review_roles_see_pending_case_statuses(): void
    {
        [$tahun, $kelas, $siswa, , $siswaLain] = $this->data();
        $wali = $this->user('guru_wali');
        PenugasanGuruWaliSiswa::create(['siswa_id' => $siswa->id, 'guru_wali_pegawai_id' => $wali->pegawai_id, 'tanggal_mulai' => '2026-07-01', 'aktif' => true]);
        $bk = $this->user('bk');
        foreach (['disahkan' => 'R-Terverifikasi', 'pemeriksaan_bk' => 'R-Diperiksa'] as $status => $nomor) {
            LaporanPembinaanSiswa::create(['siswa_id' => $siswa->id, 'kelas_id' => $kelas->id, 'tahun_pelajaran_id' => $tahun->id,
                'nomor_laporan' => $nomor, 'jenis_laporan' => 'pelanggaran', 'tanggal_kejadian' => '2026-08-10', 'status_verifikasi' => $status,
                'kronologi' => 'Kronologi Privat 8377', 'catatan_rahasia' => 'Konseling Privat 8271', 'total_poin' => 15]);
        }
        $this->actingAs($wali);
        $this->tab($siswa, 'pembinaan')->assertOk()->assertSee('R-Terverifikasi')->assertDontSee('R-Diperiksa')->assertDontSee('Konseling Privat 8271');
        $this->tab($siswaLain, 'ringkasan')->assertNotFound();
        $this->get(route('siswa-wali-saya.show', $siswa))->assertOk()->assertSee('Lihat profil terpadu');
        $this->actingAs($this->user('administrator'));
        $this->tab($siswa, 'pembinaan')->assertOk()->assertDontSee('R-Diperiksa')->assertDontSee('Kronologi Privat 8377');
        $this->actingAs($bk);
        $this->tab($siswa, 'ringkasan')->assertNotFound();
        PenugasanGuruBkTingkat::create(['pegawai_id' => $bk->pegawai_id, 'tahun_pelajaran_id' => $tahun->id,
            'tingkat' => $kelas->tingkat, 'tanggal_mulai' => '2026-07-01', 'aktif' => true]);
        $this->tab($siswa, 'pembinaan')->assertOk()->assertSee('R-Diperiksa')->assertSee('Pemeriksaan BK')
            ->assertDontSee('Konseling Privat 8271')->assertDontSee('Kronologi Privat 8377');
        $this->tab($siswaLain, 'ringkasan')->assertNotFound();
    }

    public function test_presensi_renders_separate_scan_and_manual_sources_with_actor_and_audit_time(): void
    {
        [$tahun, $kelas, $siswa] = $this->data();
        $admin = $this->user('administrator');
        $tanggal = '2026-08-13';
        $absensi = AbsensiSiswa::create(['tanggal' => $tanggal, 'siswa_id' => $siswa->id, 'kelas_id' => $kelas->id, 'tahun_pelajaran_id' => $tahun->id,
            'anggota_kelas_id' => $siswa->anggotaKelas()->firstOrFail()->id, 'status_kehadiran' => 'hadir', 'jam_masuk' => '06:22:00',
            'jam_pulang' => '11:25:00', 'sumber' => 'scan', 'menit_terlambat' => 5,
            'sanksi_manual_menit' => 5, 'alasan_hadir_manual' => 'tidak_membawa_kartu']);
        LogScanAbsensi::create(['tanggal' => $tanggal, 'siswa_id' => $siswa->id, 'absensi_siswa_id' => $absensi->id,
            'jenis_scan' => 'pulang', 'waktu_scan' => $tanggal.' 11:25:00', 'berhasil' => true, 'status_scan' => 'berhasil', 'isi_scan' => 'KODE-KARTU-PRIVAT']);
        $this->travelTo(now()->setDate(2026, 8, 13)->setTime(9, 48));
        RiwayatPerubahanAbsensiSiswa::create(['tanggal' => $tanggal, 'siswa_id' => $siswa->id, 'absensi_siswa_id' => $absensi->id,
            'sumber' => 'koreksi_manual', 'status_sesudah' => 'hadir', 'dibuat_oleh_pengguna_id' => $admin->id]);
        $this->actingAs($admin);
        $this->tab($siswa, 'presensi')->assertOk()->assertSeeInOrder(['06:22', 'Input manual', '5 menit tercatat (sanksi tanpa kartu)', '11:25', 'Scanner', '13/08/2026 09:48'])
            ->assertDontSee('KODE-KARTU-PRIVAT');
    }

    public function test_tahfidz_mentor_has_access_only_to_their_participants(): void
    {
        [$tahun, , $siswa, , $siswaLain] = $this->data();
        $guru = $this->user('pegawai');
        $kelompok = KelompokTahfidz::create(['tahun_pelajaran_id' => $tahun->id, 'pembimbing_pegawai_id' => $guru->pegawai_id, 'nama' => 'Kelompok Juz 30', 'aktif' => true]);
        $anggota = $kelompok->anggota()->forceCreate(['siswa_id' => $siswa->id, 'aktif' => true]);
        $pertemuan = $kelompok->pertemuan()->create(['tanggal' => '2026-08-18']);
        foreach ([90, 95] as $nilai) {
            $pertemuan->setoran()->create(['anggota_tahfidz_id' => $anggota->id, 'nomor_surah' => 112,
                'penilai_id' => $guru->id, 'nilai' => $nilai, 'token_setoran' => fake()->uuid()]);
        }
        $this->actingAs($guru);
        $this->tab($siswa, 'tahfidz')->assertOk()->assertSee('Al-Ikhlas')->assertSee('95')->assertSee('90')
            ->assertSee('Belum diverifikasi')->assertSee('36');
        $this->tab($siswa, 'akademik')->assertForbidden();
        $this->tab($siswaLain, 'tahfidz')->assertNotFound();
    }

    private function tab(Siswa $siswa, string $tab, array $extra = []): TestResponse
    {
        return $this->get(route('profil-siswa-terpadu.show', ['siswa' => $siswa, 'tab' => $tab, 'semester' => 'ganjil', ...$extra]));
    }

    private function data(): array
    {
        $tahun = $this->buatTahun('2026/2027', '2026-07-01', '2027-06-30', true);
        $kelas = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'VIII.A Simulasi', 'tingkat' => 8, 'aktif' => true]);
        $kelasLain = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => 'IX.B Simulasi', 'tingkat' => 9, 'aktif' => true]);
        $siswa = Siswa::create(['nama_lengkap' => 'Murid Contoh Satu', 'nis' => '6111', 'nisn' => '0012345678',
            'nik' => '1111111122222222', 'nomor_wa_ayah' => '080000000121', 'aktif' => true]);
        $siswaLain = Siswa::create(['nama_lengkap' => 'Murid Contoh Dua', 'nis' => '6112', 'aktif' => true]);
        foreach ([[$siswa, $kelas], [$siswaLain, $kelasLain]] as [$murid, $k]) {
            AnggotaKelas::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $k->id, 'siswa_id' => $murid->id,
                'nomor_absen' => 1, 'status_keanggotaan' => 'aktif', 'tanggal_masuk' => '2026-07-01']);
        }

        return [$tahun, $kelas, $siswa, $kelasLain, $siswaLain];
    }

    private function buatTahun(string $nama, string $mulai, string $selesai, bool $aktif): TahunPelajaran
    {
        return TahunPelajaran::create(['nama' => $nama, 'tanggal_mulai' => $mulai, 'tanggal_selesai' => $selesai, 'aktif' => $aktif]);
    }

    private function user(string $role, ?bool $denganPegawai = true): Pengguna
    {
        static $urut = 0;
        $urut++;
        $pegawai = $denganPegawai && $role !== 'administrator' ? Pegawai::create(['nama_lengkap' => "Petugas Uji $urut", 'aktif' => true]) : null;
        $user = Pengguna::create(['pegawai_id' => $pegawai?->id, 'nama' => "Petugas Uji $urut", 'username' => "profil-uji-$urut",
            'kata_sandi' => 'lokal-uji-sandi', 'peran' => $pegawai ? 'pegawai' : $role, 'aktif' => true, 'wajib_ganti_kata_sandi' => false]);
        $user->daftarPeran()->attach(Peran::where('kode', $role)->firstOrFail());

        return $user;
    }

    private function penugasan(TahunPelajaran $tahun, Kelas $kelas, Pengguna $guru, string $nama): GuruMataPelajaran
    {
        $mapel = MataPelajaran::create(['nama' => $nama, 'aktif' => true]);

        return GuruMataPelajaran::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id,
            'pegawai_id' => $guru->pegawai_id, 'mata_pelajaran_id' => $mapel->id, 'jenis_penugasan' => 'pengampu', 'aktif' => true]);
    }

    private function komponen(GuruMataPelajaran $mapel, string $nama): KomponenNilai
    {
        return $mapel->komponenNilai()->create(['semester' => 'ganjil', 'jenis_komponen' => 'formatif', 'nama' => $nama, 'aktif' => true, 'urutan' => 1]);
    }
}
