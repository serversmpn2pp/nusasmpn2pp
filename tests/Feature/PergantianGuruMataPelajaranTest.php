<?php

namespace Tests\Feature;

use App\Models\GuruMataPelajaran;
use App\Models\JadwalPelajaran;
use App\Models\JamPelajaran;
use App\Models\Kelas;
use App\Models\KomponenNilai;
use App\Models\MataPelajaran;
use App\Models\Pegawai;
use App\Models\Pengguna;
use App\Models\Peran;
use App\Models\RiwayatPergantianGuruMapel;
use App\Models\TahunPelajaran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PergantianGuruMataPelajaranTest extends TestCase
{
    use RefreshDatabase;

    public function test_wakil_kurikulum_memiliki_izin_mengelola_guru_mapel(): void
    {
        $wakilKurikulum = Peran::where('kode', 'wakil_pimpinan_kurikulum')->firstOrFail();

        $this->assertTrue($wakilKurikulum->memilikiIzin('guru_mapel.kelola'));
    }

    public function test_form_pergantian_menampilkan_kelas_satu_kelompok_dan_guru_pengganti(): void
    {
        [$administrator, , $guruLama, $guruBaru, , $penugasan] = $this->dataDasar();
        $kelasPendamping = Kelas::create([
            'tahun_pelajaran_id' => $penugasan->first()->tahun_pelajaran_id,
            'nama' => 'VII.D',
            'tingkat' => 7,
            'aktif' => true,
        ]);
        GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $penugasan->first()->tahun_pelajaran_id,
            'kelas_id' => $kelasPendamping->id,
            'mata_pelajaran_id' => $penugasan->first()->mata_pelajaran_id,
            'pegawai_id' => $guruLama->id,
            'jenis_penugasan' => 'pendamping',
            'aktif' => true,
        ]);

        $this->actingAs($administrator)
            ->get(route('guru-mata-pelajaran.ganti-guru', $penugasan->first()))
            ->assertOk()
            ->assertSee('Ganti Guru Pengampu')
            ->assertSee($guruLama->nama_lengkap)
            ->assertSee($guruBaru->nama_lengkap)
            ->assertSeeInOrder([
                'Tingkat VII',
                'VII.A',
                'VII.B',
                'VII.C',
            ])
            ->assertDontSee('VII.D');
    }

    public function test_pergantian_massal_mempertahankan_relasi_jadwal_dan_nilai(): void
    {
        [$administrator, $tahun, $guruLama, $guruBaru, , $penugasan] = $this->dataDasar();
        $penugasanPertama = $penugasan->first();
        $penugasanKedua = $penugasan->get(1);
        $penugasanKetiga = $penugasan->get(2);
        $komponen = KomponenNilai::create([
            'guru_mata_pelajaran_id' => $penugasanPertama->id,
            'semester' => 'ganjil',
            'jenis_komponen' => 'formatif',
            'nama' => 'Tugas 1',
            'urutan' => 1,
            'aktif' => true,
        ]);
        $jam = JamPelajaran::create([
            'hari' => 'senin',
            'nomor_jam' => 1,
            'label' => 'Jam 1',
            'jam_mulai' => '07:30',
            'jam_selesai' => '08:10',
            'jenis' => 'pelajaran',
            'aktif' => true,
        ]);
        $jadwal = JadwalPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $penugasanPertama->kelas_id,
            'hari' => 'senin',
            'jam_pelajaran_id' => $jam->id,
            'guru_mata_pelajaran_id' => $penugasanPertama->id,
            'aktif' => true,
        ]);

        $this->actingAs($administrator)
            ->put(route('guru-mata-pelajaran.simpan-pergantian', $penugasanPertama), [
                'pegawai_baru_id' => $guruBaru->id,
                'penugasan_ids' => [$penugasanPertama->id, $penugasanKedua->id],
                'tanggal_efektif' => '2026-07-20',
                'alasan' => 'Perubahan pembagian tugas semester ganjil.',
            ])
            ->assertRedirect(route('guru-mata-pelajaran.index', [
                'tahun_pelajaran_id' => $tahun->id,
                'status' => 'aktif',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame($guruBaru->id, $penugasanPertama->fresh()->pegawai_id);
        $this->assertSame($guruBaru->id, $penugasanKedua->fresh()->pegawai_id);
        $this->assertSame($guruLama->id, $penugasanKetiga->fresh()->pegawai_id);
        $this->assertSame($penugasanPertama->id, $komponen->fresh()->guru_mata_pelajaran_id);
        $this->assertSame($penugasanPertama->id, $jadwal->fresh()->guru_mata_pelajaran_id);
        $this->assertDatabaseCount('riwayat_pergantian_guru_mapel', 2);
        $this->assertDatabaseHas('riwayat_pergantian_guru_mapel', [
            'guru_mata_pelajaran_id' => $penugasanPertama->id,
            'pegawai_lama_id' => $guruLama->id,
            'pegawai_baru_id' => $guruBaru->id,
            'diganti_oleh_pengguna_id' => $administrator->id,
        ]);
        $this->assertSame(
            '2026-07-20',
            RiwayatPergantianGuruMapel::where('guru_mata_pelajaran_id', $penugasanPertama->id)
                ->firstOrFail()
                ->tanggal_efektif
                ->format('Y-m-d'),
        );
    }

    public function test_pergantian_menolak_penugasan_dari_kelompok_lain(): void
    {
        [$administrator, $tahun, $guruLama, $guruBaru, , $penugasan] = $this->dataDasar();
        $mapelLain = MataPelajaran::create([
            'nama' => 'Ilmu Pengetahuan Alam',
            'kelompok' => 'Umum',
            'aktif' => true,
        ]);
        $penugasanLain = GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $penugasan->first()->kelas_id,
            'mata_pelajaran_id' => $mapelLain->id,
            'pegawai_id' => $guruLama->id,
            'jenis_penugasan' => 'pengampu',
            'aktif' => true,
        ]);

        $this->actingAs($administrator)
            ->from(route('guru-mata-pelajaran.ganti-guru', $penugasan->first()))
            ->put(route('guru-mata-pelajaran.simpan-pergantian', $penugasan->first()), [
                'pegawai_baru_id' => $guruBaru->id,
                'penugasan_ids' => [$penugasan->first()->id, $penugasanLain->id],
                'tanggal_efektif' => '2026-07-20',
                'alasan' => 'Pengujian validasi kelompok.',
            ])
            ->assertRedirect(route('guru-mata-pelajaran.ganti-guru', $penugasan->first()))
            ->assertSessionHasErrors('penugasan_ids');

        $this->assertSame($guruLama->id, $penugasan->first()->fresh()->pegawai_id);
        $this->assertDatabaseCount('riwayat_pergantian_guru_mapel', 0);
    }

    private function dataDasar(): array
    {
        $administrator = Pengguna::create([
            'nama' => 'Administrator Uji',
            'username' => 'administrator-pergantian-guru-uji',
            'kata_sandi' => 'KataSandi-Uji-2026',
            'wajib_ganti_kata_sandi' => false,
            'peran' => 'administrator',
            'aktif' => true,
            'akun_sistem' => true,
        ]);
        $tahun = TahunPelajaran::create([
            'nama' => '2026/2027',
            'tanggal_mulai' => '2026-07-01',
            'tanggal_selesai' => '2027-06-30',
            'aktif' => true,
        ]);
        $guruLama = Pegawai::create([
            'nama_lengkap' => 'Guru Lama',
            'nip' => '198501012010011001',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $guruBaru = Pegawai::create([
            'nama_lengkap' => 'Guru Pengganti',
            'nip' => '198601012011012002',
            'jenis_pegawai' => 'Guru',
            'aktif' => true,
        ]);
        $mapel = MataPelajaran::create([
            'nama' => 'Bahasa Indonesia',
            'kelompok' => 'Umum',
            'aktif' => true,
        ]);
        $kelas = collect(['VII.A', 'VII.B', 'VII.C'])->map(fn ($nama) => Kelas::create([
            'tahun_pelajaran_id' => $tahun->id,
            'nama' => $nama,
            'tingkat' => 7,
            'aktif' => true,
        ]));
        $penugasan = $kelas->map(fn (Kelas $item) => GuruMataPelajaran::create([
            'tahun_pelajaran_id' => $tahun->id,
            'kelas_id' => $item->id,
            'mata_pelajaran_id' => $mapel->id,
            'pegawai_id' => $guruLama->id,
            'jenis_penugasan' => 'pengampu',
            'aktif' => true,
        ]));

        return [$administrator, $tahun, $guruLama, $guruBaru, $mapel, $penugasan];
    }
}
