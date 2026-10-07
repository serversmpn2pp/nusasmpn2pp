<?php

namespace App\Services\Pembinaan;

use App\Models\AbsensiSiswa;
use App\Models\AnggotaKelas;
use App\Models\JenisPelanggaranSiswa;
use App\Models\KategoriPembinaanSiswa;
use App\Models\LaporanPembinaanSiswa;
use App\Models\PengaturanAbsensi;
use App\Models\PengaturanPoinKeterlambatan;
use App\Models\PengecualianPresensiSiswa;
use App\Models\PenugasanGuruWaliSiswa;
use App\Services\Absensi\AturanAlfaOtomatisSiswaService;
use App\Services\Absensi\HitungKeterlambatanSiswaService;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PoinPresensiOtomatisService
{
    public function __construct(
        private ProsesPoinSiswaService $poin,
        private CatatRiwayatPembinaanService $riwayat,
        private NotifikasiPenggunaService $notifikasi,
    ) {}

    public function sinkronkanAbsensi(AbsensiSiswa $absensi, ?int $penggunaId = null): array
    {
        return $absensi->anggota_kelas_id
            ? $this->sinkronkanAnggota($absensi->anggota_kelas_id, $absensi->tanggal->toDateString(), $penggunaId)
            : ['hasil' => 'diabaikan', 'laporan_id' => null];
    }

    public function sinkronkanAnggota(int $anggotaId, string $tanggal, ?int $penggunaId = null): array
    {
        $tanggal = Carbon::parse($tanggal)->toDateString();
        $hasil = DB::transaction(function () use ($anggotaId, $tanggal, $penggunaId) {
            // Serializing the student's membership protects the first report, before it has a row to lock.
            $anggota = AnggotaKelas::with(['siswa', 'kelas', 'tahunPelajaran'])->lockForUpdate()->findOrFail($anggotaId);
            $kunci = $anggota->tahun_pelajaran_id.':'.$anggota->siswa_id.':'.$tanggal;
            $laporan = LaporanPembinaanSiswa::where('kunci_presensi_otomatis', $kunci)->lockForUpdate()->first();
            $pengaturan = PengaturanPoinKeterlambatan::where('tahun_pelajaran_id', $anggota->tahun_pelajaran_id)->first();
            $dapatMenambah = $pengaturan?->aktif && $pengaturan->otomatis_langsung && $pengaturan->berlaku_mulai
                && $tanggal >= $pengaturan->berlaku_mulai->toDateString() && $tanggal <= now()->toDateString();
            if (! $laporan && ! $dapatMenambah) {
                return ['hasil' => 'diabaikan', 'laporan_id' => null];
            }

            $absensi = AbsensiSiswa::where('siswa_id', $anggota->siswa_id)->whereDate('tanggal', $tanggal)->lockForUpdate()->first();
            $aturan = new AturanAlfaOtomatisSiswaService;
            $hariAktif = $aturan->hariAktif();
            $hari = array_keys(PengaturanAbsensi::DAFTAR_HARI)[Carbon::parse($tanggal)->isoWeekday() - 1];
            $jadwal = PengaturanAbsensi::where('hari', $hari)->where('aktif', true)->first();
            $pengecualian = $aturan->pengecualianPada($tanggal, $anggota);
            $cakupanValid = $anggota->siswa?->aktif && $anggota->kelas?->aktif
                && (! $anggota->tanggal_masuk || $tanggal >= $anggota->tanggal_masuk->toDateString())
                && (! $anggota->tanggal_keluar || $tanggal <= $anggota->tanggal_keluar->toDateString())
                && (! $anggota->tahunPelajaran?->tanggal_mulai || $tanggal >= $anggota->tahunPelajaran->tanggal_mulai->toDateString())
                && (! $anggota->tahunPelajaran?->tanggal_selesai || $tanggal <= $anggota->tahunPelajaran->tanggal_selesai->toDateString());
            $jenis = null;
            $menit = $absensi?->status_kehadiran === 'hadir' && $absensi->jam_masuk && $jadwal
                ? app(HitungKeterlambatanSiswaService::class)->menit($absensi->jam_masuk, $jadwal->jam_masuk, true) : 0;
            if ($cakupanValid && $jadwal && ! $pengecualian) {
                if ($absensi?->status_kehadiran === 'hadir' && $absensi->jam_masuk) {
                    $jenis = $menit > 0 ? 'terlambat' : null;
                } elseif ((! $absensi || $absensi->status_kehadiran === 'alfa')
                    && $aturan->menjadiAlfa($tanggal, $hariAktif, $anggota)) {
                    $jenis = 'alfa';
                }
            }
            $target = $jenis ? (int) ($jenis === 'alfa' ? $pengaturan?->poin_alfa : $pengaturan?->poin_terlambat) : 0;
            if ($laporan?->poin_dikecualikan_pada) {
                $target = 0;
            } elseif (! $dapatMenambah && $target > 0) {
                $target = (int) ($laporan?->total_poin ?? 0);
            }
            if (! $laporan && $target <= 0) {
                if ($absensi && $dapatMenambah) {
                    $absensi->update(['status_poin_keterlambatan' => $pengecualian ? 'pengecualian_presensi' : 'tidak_terlambat',
                        'poin_keterlambatan_terhitung' => 0, 'poin_keterlambatan_diproses_pada' => now()]);
                }

                return ['hasil' => 'diabaikan', 'laporan_id' => null];
            }

            $baru = ! $laporan;
            if (! $laporan && $absensi) {
                $laporan = LaporanPembinaanSiswa::where('sumber_laporan', 'absensi_otomatis')
                    ->where('absensi_siswa_id', $absensi->id)->where('status_verifikasi', '!=', 'dibatalkan')->first();
            }
            if (! $laporan) {
                $laporan = LaporanPembinaanSiswa::create([
                    'nomor_laporan' => 'PPR-'.str_replace('-', '', $tanggal).'-'.$anggota->tahun_pelajaran_id.'-'.$anggota->siswa_id,
                    'jenis_laporan' => 'pelanggaran', 'sumber_laporan' => 'absensi_otomatis',
                    'tanggal_kejadian' => $tanggal, 'tempat_kejadian' => 'Sekolah',
                    'siswa_id' => $anggota->siswa_id, 'tahun_pelajaran_id' => $anggota->tahun_pelajaran_id,
                    'kelas_id' => $anggota->kelas_id, 'anggota_kelas_id' => $anggota->id,
                    'kunci_presensi_otomatis' => $kunci, 'jenis_presensi_otomatis' => $jenis,
                    'wali_kelas_pegawai_id' => $anggota->kelas?->wali_kelas_id,
                    'guru_wali_pegawai_id' => PenugasanGuruWaliSiswa::where('siswa_id', $anggota->siswa_id)
                        ->where('tanggal_mulai', '<=', $tanggal)
                        ->where(fn ($q) => $q->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $tanggal))
                        ->latest('tanggal_mulai')->value('guru_wali_pegawai_id'),
                    'status' => 'baru', 'status_verifikasi' => 'diajukan', 'tingkat' => 'ringan',
                    'total_poin' => 0, 'kronologi' => 'Poin presensi otomatis.',
                    'tindakan_awal' => 'Poin langsung ditetapkan tanpa persetujuan Wakil Kesiswaan.',
                    'dibuat_oleh_pengguna_id' => $penggunaId,
                ]);
            }
            if ($jenis && ! $laporan->poin_dikecualikan_pada) {
                $butir = $this->jenisPelanggaran($jenis);
                $laporan->update([
                    'kunci_presensi_otomatis' => $kunci, 'jenis_presensi_otomatis' => $jenis,
                    'absensi_siswa_id' => $absensi?->id, 'waktu_kejadian' => $absensi?->jam_masuk,
                    'kategori_pembinaan_siswa_id' => $butir->kategori_pembinaan_siswa_id,
                    'tingkat' => $butir->tingkat, 'menit_terlambat_tercatat' => $menit,
                    'diproses_otomatis_pada' => now(),
                    'kronologi' => $jenis === 'alfa' ? 'Hari sekolah berakhir tanpa kehadiran atau keterangan yang sah.'
                        : 'Scan masuk pukul '.$absensi->jam_masuk.' melewati batas '.$jadwal->jam_masuk.'. Keterlambatan dihitung sejak detik pertama.',
                ]);
                $laporan->butirPelanggaranLaporan()->updateOrCreate(
                    ['laporan_pembinaan_siswa_id' => $laporan->id],
                    ['jenis_pelanggaran_siswa_id' => $butir->id, 'kode_pelanggaran' => $butir->kode,
                        'nama_pelanggaran' => $jenis === 'alfa' ? 'Tidak hadir tanpa keterangan (alfa)' : 'Terlambat datang ke sekolah',
                        'tingkat' => $butir->tingkat, 'poin' => $target, 'catatan' => 'Aturan poin presensi langsung.'],
                );
            }
            $alasan = $target > 0 ? ucfirst($jenis).' otomatis: '.$target.' poin, tanpa persetujuan Wakil Kesiswaan.'
                : ($pengecualian ? 'Poin dibatalkan karena pengecualian presensi: '.$pengecualian->alasan : 'Poin dibatalkan setelah koreksi presensi atau penerimaan alasan.');
            $berubah = $this->poin->tetapkanPoinPresensi($laporan, $target, $penggunaId, $alasan);
            if ($absensi && $dapatMenambah) {
                $nilai = ['status_poin_keterlambatan' => $target > 0 ? 'poin_otomatis' : 'poin_dibatalkan',
                    'poin_keterlambatan_terhitung' => $target, 'poin_keterlambatan_diproses_pada' => now()];
                if ($absensi->status_kehadiran === 'hadir' && $jadwal) {
                    $nilai += ['menit_terlambat' => $menit, 'status_masuk' => $menit > 0 ? 'terlambat' : 'tepat_waktu'];
                }
                $absensi->update($nilai);
            }

            return ['hasil' => ! $berubah ? 'diabaikan' : ($target === 0 ? 'dibatalkan' : ($baru ? 'dibuat' : 'diperbarui')), 'laporan_id' => $laporan->id];
        });
        if ($hasil['hasil'] !== 'diabaikan' && $hasil['laporan_id']) {
            $this->kirimNotifikasi(LaporanPembinaanSiswa::findOrFail($hasil['laporan_id']));
        }

        return $hasil;
    }

    public function prosesTanggal(string $tanggal, int $tahunId, ?int $kelasId = null, ?int $penggunaId = null): array
    {
        $hasil = ['total' => 0, 'dibuat' => 0, 'diperbarui' => 0, 'dibatalkan' => 0, 'diabaikan' => 0, 'laporan_baru_ids' => []];
        AnggotaKelas::where('tahun_pelajaran_id', $tahunId)->where('status_keanggotaan', 'aktif')
            ->when($kelasId, fn ($q) => $q->where('kelas_id', $kelasId))->orderBy('id')
            ->chunkById(200, function ($anggota) use ($tanggal, $penggunaId, &$hasil) {
                foreach ($anggota as $item) {
                    $satu = $this->sinkronkanAnggota($item->id, $tanggal, $penggunaId);
                    $hasil['total']++;
                    $hasil[$satu['hasil']]++;
                    if ($satu['hasil'] === 'dibuat') {
                        $hasil['laporan_baru_ids'][] = $satu['laporan_id'];
                    }
                }
            });

        return $hasil;
    }

    public function prosesSemua(): int
    {
        $jumlah = 0;
        foreach (PengaturanPoinKeterlambatan::where('aktif', true)->where('otomatis_langsung', true)
            ->whereNotNull('berlaku_mulai')->whereHas('tahunPelajaran', fn ($q) => $q->where('aktif', true))->get() as $pengaturan) {
            $jumlah += $this->prosesTanggal(now()->toDateString(), $pengaturan->tahun_pelajaran_id)['dibuat'];
            $mulai = $pengaturan->alfa_diproses_sampai?->copy()->addDay() ?? $pengaturan->berlaku_mulai;
            $mulai = $mulai->max($pengaturan->berlaku_mulai);
            $akhir = now()->subDay()->startOfDay();
            foreach (CarbonPeriod::create($mulai, $akhir) as $tanggal) {
                $jumlah += $this->prosesTanggal($tanggal->toDateString(), $pengaturan->tahun_pelajaran_id)['dibuat'];
                PengaturanPoinKeterlambatan::whereKey($pengaturan->id)
                    ->where(fn ($q) => $q->whereNull('alfa_diproses_sampai')->orWhereDate('alfa_diproses_sampai', '<', $tanggal))
                    ->update(['alfa_diproses_sampai' => $tanggal->toDateString()]);
            }
        }

        return $jumlah;
    }

    public function terimaAlasan(LaporanPembinaanSiswa $laporan, int $penggunaId, string $alasan): void
    {
        if (blank(trim($alasan))) {
            throw ValidationException::withMessages(['alasan' => 'Alasan koreksi poin wajib diisi.']);
        }
        DB::transaction(function () use ($laporan, $penggunaId, $alasan) {
            $laporan = LaporanPembinaanSiswa::lockForUpdate()->findOrFail($laporan->id);
            abort_unless($laporan->kunci_presensi_otomatis, 422);
            if ($laporan->poin_dikecualikan_pada) {
                return;
            }
            $laporan->update(['poin_dikecualikan_pada' => now()]);
            $this->poin->tetapkanPoinPresensi($laporan, 0, $penggunaId, 'Alasan diterima: '.trim($alasan));
            $this->riwayat->catat($laporan->fresh(), 'alasan_presensi_diterima', 'Alasan siswa diterima', trim($alasan), null, 'dibatalkan', $penggunaId);
        });
        if ($laporan->absensi_siswa_id) {
            AbsensiSiswa::whereKey($laporan->absensi_siswa_id)->update([
                'status_poin_keterlambatan' => 'poin_dibatalkan', 'poin_keterlambatan_terhitung' => 0,
                'poin_keterlambatan_diproses_pada' => now(),
            ]);
        }
        $this->kirimNotifikasi($laporan->fresh());
    }

    public function sinkronkanPengecualian(PengecualianPresensiSiswa $pengecualian, ?int $penggunaId): void
    {
        LaporanPembinaanSiswa::whereNotNull('kunci_presensi_otomatis')->where('tahun_pelajaran_id', $pengecualian->tahun_pelajaran_id)
            ->when($pengecualian->kelas_id, fn ($q) => $q->where('kelas_id', $pengecualian->kelas_id))
            ->whereBetween('tanggal_kejadian', [$pengecualian->tanggal_mulai->toDateString(), $pengecualian->tanggal_selesai->toDateString()])
            ->orderBy('id')->chunkById(200, function ($laporan) use ($penggunaId) {
                foreach ($laporan as $item) {
                    $this->sinkronkanAnggota($item->anggota_kelas_id, $item->tanggal_kejadian->toDateString(), $penggunaId);
                }
            });
    }

    private function jenisPelanggaran(string $jenis): JenisPelanggaranSiswa
    {
        $kategori = KategoriPembinaanSiswa::firstOrCreate(['kode' => 'KEHADIRAN'], ['nama' => 'Kehadiran', 'aktif' => true]);

        return JenisPelanggaranSiswa::firstOrCreate(['kode' => $jenis === 'alfa' ? 'PRESENSI-ALFA' : 'R001'], [
            'kategori_pembinaan_siswa_id' => $kategori->id,
            'nama' => $jenis === 'alfa' ? 'Tidak hadir tanpa keterangan (alfa)' : 'Terlambat datang ke sekolah',
            'tingkat' => 'ringan', 'poin' => $jenis === 'alfa' ? 25 : 15, 'aktif' => true,
            'urutan' => (int) JenisPelanggaranSiswa::max('urutan') + 1,
        ]);
    }

    private function kirimNotifikasi(LaporanPembinaanSiswa $laporan): void
    {
        $penerima = $this->notifikasi->penggunaUntukSiswa($laporan->siswa_id)
            ->merge($this->notifikasi->penggunaOrangTuaUntukSiswa($laporan->siswa_id))->unique('id');
        $this->notifikasi->kirimKeBanyak($penerima, $laporan->total_poin > 0 ? 'peringatan' : 'informasi',
            $laporan->total_poin > 0 ? 'Poin presensi tercatat otomatis' : 'Poin presensi dikoreksi',
            $laporan->total_poin > 0 ? ucfirst($laporan->jenis_presensi_otomatis).' pada '.$laporan->tanggal_kejadian->format('d/m/Y').': '.$laporan->total_poin.' poin.'
                : 'Poin kejadian presensi tanggal '.$laporan->tanggal_kejadian->format('d/m/Y').' telah dibatalkan.',
            null, 'poin-presensi:'.$laporan->id.':'.$laporan->transaksiPoinSiswa()->count(),
            ['laporan_pembinaan_siswa_id' => $laporan->id]);
    }
}
