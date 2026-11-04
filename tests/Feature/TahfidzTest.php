<?php

namespace Tests\Feature;

use App\Jobs\KirimPushNotification;
use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\KelompokTahfidz;
use App\Models\KelulusanTahfidz;
use App\Models\NotifikasiPengguna;
use App\Models\OrangTuaWali;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\PublikasiRaporTahfidz;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TargetRaporTahfidz;
use App\Models\WisudaTahfidz;
use App\Services\Notifikasi\IsiPushNotifikasiService;
use App\Services\Tahfidz\AksesTahfidz;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class TahfidzTest extends TestCase
{
    use RefreshDatabase;

    public function test_koordinator_membuat_kelompok_dan_memilih_siswa_lintas_tingkat(): void
    {
        [$tahun, $siswa] = $this->sekolah();
        $guru = $this->akunPegawai('Pembimbing Tahfidz');
        $koordinator = $this->akunPegawai('Koordinator Uji');
        $role = Peran::where('kode', 'koordinator_tahfidz')->sole();
        $this->actingAs($this->admin())->patch(route('akun-pegawai.peran.update', $koordinator), [
            'peran_ids' => [$role->id],
        ])->assertSessionHasNoErrors();
        $this->get(route('tahfidz.index'))->assertOk()->assertSee('data-sidebar-search-text="akademik penilaian tahfidz"', false);
        $this->assertTrue($koordinator->fresh()->memilikiIzin('tahfidz.kelola'));
        $this->actingAs($koordinator)->get(route('beranda'))->assertOk()->assertSee('data-sidebar-search-text="akademik penilaian tahfidz"', false);
        $this->capture('empty', $this->get(route('tahfidz.index'))->assertOk()->assertSeeText('Belum ada kelompok'));
        $this->capture('form', $this->get(route('tahfidz.create'))->assertOk()->assertSeeText('Pembimbing Tahfidz'));

        $this->post(route('tahfidz.store'), $this->isian($tahun, $guru))->assertSessionHasNoErrors();
        $kelompok = KelompokTahfidz::sole();
        $this->assertSame($guru->pegawai_id, $kelompok->pembimbing_pegawai_id);
        $this->assertSame($tahun->id, $kelompok->tahun_pelajaran_id);
        $this->get(route('tahfidz.anggota.edit', $kelompok))->assertOk()->assertSeeText('Tingkat 7')->assertSeeText('Tingkat 8')->assertSeeText('Tingkat 9');

        $this->putAnggota($kelompok, [$siswa[0]->id, $siswa[1]->id])->assertSessionHasNoErrors()->assertRedirect(route('tahfidz.show', $kelompok));
        $kelompok->refresh();
        $this->assertSame(2, $kelompok->versi);
        $this->assertSame([$siswa[0]->id, $siswa[1]->id], $kelompok->anggotaAktif()->orderBy('siswa_id')->pluck('siswa_id')->all());
        $this->capture('detail', $this->get(route('tahfidz.show', $kelompok))->assertOk()
            ->assertSeeText('Peserta Tujuh')->assertSeeText('Peserta Delapan')->assertDontSeeText('Peserta Sembilan')->assertSeeText('Atur anggota'));

        $lain = $this->kelompok($tahun, $guru, 'Kelompok An-Nas');
        $this->putAnggota($lain, [$siswa[2]->id])->assertSessionHasNoErrors();
        $this->capture('anggota', $this->get(route('tahfidz.anggota.edit', $kelompok))->assertOk()->assertSeeText('Anggota Kelompok An-Nas'));
        $this->capture('index', $this->get(route('tahfidz.index'))->assertOk()->assertSeeText('Kelompok Al-Ikhlas')->assertSeeText('2 siswa'));
        $this->capture('surah', $this->get(route('tahfidz.surah'))->assertOk()->assertSeeText('37 surah')->assertSeeText('Al-Ikhlas'));
    }

    public function test_pembimbing_hanya_membaca_kelompok_sendiri_dan_akses_ikut_penugasan(): void
    {
        [$tahun, $siswa] = $this->sekolah();
        $guru = $this->akunPegawai('Guru Pemilik');
        $guruLain = $this->akunPegawai('Guru Lain');
        $tanpaTugas = $this->akunPegawai('Guru Tanpa Tugas');
        $sendiri = $this->kelompok($tahun, $guru, 'Kelompok Sendiri');
        $lain = $this->kelompok($tahun, $guruLain, 'Kelompok Rahasia');
        $this->actingAs($this->koordinator());
        $this->putAnggota($sendiri, [$siswa[0]->id])->assertSessionHasNoErrors();
        $this->putAnggota($lain, [$siswa[1]->id])->assertSessionHasNoErrors();

        $this->actingAs($guru)->get(route('beranda'))->assertOk()->assertSee('data-sidebar-search-text="akademik penilaian tahfidz"', false);
        $this->get(route('tahfidz.index'))->assertOk()->assertSeeText('Kelompok Sendiri')->assertDontSeeText('Kelompok Rahasia')->assertDontSeeText('Tambah kelompok');
        $this->capture('pembimbing', $this->get(route('tahfidz.show', $sendiri))->assertOk()->assertSeeText('Peserta Tujuh')
            ->assertDontSeeText('Peserta Delapan')->assertDontSeeText('Atur anggota')->assertDontSeeText('Edit kelompok'));
        $this->get(route('tahfidz.show', $lain))->assertNotFound();
        $this->get(route('tahfidz.anggota.edit', $sendiri))->assertForbidden();
        $this->get(route('tahfidz.edit', $sendiri))->assertForbidden();
        $this->get(route('tahfidz.create'))->assertForbidden();
        $this->post(route('tahfidz.store'), $this->isian($tahun, $guru))->assertForbidden();
        $this->put(route('tahfidz.update', $sendiri), [])->assertForbidden();
        $this->putAnggota($sendiri->fresh(), [])->assertForbidden();
        $this->get(route('tahfidz.surah'))->assertOk();

        $this->actingAs($tanpaTugas)->get(route('beranda'))->assertOk()->assertDontSee(route('tahfidz.index'), false);
        $this->get(route('tahfidz.index'))->assertForbidden();
        $this->get(route('tahfidz.surah'))->assertForbidden();

        $this->actingAs($this->admin())->put(route('tahfidz.update', $sendiri), [
            'nama' => $sendiri->nama, 'pembimbing_pegawai_id' => $tanpaTugas->pegawai_id, 'aktif' => 1, 'versi' => $sendiri->fresh()->versi,
        ])->assertSessionHasNoErrors();
        $this->actingAs($guru)->get(route('tahfidz.show', $sendiri))->assertForbidden();
        $this->actingAs($tanpaTugas)->get(route('tahfidz.show', $sendiri))->assertOk()->assertSeeText('Peserta Tujuh');
        $this->assertDatabaseCount('anggota_tahfidz', 2);
    }

    public function test_validasi_kelompok_dan_anggota_menolak_data_di_luar_cakupan(): void
    {
        [$tahun, $siswa] = $this->sekolah();
        [$tahunLain, $siswaLain] = $this->sekolah('2025/2026', false);
        $guru = $this->akunPegawai('Pembimbing Valid');
        $nonGuru = $this->akunPegawai('Pegawai Tata Usaha', 'TU');
        $nonaktif = $this->akunPegawai('Guru Nonaktif');
        $nonaktif->pegawai->update(['aktif' => false]);
        $kelompok = $this->kelompok($tahun, $guru);
        $this->actingAs($this->admin());
        foreach ([$nonGuru, $nonaktif] as $akun) {
            $this->post(route('tahfidz.store'), $this->isian($tahun, $akun))->assertSessionHasErrors('pembimbing_pegawai_id');
        }
        $this->post(route('tahfidz.store'), $this->isian($tahun, $guru))->assertSessionHasErrors('nama');
        $this->post(route('tahfidz.store'), $this->isian($tahunLain, $guru))->assertSessionHasNoErrors();
        $this->get(route('tahfidz.index', ['tahun' => $tahunLain->id]))->assertOk()->assertViewHas('kelompok', fn ($items) => $items->sole()->tahun_pelajaran_id === $tahunLain->id);
        $this->put(route('tahfidz.update', $kelompok), [...$this->isian($tahunLain, $guru), 'versi' => 1])->assertSessionHasErrors('tahun_pelajaran_id');

        $siswa[2]->update(['aktif' => false]);
        $this->get(route('tahfidz.anggota.edit', $kelompok))->assertOk()->assertDontSeeText('Peserta Sembilan');
        $this->putAnggota($kelompok, [$siswa[0]->id, $siswaLain[0]->id])->assertSessionHasErrors('siswa_ids');
        $this->putAnggota($kelompok, [$siswa[2]->id])->assertSessionHasErrors('siswa_ids');
        $this->putAnggota($kelompok, [$siswa[0]->id, $siswa[0]->id])->assertSessionHasErrors('siswa_ids.0');
        $this->assertDatabaseCount('anggota_tahfidz', 0);

        $this->putAnggota($kelompok, [$siswa[0]->id])->assertSessionHasNoErrors();
        $this->putAnggota($kelompok, [$siswa[1]->id])->assertSessionHasErrors('versi');
        $this->put(route('tahfidz.anggota.update', $kelompok), ['versi' => 2])->assertSessionHasErrors('daftar_lengkap');
        $this->assertSame([$siswa[0]->id], $kelompok->anggotaAktif()->pluck('siswa_id')->all());
    }

    public function test_anggota_tidak_rangkap_dan_kelompok_nonaktif_menyimpan_daftar_lama(): void
    {
        [$tahun, $siswa] = $this->sekolah();
        $guru = $this->akunPegawai('Guru Kelompok');
        $a = $this->kelompok($tahun, $guru, 'Kelompok A');
        $b = $this->kelompok($tahun, $guru, 'Kelompok B');
        $siswaId = $siswa[0]->id;
        $this->actingAs($this->koordinator());
        $this->putAnggota($a, [$siswaId])->assertSessionHasNoErrors();
        $this->putAnggota($b, [$siswaId])->assertSessionHasErrors('siswa_ids');
        $this->ubahStatus($a->fresh(), false)->assertSessionHasNoErrors();
        $this->get(route('tahfidz.show', $a))->assertOk()->assertSeeText('Nonaktif')->assertSeeText('Peserta Tujuh')->assertDontSeeText('Atur anggota');
        $this->get(route('tahfidz.anggota.edit', $a))->assertForbidden();
        $this->putAnggota($a->fresh(), [])->assertSessionHasErrors('siswa_ids');
        $this->putAnggota($b, [$siswaId])->assertSessionHasNoErrors();
        $this->ubahStatus($a->fresh(), true)->assertSessionHasErrors('siswa_ids');
        $this->assertFalse($a->fresh()->aktif);

        $anggotaB = $b->anggotaAktif()->sole();
        $this->putAnggota($b->fresh(), [])->assertSessionHasNoErrors();
        $this->assertFalse($anggotaB->fresh()->aktif);
        $this->ubahStatus($a->fresh(), true)->assertSessionHasNoErrors();
        $this->assertTrue($a->fresh()->aktif);
        $this->putAnggota($a->fresh(), [])->assertSessionHasNoErrors();
        $this->putAnggota($b->fresh(), [$siswaId])->assertSessionHasNoErrors();
        $this->assertTrue($anggotaB->fresh()->aktif);
        $this->assertDatabaseCount('anggota_tahfidz', 2);
    }

    public function test_master_surah_lengkap_dan_izin_tidak_memberi_akses_kepada_siswa_atau_orang_tua(): void
    {
        $this->get(route('tahfidz.index'))->assertRedirect(route('login'));
        [$tahun, $siswa] = $this->sekolah();
        $role = Peran::where('kode', 'koordinator_tahfidz')->sole();
        $this->assertSame(['tahfidz.kelola'], $role->izin()->pluck('kode')->all());
        $this->assertSame(range(78, 114), DB::table('surah_tahfidz')->orderBy('nomor')->pluck('nomor')->all());
        $this->assertDatabaseHas('surah_tahfidz', ['nomor' => 112, 'nama' => 'Al-Ikhlas']);
        $pelajar = Pengguna::create(['nama' => 'Pelajar Tahfidz', 'username' => 'tahfidz.siswa', 'siswa_id' => $siswa[0]->id,
            'kata_sandi' => 'UjiTahfidz2026', 'peran' => 'siswa', 'aktif' => true]);
        $orangTua = Pengguna::create(['nama' => 'Orang Tua Tahfidz', 'username' => 'tahfidz.ortu',
            'kata_sandi' => 'UjiTahfidz2026', 'peran' => 'orang_tua', 'aktif' => true]);
        OrangTuaWali::create(['pengguna_id' => $orangTua->id, 'siswa_acuan_username_id' => $siswa[0]->id, 'nama_lengkap' => 'Orang Tua Tahfidz']);
        foreach ([$pelajar, $orangTua] as $akun) {
            $akun->daftarPeran()->attach($role);
            $this->actingAs($akun)->get(route('tahfidz.index'))->assertForbidden();
            $this->post(route('tahfidz.store'), [])->assertForbidden();
        }
        $koordinator = $this->koordinator();
        $koordinator->pegawai->update(['aktif' => false]);
        $this->assertFalse(app(AksesTahfidz::class)->lihat($koordinator->fresh()));
    }

    public function test_pembimbing_mencatat_setoran_ulang_tanpa_menimpa_riwayat(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 4)->setTime(9, 0));
        [$tahun, $siswa] = $this->sekolah();
        $tahun->update(['tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30']);
        $guru = $this->akunPegawai('Guru Penilai');
        $kelompok = $this->kelompok($tahun, $guru);
        $this->actingAs($this->admin());
        $this->putAnggota($kelompok, [$siswa[0]->id, $siswa[1]->id])->assertSessionHasNoErrors();
        $anggota = $kelompok->anggotaAktif()->where('siswa_id', $siswa[0]->id)->sole();
        $this->actingAs($guru)->get(route('tahfidz.show', $kelompok))->assertOk()->assertSeeText('Buka pertemuan');
        $urlBuka = route('tahfidz.pertemuan.store', $kelompok);
        $this->post($urlBuka, ['tanggal' => '2026-11-05'])->assertSessionHasErrors('tanggal');
        $this->post($urlBuka, ['tanggal' => '2026-06-30'])->assertSessionHasErrors('tanggal');
        $this->post($urlBuka, ['tanggal' => '2026-10-28'])->assertSessionHasNoErrors();
        $pertama = $kelompok->pertemuan()->sole();
        $this->post($urlBuka, ['tanggal' => '2026-10-28'])->assertRedirect(route('tahfidz.pertemuan.show', [$kelompok, $pertama]));
        $this->assertDatabaseCount('pertemuan_tahfidz', 1);

        $payload = ['anggota_tahfidz_id' => $anggota->id, 'nomor_surah' => 112, 'nilai' => '85,50', 'catatan' => 'Setoran pertama', 'token_setoran' => (string) Str::uuid()];
        $urlNilai = route('tahfidz.setoran.store', [$kelompok, $pertama]);
        $this->post($urlNilai, $payload)->assertSessionHasNoErrors();
        $this->post($urlNilai, $payload)->assertSessionHasNoErrors();
        $this->post($urlNilai, [...$payload, 'nilai' => 90])->assertSessionHasErrors('token_setoran');
        $this->assertDatabaseCount('setoran_tahfidz', 1);

        $this->post($urlBuka, ['tanggal' => '2026-11-04'])->assertSessionHasNoErrors();
        $kedua = $kelompok->pertemuan()->latest('tanggal')->firstOrFail();
        $urlNilai = route('tahfidz.setoran.store', [$kelompok, $kedua]);
        foreach ([90, 100, 0] as $nilai) {
            $this->post($urlNilai, [...$payload, 'nilai' => $nilai, 'catatan' => 'Setoran ulang', 'token_setoran' => (string) Str::uuid()])->assertSessionHasNoErrors();
        }
        $this->assertSame(['85.50', '90.00', '100.00', '0.00'], $anggota->setoran()->orderBy('id')->get()->pluck('nilai')->all());
        $this->assertSame(3, $kedua->setoran()->count());
        $this->assertSame($guru->id, $pertama->setoran()->sole()->penilai_id);
        $this->capture('pertemuan', $this->get(route('tahfidz.pertemuan.show', [$kelompok, $kedua]))->assertOk()
            ->assertSeeText('Catat setoran')->assertSeeText('3 setoran')->assertDontSeeText('Peserta Sembilan'));
        $this->capture('jadwal', $this->get(route('tahfidz.show', $kelompok))->assertOk()->assertSeeText('2 pertemuan'));
        $urlRiwayat = route('tahfidz.anggota.riwayat', [$kelompok, $anggota]);
        $this->capture('riwayat', $this->get($urlRiwayat)->assertOk()->assertSeeText('85,5')->assertSeeText('Setoran ulang')
            ->assertViewHas('setoran', fn ($setoran) => $setoran->first()->pertemuan_tahfidz_id === $kedua->id));

        $this->actingAs($this->admin());
        $this->putAnggota($kelompok->fresh(), [$siswa[1]->id])->assertSessionHasNoErrors();
        $this->get($urlRiwayat)->assertOk()->assertSeeText('4 setoran');
        $this->assertDatabaseCount('setoran_tahfidz', 4);
    }

    public function test_setoran_menolak_nilai_dan_cakupan_yang_tidak_sah(): void
    {
        [$tahun, $siswa] = $this->sekolah();
        $guru = $this->akunPegawai('Guru Setoran A');
        $guruLain = $this->akunPegawai('Guru Setoran B');
        $a = $this->kelompok($tahun, $guru, 'Kelompok A');
        $b = $this->kelompok($tahun, $guruLain, 'Kelompok B');
        $this->actingAs($this->admin());
        $this->putAnggota($a, [$siswa[0]->id])->assertSessionHasNoErrors();
        $this->putAnggota($b, [$siswa[1]->id])->assertSessionHasNoErrors();
        $anggota = $a->anggotaAktif()->sole();
        $anggotaLain = $b->anggotaAktif()->sole();
        $sesi = $a->pertemuan()->create(['tanggal' => today()->toDateString()]);
        $sesiLain = $b->pertemuan()->create(['tanggal' => today()->toDateString()]);
        $urlNilai = route('tahfidz.setoran.store', [$a, $sesi]);
        $payload = ['anggota_tahfidz_id' => $anggota->id, 'nomor_surah' => 112, 'nilai' => 90, 'token_setoran' => (string) Str::uuid()];

        foreach ([$this->admin(), $this->koordinator()] as $akun) {
            $this->actingAs($akun)->get(route('tahfidz.pertemuan.show', [$a, $sesi]))->assertOk()->assertDontSeeText('Catat setoran');
            $this->post($urlNilai, $payload)->assertForbidden();
            $this->post(route('tahfidz.pertemuan.store', $a), ['tanggal' => today()->toDateString()])->assertForbidden();
        }
        $this->actingAs($guruLain)->get(route('tahfidz.pertemuan.show', [$a, $sesi]))->assertNotFound();
        $this->post($urlNilai, $payload)->assertNotFound();
        $this->actingAs($guru)->post(route('tahfidz.setoran.store', [$a, $sesiLain]), $payload)->assertNotFound();
        $this->get(route('tahfidz.anggota.riwayat', [$a, $anggotaLain]))->assertNotFound();
        $this->post($urlNilai, [...$payload, 'anggota_tahfidz_id' => $anggotaLain->id])->assertSessionHasErrors('anggota_tahfidz_id');
        $this->post($urlNilai, [...$payload, 'nomor_surah' => 1])->assertSessionHasErrors('nomor_surah');
        foreach (['', -1, 101, '90,123', '9e1'] as $nilai) {
            $this->post($urlNilai, [...$payload, 'nilai' => $nilai])->assertSessionHasErrors('nilai');
        }
        $siswa[0]->update(['aktif' => false]);
        $this->post($urlNilai, $payload)->assertSessionHasErrors('anggota_tahfidz_id');
        $siswa[0]->update(['aktif' => true]);
        $a->update(['aktif' => false]);
        $this->post($urlNilai, $payload)->assertForbidden();
        $this->get(route('tahfidz.pertemuan.show', [$a, $sesi]))->assertOk()->assertDontSeeText('Catat setoran');
        $a->update(['aktif' => true, 'pembimbing_pegawai_id' => $guruLain->pegawai_id]);
        $this->post($urlNilai, $payload)->assertForbidden();
        $this->assertDatabaseCount('setoran_tahfidz', 0);
    }

    public function test_rekap_menghitung_surah_unik_dan_menampilkan_nilai_terbaru_berdasarkan_tanggal(): void
    {
        [$tahun, $siswa] = $this->sekolah();
        $guru = $this->akunPegawai('Guru Rekap');
        $kelompok = $this->kelompok($tahun, $guru);
        $this->actingAs($this->admin());
        $this->putAnggota($kelompok, collect($siswa)->pluck('id')->all())->assertSessionHasNoErrors();
        $anggota = $kelompok->anggotaAktif()->orderBy('siswa_id')->get();
        $awal = $kelompok->pertemuan()->create(['tanggal' => '2026-10-28']);
        $akhir = $kelompok->pertemuan()->create(['tanggal' => '2026-11-04']);
        $dataSetoran = fn ($peserta, int $nomor, string $nilai) => [
            'anggota_tahfidz_id' => $peserta->id, 'nomor_surah' => $nomor, 'nilai' => $nilai,
            'penilai_id' => $guru->id, 'token_setoran' => (string) Str::uuid(),
        ];
        $akhir->setoran()->createMany([
            $dataSetoran($anggota[0], 112, '90'),
            $dataSetoran($anggota[0], 112, '92.25'),
            $dataSetoran($anggota[0], 114, '0'),
        ]);
        $awal->setoran()->create($dataSetoran($anggota[0], 112, '85.50'));
        $akhir->setoran()->createMany(array_map(fn ($nomor) => $dataSetoran($anggota[2], $nomor, '95'), range(78, 114)));

        $urlKelompok = route('tahfidz.rekap.kelompok', $kelompok);
        $urlSiswa = route('tahfidz.rekap.siswa', [$kelompok, $anggota[0]]);
        $this->actingAs($guru)->get(route('tahfidz.show', $kelompok))->assertOk()->assertSee($urlKelompok, false);
        $this->capture('rekapkelompok', $this->get($urlKelompok)->assertOk()
            ->assertViewHasAll(['target' => 37, 'jumlahSiswa' => 3, 'sudahMulai' => 2, 'lengkap' => 1])
            ->assertViewHas('baris', fn ($baris) => $baris->firstWhere('anggota.id', $anggota[0]->id)['setoran'] === 4)
            ->assertSeeText('2/37')->assertSeeText('0/37')->assertSeeText('37/37'));
        $this->get($urlKelompok.'?status=belum&cari=delapan')->assertOk()
            ->assertViewHas('baris', fn ($baris) => $baris->count() === 1 && $baris->first()['anggota']->id === $anggota[1]->id)
            ->assertViewHas('jumlahSiswa', 3);
        $this->get($urlKelompok.'?status=lengkap')->assertOk()->assertDontSeeText('Peserta Delapan')->assertSeeText('Peserta Sembilan');

        $this->capture('rekapsiswa', $this->get($urlSiswa)->assertOk()
            ->assertViewHasAll(['target' => 37, 'sudah' => 2, 'jumlahSetoran' => 4])
            ->assertViewHas('baris', fn ($baris) => $baris->count() === 37
                && $baris->firstWhere('surah.nomor', 112)['terbaru']->nilai === '92.25'
                && (int) $baris->firstWhere('surah.nomor', 112)['terbaru']->jumlah_setoran === 3)
            ->assertSeeText('92,25')->assertDontSeeText('85,5'));
        $this->capture('rekapsudah', $this->get($urlSiswa.'?status=sudah')->assertOk()
            ->assertViewHas('baris', fn ($baris) => $baris->count() === 2 && $baris->firstWhere('surah.nomor', 114)['terbaru']->nilai === '0.00'));
        $this->capture('rekapbelum', $this->get($urlSiswa.'?status=belum')->assertOk()
            ->assertViewHas('baris', fn ($baris) => $baris->count() === 35 && $baris->every(fn ($item) => $item['terbaru'] === null)));
        $this->get(route('tahfidz.rekap.siswa', [$kelompok, $anggota[1]]).'?status=sudah')->assertOk()->assertSeeText('Belum ada surah yang disetor.');
        $this->get(route('tahfidz.rekap.siswa', [$kelompok, $anggota[2]]).'?status=belum')->assertOk()->assertSeeText('Semua surah sudah disetor.');
        $this->assertDatabaseCount('setoran_tahfidz', 41);
    }

    public function test_rekap_tetap_terbatas_ke_kelompok_dan_menyimpan_akses_riwayat_anggota_lama(): void
    {
        [$tahun, $siswa] = $this->sekolah();
        $guru = $this->akunPegawai('Guru Rekap A');
        $guruLain = $this->akunPegawai('Guru Rekap B');
        $a = $this->kelompok($tahun, $guru, 'Rekap A');
        $b = $this->kelompok($tahun, $guruLain, 'Rekap B');
        $urlA = route('tahfidz.rekap.kelompok', $a);
        $this->get($urlA)->assertRedirect(route('login'));
        $this->actingAs($this->admin())->get($urlA)->assertOk()->assertSeeText('Belum ada anggota pada kelompok ini.');
        $this->putAnggota($a, [$siswa[0]->id])->assertSessionHasNoErrors();
        $lama = $a->anggotaAktif()->sole();
        $a->pertemuan()->create(['tanggal' => '2026-10-28'])->setoran()->create([
            'anggota_tahfidz_id' => $lama->id, 'nomor_surah' => 112, 'nilai' => 85, 'token_setoran' => (string) Str::uuid(),
        ]);
        $this->putAnggota($a->fresh(), [])->assertSessionHasNoErrors();
        $this->putAnggota($b, [$siswa[0]->id])->assertSessionHasNoErrors();
        $baru = $b->anggotaAktif()->sole();
        $b->pertemuan()->create(['tanggal' => '2026-11-04'])->setoran()->create([
            'anggota_tahfidz_id' => $baru->id, 'nomor_surah' => 114, 'nilai' => 99, 'token_setoran' => (string) Str::uuid(),
        ]);

        $urlLama = route('tahfidz.rekap.siswa', [$a, $lama]);
        $this->actingAs($guru)->get($urlA)->assertOk()->assertViewHas('jumlahSiswa', 0);
        $this->get($urlLama)->assertOk()->assertViewHasAll(['sudah' => 1, 'jumlahSetoran' => 1])->assertSee('>85</strong>', false)->assertDontSee('>99</strong>', false);
        $this->get(route('tahfidz.rekap.siswa', [$a, $baru]))->assertNotFound();
        $this->get(route('tahfidz.rekap.kelompok', $b))->assertNotFound();
        $this->actingAs($guruLain)->get($urlLama)->assertNotFound();
        $this->get(route('tahfidz.rekap.siswa', [$b, $baru]))->assertOk()->assertSeeText('99');
        $this->actingAs($this->koordinator())->get($urlLama)->assertOk();
        $this->actingAs($this->akunPegawai('Pegawai Tanpa Tahfidz'))->get($urlA)->assertForbidden();
    }

    public function test_rapor_mendukung_target_tetap_dan_bebas_dengan_pembagi_tetap(): void
    {
        [$tahun, $siswa] = $this->sekolah();
        $tahun->update(['tanggal_mulai' => '2026-07-01', 'tanggal_selesai' => '2027-06-30']);
        $guru = $this->akunPegawai('Guru Rapor');
        $kelompok = $this->kelompok($tahun, $guru);
        $koordinator = $this->koordinator();
        $this->actingAs($koordinator);
        $this->putAnggota($kelompok, [$siswa[0]->id, $siswa[1]->id])->assertSessionHasNoErrors();
        $anggota = $kelompok->anggota()->orderBy('siswa_id')->get();
        $awal = $kelompok->pertemuan()->create(['tanggal' => '2026-10-28']);
        $akhir = $kelompok->pertemuan()->create(['tanggal' => '2026-11-04']);
        $lewat = $kelompok->pertemuan()->create(['tanggal' => '2026-11-05']);
        foreach ([[$awal, 112, 99], [$akhir, 112, 80], [$akhir, 113, 90], [$akhir, 114, 100], [$lewat, 111, 100]] as [$sesi, $surah, $nilai]) {
            $sesi->setoran()->create(['anggota_tahfidz_id' => $anggota[0]->id, 'nomor_surah' => $surah, 'nilai' => $nilai, 'token_setoran' => (string) Str::uuid()]);
        }
        $pengaturan = ['tahun_pelajaran_id' => $tahun->id, 'tingkat' => 7, 'mode' => 'tetap', 'target' => 4,
            'surah' => [111, 112, 113, 114], 'tanggal_rapor' => '2026-11-04', 'batas_cukup' => 60, 'batas_baik' => 80, 'batas_sangat_baik' => 90];
        $urlSimpan = route('tahfidz.rapor.simpan');
        $urlCetak = route('tahfidz.rapor.cetak', [$kelompok, $anggota[0]]);
        $this->actingAs($guru)->get($urlCetak)->assertRedirect()->assertSessionHasErrors('rapor');
        $this->post($urlSimpan, $pengaturan)->assertForbidden();
        $this->actingAs($koordinator)->post($urlSimpan, [...$pengaturan, 'target' => 3])->assertSessionHasErrors('surah');
        $this->post($urlSimpan, [...$pengaturan, 'surah' => [1, 112, 113, 114]])->assertSessionHasErrors('surah.0');
        $this->post($urlSimpan, [...$pengaturan, 'batas_baik' => 59])->assertSessionHasErrors('batas_baik');
        $this->post($urlSimpan, $pengaturan)->assertSessionHasNoErrors();
        $this->capture('targetrapor', $this->get(route('tahfidz.rapor.pengaturan', ['tahun' => $tahun->id, 'tingkat' => 7]))->assertOk());
        $this->capture('raporcetak', $this->actingAs($guru)->get($urlCetak)->assertOk()
            ->assertViewHasAll(['jumlah' => 270.0, 'rata' => 67.5, 'sudah' => 3, 'predikat' => 'Cukup'])
            ->assertSeeText('Belum disetor')->assertSeeText('67,50')->assertSeeText('VII.A'));

        $this->actingAs($koordinator)->post($urlSimpan, [...$pengaturan, 'mode' => 'bebas', 'target' => 2])->assertSessionHasNoErrors();
        $this->post($urlSimpan, [...$pengaturan, 'mode' => 'bebas', 'target' => 20, 'tingkat' => 8])->assertSessionHasNoErrors();
        $this->assertSame([], TargetRaporTahfidz::where('tingkat', 7)->sole()->surah);
        $this->actingAs($guru)->get($urlCetak)->assertOk()->assertViewHasAll(['jumlah' => 190.0, 'rata' => 95.0, 'sudah' => 2, 'predikat' => 'Sangat Baik'])
            ->assertViewHas('baris', fn ($baris) => $baris->pluck('surah.nomor')->all() === [113, 114]);
        $this->get(route('tahfidz.rapor.cetak', [$kelompok, $anggota[1]]))->assertOk()->assertViewHasAll(['rata' => null, 'sudah' => 0]);
        $this->actingAs($koordinator)->post($urlSimpan, [...$pengaturan, 'mode' => 'bebas', 'target' => 37])->assertSessionHasNoErrors();
        $this->capture('raporpanjang', $this->get($urlCetak)->assertOk()->assertViewHas('baris', fn ($baris) => $baris->count() === 37));
        $this->assertDatabaseCount('setoran_tahfidz', 5);
    }

    public function test_sertifikat_memerlukan_37_surah_verifikasi_dan_penerbitan_pada_acara(): void
    {
        [$tahun, $siswa] = $this->sekolah();
        [$tahunLama] = $this->sekolah('2025/2026', false);
        $guru = $this->akunPegawai('Pembimbing Wisuda');
        $guruLama = $this->akunPegawai('Pembimbing Lama');
        $koordinator = $this->koordinator();
        $kelompok = $this->kelompok($tahun, $guru);
        $lama = $this->kelompok($tahunLama, $guruLama);
        $this->actingAs($koordinator)->putAnggota($kelompok, [$siswa[0]->id])->assertSessionHasNoErrors();
        $anggota = $kelompok->anggotaAktif()->sole();
        $anggotaLama = DB::table('anggota_tahfidz')->insertGetId(['kelompok_tahfidz_id' => $lama->id, 'siswa_id' => $siswa[0]->id, 'aktif' => true]);
        $sesiLama = $lama->pertemuan()->create(['tanggal' => '2026-06-01']);
        $sesi = $kelompok->pertemuan()->create(['tanggal' => '2026-11-04']);
        $buat = fn ($sesi, int $anggota, int $nomor) => $sesi->setoran()->create([
            'anggota_tahfidz_id' => $anggota, 'nomor_surah' => $nomor, 'nilai' => 0, 'token_setoran' => (string) Str::uuid(),
        ]);
        foreach (range(78, 113) as $nomor) {
            $nomor < 100 ? $buat($sesiLama, $anggotaLama, $nomor) : $buat($sesi, $anggota->id, $nomor);
        }
        $buat($sesi, $anggota->id, 113);
        $verifikasi = route('tahfidz.wisuda.verifikasi', [$kelompok, $anggota]);
        $cetakSiswa = route('tahfidz.anggota.sertifikat', [$kelompok, $anggota]);
        $this->actingAs($guru)->post($verifikasi)->assertSessionHasErrors('kelulusan');
        $this->get($cetakSiswa)->assertNotFound();
        $buat($sesi, $anggota->id, 114);
        $this->actingAs($guruLama)->post(route('tahfidz.wisuda.verifikasi', [$lama, $anggotaLama]))->assertForbidden();
        $this->actingAs($koordinator)->post($verifikasi)->assertForbidden();
        $this->actingAs($guru)->get(route('tahfidz.rekap.siswa', [$kelompok, $anggota]))->assertOk()->assertSeeText('Verifikasi kelulusan');
        $this->post($verifikasi)->assertSessionHasNoErrors();
        $this->post($verifikasi)->assertSessionHasNoErrors();
        $lulus = KelulusanTahfidz::sole();
        $this->assertSame($guru->id, $lulus->pembimbing_id);
        $this->get($cetakSiswa)->assertNotFound();
        $this->get(route('tahfidz.wisuda.index'))->assertForbidden();

        $acara = ['nama' => 'Wisuda Tahfidz NUSA', 'tanggal' => '2026-12-12', 'lokasi' => 'Padang Panjang', 'kepala_sekolah' => 'Kepala Sekolah Uji'];
        $this->actingAs($koordinator)->post(route('tahfidz.wisuda.store'), $acara)->assertSessionHasNoErrors();
        $wisuda = WisudaTahfidz::sole();
        $urlTerbit = route('tahfidz.wisuda.terbitkan', $lulus);
        $this->capture('wisuda', $this->get(route('tahfidz.wisuda.index'))->assertOk()->assertSeeText('Terbitkan sertifikat'));
        $this->post($urlTerbit, ['wisuda_tahfidz_id' => $wisuda->id])->assertSessionHasNoErrors();
        $nomor = $lulus->fresh()->nomor_sertifikat;
        $this->post($urlTerbit, ['wisuda_tahfidz_id' => $wisuda->id])->assertSessionHasNoErrors();
        $this->assertSame($nomor, $lulus->fresh()->nomor_sertifikat);
        $this->put(route('tahfidz.wisuda.update', $wisuda), [...$acara, 'kepala_sekolah' => 'Nama diganti'])->assertSessionHasErrors('acara');
        $siswa[0]->update(['nama_lengkap' => 'Nama berubah setelah terbit']);
        $this->capture('sertifikat', $this->get(route('tahfidz.wisuda.sertifikat', $lulus))->assertOk()
            ->assertSeeText($nomor)->assertSeeText('Peserta Tujuh')->assertDontSeeText('Nama berubah setelah terbit')
            ->assertSeeText('Kepala Sekolah Uji')->assertSeeText('Pembimbing Wisuda'));
        $this->actingAs($guru)->get($cetakSiswa)->assertOk()->assertSeeText($nomor);
        $this->actingAs($guruLama)->get($cetakSiswa)->assertNotFound();
        $this->assertDatabaseCount('kelulusan_tahfidz', 1);
        $this->assertDatabaseCount('setoran_tahfidz', 38);
    }

    public function test_selesaikan_pertemuan_mengirim_satu_ringkasan_per_anak_dan_membuka_hanya_setoran_yang_selesai(): void
    {
        Queue::fake();
        config(['services.firebase.push_enabled' => true]);
        [$tahun, $siswa] = $this->sekolah();
        $guru = $this->akunPegawai('Guru Pertemuan Anak');
        $kelompok = $this->kelompok($tahun, $guru);
        $ortu = $this->akunOrangTua('wali-utama-tf', $siswa[0], $siswa[1]);
        $waliLain = $this->akunOrangTua('wali-kedua-tf', $siswa[0]);
        $bukanWali = $this->akunOrangTua('wali-kelompok-lain-tf', $siswa[2]);
        $nonaktif = $this->akunOrangTua('wali-nonaktif-tf', $siswa[0]);
        $nonaktif->update(['aktif' => false]);
        $this->actingAs($this->admin())->putAnggota($kelompok, [$siswa[0]->id, $siswa[1]->id])->assertSessionHasNoErrors();
        $anggota = $kelompok->anggotaAktif()->orderBy('siswa_id')->get();
        $sesi = $kelompok->pertemuan()->create(['tanggal' => '2026-11-04']);
        $draft = $kelompok->pertemuan()->create(['tanggal' => '2026-11-05']);
        $selesai = route('tahfidz.pertemuan.selesaikan', [$kelompok, $sesi]);
        $urlSimpan = route('tahfidz.setoran.store', [$kelompok, $sesi]);
        $urlAnak = route('tahfidz-anak.index', ['siswa' => $siswa[0]->id, 'status' => 'sudah']);
        $this->get($urlAnak)->assertForbidden();
        $this->actingAs($guru)->post($selesai)->assertSessionHasErrors('pertemuan');
        foreach ([[$anggota[0]->id, 112, 85], [$anggota[0]->id, 112, 90], [$anggota[1]->id, 114, 0]] as [$id, $surah, $nilai]) {
            $payload = ['anggota_tahfidz_id' => $id, 'nomor_surah' => $surah, 'nilai' => $nilai,
                'catatan' => 'Catatan privat Tahfidz', 'token_setoran' => (string) Str::uuid()];
            $this->post($urlSimpan, $payload)->assertSessionHasNoErrors();
        }
        $draft->setoran()->create(['anggota_tahfidz_id' => $anggota[0]->id, 'nomor_surah' => 112, 'nilai' => 97, 'token_setoran' => (string) Str::uuid()]);
        $this->assertDatabaseCount('notifikasi_pengguna', 0);
        Queue::assertNothingPushed();
        $this->actingAs($ortu)->get($urlAnak)->assertOk()->assertViewHas('sudah', 0)->assertSeeText('Belum ada setoran dari pertemuan yang selesai.');
        $this->post($selesai)->assertForbidden();
        $this->get(route('tahfidz-anak.index', ['siswa' => $siswa[2]->id]))->assertNotFound();
        $this->actingAs($bukanWali)->get($urlAnak)->assertNotFound();

        $this->actingAs($guru)->post($selesai)->assertSessionHasNoErrors();
        $this->post($selesai)->assertSessionHasNoErrors();
        $this->get(route('tahfidz.pertemuan.show', [$kelompok, $sesi]))->assertOk()->assertDontSeeText('Catat setoran')->assertSeeText('Dibagikan ke orang tua pada');
        $this->post($urlSimpan, [...$payload, 'token_setoran' => (string) Str::uuid()])->assertSessionHasErrors('pertemuan');
        $this->assertDatabaseCount('setoran_tahfidz', 4);
        $ringkasan = NotifikasiPengguna::where('kunci_unik', 'like', 'tahfidz-pertemuan:%')->get();
        $this->assertEqualsCanonicalizing([$ortu->id, $ortu->id, $waliLain->id], $ringkasan->pluck('pengguna_id')->all());
        $pertama = $ringkasan->firstWhere('tautan', '/tahfidz-anak?siswa='.$siswa[0]->id);
        $this->assertStringContainsString('menyetorkan 1 surah', $pertama->pesan);
        $this->assertStringNotContainsString($siswa[0]->nama_lengkap, app(IsiPushNotifikasiService::class)->untuk($pertama)['body']);
        Queue::assertPushed(KirimPushNotification::class, 3);

        $this->capture('anak', $this->actingAs($ortu)->get($urlAnak)->assertOk()->assertViewHas('sudah', 1)
            ->assertSeeText('Tahfidz Anak')->assertSee('>90</strong>', false)->assertDontSee('>97</strong>', false)
            ->assertDontSeeText('Catatan privat Tahfidz'));
        $this->get(route('tahfidz-anak.index', ['siswa' => $siswa[1]->id, 'status' => 'sudah']))->assertOk()
            ->assertViewHas('sudah', 1)->assertSee('>0</strong>', false);
    }

    public function test_publikasi_rapor_menyimpan_salinan_dan_membatasi_akses_orang_tua(): void
    {
        Queue::fake();
        config(['services.firebase.push_enabled' => true]);
        [$tahun, $siswa] = $this->sekolah();
        $guru = $this->akunPegawai('Guru Rapor Orang Tua');
        $kelompok = $this->kelompok($tahun, $guru);
        $ortu = $this->akunOrangTua('wali-rapor-tf', $siswa[0]);
        $lain = $this->akunOrangTua('wali-lain-rapor-tf', $siswa[1]);
        $koordinator = $this->koordinator();
        $this->actingAs($koordinator)->putAnggota($kelompok, [$siswa[0]->id])->assertSessionHasNoErrors();
        $anggota = $kelompok->anggotaAktif()->sole();
        TargetRaporTahfidz::create(['tahun_pelajaran_id' => $tahun->id, 'tingkat' => 7, 'target' => 2, 'mode' => 'bebas', 'surah' => [], 'tanggal_rapor' => '2026-11-05']);
        $sesi = $kelompok->pertemuan()->create(['tanggal' => '2026-11-04']);
        $buat = fn (int $nilai) => $sesi->setoran()->create(['anggota_tahfidz_id' => $anggota->id, 'nomor_surah' => 112, 'nilai' => $nilai, 'token_setoran' => (string) Str::uuid()]);
        $buat(80);
        $preview = route('tahfidz.rapor.cetak', [$kelompok, $anggota]);
        $bagikan = route('tahfidz.rapor.publikasi', [$kelompok, $anggota]);
        $sidik = $this->get($preview)->assertOk()->assertSeeText('Bagikan rapor ke orang tua')->viewData('sidik');
        $this->actingAs($ortu)->get(route('tahfidz-anak.index'))->assertOk()->assertDontSeeText('Buka rapor');
        $this->get($preview)->assertForbidden();
        $this->actingAs($guru)->post($bagikan, ['bagikan' => 1, 'sidik' => $sidik])->assertForbidden();
        $this->actingAs($koordinator)->post($bagikan, ['bagikan' => 1, 'sidik' => $sidik])->assertSessionHasNoErrors();
        $this->post($bagikan, ['bagikan' => 1, 'sidik' => $sidik])->assertSessionHasNoErrors();
        $rapor = PublikasiRaporTahfidz::sole();
        $this->assertSame(1, $rapor->versi);
        $url = route('tahfidz-anak.rapor', $rapor);
        $this->actingAs($lain)->get($url)->assertNotFound();
        $this->actingAs($ortu)->get($url)->assertOk()->assertSeeText('40,00')->assertDontSeeText('Tarik publikasi');

        $buat(90);
        $this->get($url)->assertOk()->assertSeeText('40,00')->assertDontSeeText('45,00');
        $this->actingAs($koordinator)->post($bagikan, ['bagikan' => 1, 'sidik' => $sidik])->assertSessionHasErrors('rapor');
        $sidikBaru = $this->get($preview)->assertOk()->viewData('sidik');
        $this->post($bagikan, ['bagikan' => 1, 'sidik' => $sidikBaru])->assertSessionHasNoErrors();
        $this->assertSame(2, $rapor->fresh()->versi);
        $this->capture('raporanak', $this->actingAs($ortu)->get($url)->assertOk()->assertSeeText('45,00'));
        $this->actingAs($koordinator)->post($bagikan, ['bagikan' => 0])->assertSessionHasNoErrors();
        $this->actingAs($ortu)->get($url)->assertNotFound();
        $this->assertSame([$ortu->id, $ortu->id], NotifikasiPengguna::where('kunci_unik', 'like', 'tahfidz-rapor:%')->pluck('pengguna_id')->all());
        Queue::assertPushed(KirimPushNotification::class, 2);
    }

    public function test_sertifikat_terbit_belum_terlihat_sampai_dibagikan_dan_dapat_ditarik(): void
    {
        Queue::fake();
        config(['services.firebase.push_enabled' => true]);
        [$tahun, $siswa] = $this->sekolah();
        $guru = $this->akunPegawai('Guru Sertifikat Orang Tua');
        $kelompok = $this->kelompok($tahun, $guru);
        $ortu = $this->akunOrangTua('wali-sertifikat-tf', $siswa[0]);
        $lain = $this->akunOrangTua('wali-lain-sertifikat-tf', $siswa[1]);
        $koordinator = $this->koordinator();
        $this->actingAs($koordinator)->putAnggota($kelompok, [$siswa[0]->id])->assertSessionHasNoErrors();
        $lulus = KelulusanTahfidz::create(['siswa_id' => $siswa[0]->id, 'anggota_tahfidz_id' => $kelompok->anggotaAktif()->sole()->id,
            'pembimbing_id' => $guru->id, 'nama_pembimbing' => $guru->nama, 'diverifikasi_pada' => now()]);
        $bagikan = route('tahfidz.wisuda.publikasi', $lulus);
        $cetak = route('tahfidz-anak.sertifikat', $lulus);
        $this->post($bagikan, ['bagikan' => 1])->assertSessionHasErrors('sertifikat');
        $acara = WisudaTahfidz::create(['nama' => 'Wisuda Tahfidz Uji', 'tanggal' => '2026-12-12', 'lokasi' => 'Padang Panjang', 'kepala_sekolah' => 'Kepala Sekolah Uji']);
        $lulus->update(['wisuda_tahfidz_id' => $acara->id, 'nomor_sertifikat' => 'THF/2026/00001', 'nama_penerima' => $siswa[0]->nama_lengkap,
            'penerbit_id' => $koordinator->id, 'diterbitkan_pada' => now()]);
        $this->actingAs($ortu)->get($cetak)->assertNotFound();
        $this->get(route('tahfidz-anak.index'))->assertOk()->assertDontSeeText('Buka sertifikat');
        $this->actingAs($guru)->post($bagikan, ['bagikan' => 1])->assertForbidden();
        $this->actingAs($koordinator)->post($bagikan, ['bagikan' => 1])->assertSessionHasNoErrors();
        $this->post($bagikan, ['bagikan' => 1])->assertSessionHasNoErrors();
        $this->actingAs($lain)->get($cetak)->assertNotFound();
        $this->capture('anakdokumen', $this->actingAs($ortu)->get(route('tahfidz-anak.index'))->assertOk()->assertSeeText('Buka sertifikat'));
        $this->get($cetak)->assertOk()->assertSeeText('THF/2026/00001');
        $this->actingAs($koordinator)->post($bagikan, ['bagikan' => 0])->assertSessionHasNoErrors();
        $this->actingAs($ortu)->get($cetak)->assertNotFound();
        $this->assertSame([$ortu->id], NotifikasiPengguna::where('kunci_unik', 'like', 'tahfidz-sertifikat:%')->pluck('pengguna_id')->all());
        Queue::assertPushed(KirimPushNotification::class, 1);
    }

    public function test_cetak_rapor_kelompok_memakai_target_per_tingkat_dan_mencetak_seluruh_anggota(): void
    {
        [$tahun, $siswa] = $this->sekolah();
        $siswa[1]->anggotaKelas()->sole()->kelas->update(['tingkat' => 7, 'nama' => 'VII.B']);
        $guru = $this->akunPegawai('Pembimbing Cetak');
        $guruLain = $this->akunPegawai('Pembimbing Kelompok Lain');
        $kelompok = $this->kelompok($tahun, $guru, 'Kelompok Cetak Bersama');
        $lain = $this->kelompok($tahun, $guruLain, 'Kelompok Cetak Rahasia');
        $this->actingAs($this->admin())->putAnggota($kelompok, collect($siswa)->pluck('id')->all())->assertSessionHasNoErrors();
        $anggota = $kelompok->anggotaAktif()->orderBy('siswa_id')->get();
        $target = ['tahun_pelajaran_id' => $tahun->id, 'tingkat' => 7, 'target' => 10, 'mode' => 'bebas', 'surah' => [], 'tanggal_rapor' => '2026-11-04'];
        TargetRaporTahfidz::create($target);
        $sesi = $kelompok->pertemuan()->create(['tanggal' => '2026-11-04']);
        foreach ([[$anggota[0], 60], [$anggota[1], 50], [$anggota[0], 80]] as [$item, $nilai]) {
            $sesi->setoran()->create(['anggota_tahfidz_id' => $item->id, 'nomor_surah' => 112, 'nilai' => $nilai, 'token_setoran' => (string) Str::uuid()]);
        }
        $index = route('tahfidz.rapor.index', ['kelompok' => $kelompok->id]);
        $cetak = route('tahfidz.rapor.kelompok.cetak', $kelompok);
        $this->capture('raporkelompokblokir', $this->actingAs($guru)->get($index)->assertOk()->assertViewHas('siap', 2)
            ->assertSeeText('Koordinator belum mengatur target rapor untuk tingkat 9.')->assertDontSeeText('Kelompok Cetak Rahasia'));
        $this->get($cetak)->assertRedirect($index)->assertSessionHasErrors('rapor');
        TargetRaporTahfidz::create([...$target, 'tingkat' => 9, 'target' => 37, 'tanggal_rapor' => '2026-11-05']);
        $this->capture('raporkelompok', $this->get($index)->assertOk()->assertViewHas('siap', 3)->assertSee($cetak, false)
            ->assertSee('data-sidebar-search-text="akademik penilaian rapor tahfidz"', false));
        DB::enableQueryLog();
        DB::flushQueryLog();
        $cetakResponse = $this->get($cetak)->assertOk();
        $kueriSetoran = collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'setoran_terurut'))->count();
        DB::disableQueryLog();
        $this->assertSame(2, $kueriSetoran, 'Setoran harus dibaca satu kali per pengaturan tingkat, bukan per siswa.');
        $this->capture('raporkelompokcetak', $cetakResponse->assertSeeText('3 rapor')->assertSeeText('Belum disetor')->assertDontSeeText('Bagikan rapor ke orang tua'));
        $dokumen = $cetakResponse->viewData('dokumenList')->keyBy('nama');
        $this->assertSame('8,00', $dokumen['Peserta Tujuh']['rata']);
        $this->assertSame('5,00', $dokumen['Peserta Delapan']['rata']);
        $this->assertNull($dokumen['Peserta Sembilan']['rata']);
        $this->assertCount(37, $dokumen['Peserta Sembilan']['baris']);
        $this->assertSame('2026-11-05', $dokumen['Peserta Sembilan']['tanggal']);
        $this->assertSame($dokumen['Peserta Tujuh'], $this->get(route('tahfidz.rapor.cetak', [$kelompok, $anggota[0]]))->assertOk()->viewData('dokumen'));

        $this->actingAs($guruLain)->get($index)->assertNotFound();
        $this->get($cetak)->assertNotFound();
        $this->get(route('tahfidz.rapor.kelompok.cetak', $lain))->assertRedirect()->assertSessionHasErrors('rapor');
        $this->actingAs($this->akunOrangTua('wali-cetak-kelompok', $siswa[0]))->get($cetak)->assertForbidden();
        $this->actingAs($this->akunPegawai('Pegawai Tanpa Akses Rapor'))->get(route('tahfidz.rapor.index'))->assertForbidden();
        $this->actingAs($this->koordinator())->get($cetak)->assertOk();
        $this->assertDatabaseCount('publikasi_rapor_tahfidz', 0);
        $this->assertDatabaseCount('notifikasi_pengguna', 0);
        $this->assertDatabaseCount('setoran_tahfidz', 3);
    }

    private function akunOrangTua(string $username, Siswa ...$anak): Pengguna
    {
        $akun = Pengguna::create(['nama' => 'Wali Uji', 'username' => $username,
            'kata_sandi' => 'UjiTahfidz2026', 'peran' => 'orang_tua', 'aktif' => true]);
        $akun->daftarPeran()->attach(Peran::where('kode', 'orang_tua')->sole());
        $orangTua = OrangTuaWali::create(['pengguna_id' => $akun->id, 'nama_lengkap' => 'Wali Uji']);
        foreach ($anak as $siswa) {
            $orangTua->siswa()->attach($siswa->id, ['hubungan' => 'wali', 'utama' => true]);
        }

        return $akun;
    }

    private function sekolah(string $nama = '2026/2027', bool $aktif = true): array
    {
        $tahun = TahunPelajaran::create(['nama' => $nama, 'aktif' => $aktif]);
        $siswa = [];
        foreach ([7 => ['VII.A', 'Tujuh'], 8 => ['VIII.B', 'Delapan'], 9 => ['IX.C', 'Sembilan']] as $tingkat => [$kelasNama, $label]) {
            $kelas = Kelas::create(['tahun_pelajaran_id' => $tahun->id, 'nama' => $kelasNama, 'tingkat' => $tingkat, 'aktif' => true]);
            $peserta = Siswa::create(['nama_lengkap' => 'Peserta '.$label, 'nis' => 'TF-'.$tahun->id.'-'.$tingkat, 'aktif' => true]);
            AnggotaKelas::create(['tahun_pelajaran_id' => $tahun->id, 'kelas_id' => $kelas->id, 'siswa_id' => $peserta->id,
                'nomor_absen' => 1, 'status_keanggotaan' => 'aktif']);
            $siswa[] = $peserta;
        }

        return [$tahun, $siswa];
    }

    private function akunPegawai(string $nama, string $jenis = 'Guru'): Pengguna
    {
        $pegawai = Pegawai::create(['nama_lengkap' => $nama, 'jenis_pegawai' => $jenis, 'aktif' => true]);
        $akun = Pengguna::create(['nama' => $nama, 'username' => 'tahfidz.'.$pegawai->id, 'pegawai_id' => $pegawai->id,
            'kata_sandi' => 'UjiTahfidz2026', 'peran' => 'pegawai', 'aktif' => true]);
        $akun->daftarPeran()->attach(Peran::where('kode', 'pegawai')->sole());

        return $akun;
    }

    private function koordinator(): Pengguna
    {
        $akun = $this->akunPegawai('Koordinator Uji');
        $akun->daftarPeran()->attach(Peran::where('kode', 'koordinator_tahfidz')->sole());

        return $akun;
    }

    private function admin(): Pengguna
    {
        return Pengguna::where('username', 'administrator')->sole();
    }

    private function isian(TahunPelajaran $tahun, Pengguna $guru, string $nama = 'Kelompok Al-Ikhlas'): array
    {
        return ['tahun_pelajaran_id' => $tahun->id, 'pembimbing_pegawai_id' => $guru->pegawai_id, 'nama' => $nama, 'aktif' => 1];
    }

    private function kelompok(TahunPelajaran $tahun, Pengguna $guru, string $nama = 'Kelompok Al-Ikhlas'): KelompokTahfidz
    {
        return KelompokTahfidz::create($this->isian($tahun, $guru, $nama))->refresh();
    }

    private function putAnggota(KelompokTahfidz $kelompok, array $ids): TestResponse
    {
        return $this->put(route('tahfidz.anggota.update', $kelompok), ['versi' => $kelompok->versi, 'daftar_lengkap' => 1, 'siswa_ids' => $ids]);
    }

    private function ubahStatus(KelompokTahfidz $kelompok, bool $aktif): TestResponse
    {
        return $this->put(route('tahfidz.update', $kelompok), ['nama' => $kelompok->nama, 'aktif' => $aktif,
            'pembimbing_pegawai_id' => $kelompok->pembimbing_pegawai_id, 'versi' => $kelompok->versi]);
    }

    private function capture(string $nama, TestResponse $response): void
    {
        if (getenv('NUSA_CAPTURE_TAHFIDZ')) {
            $path = storage_path('framework/testing/tahfidz');
            File::ensureDirectoryExists($path);
            File::put($path.'/'.$nama.'.html', $response->getContent());
        }
    }
}
