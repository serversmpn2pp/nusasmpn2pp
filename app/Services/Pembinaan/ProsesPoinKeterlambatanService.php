<?php

namespace App\Services\Pembinaan;

use App\Models\AbsensiSiswa;
use App\Models\JenisPelanggaranSiswa;
use App\Models\KategoriPembinaanSiswa;
use App\Models\LaporanPembinaanSiswa;
use App\Models\PenugasanGuruWaliSiswa;
use App\Models\RentangPoinKeterlambatan;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ProsesPoinKeterlambatanService
{
    public function __construct(
        private PengaturanPoinKeterlambatanService $pengaturanPoin,
        private PengaturanBatasProsesPelanggaranService $pengaturanBatas,
        private CatatRiwayatPembinaanService $riwayatPembinaan,
        private ProsesPoinSiswaService $prosesPoin,
        private NotifikasiPenggunaService $notifikasi,
        private PenugasanGuruBkTingkatService $penugasanBk,
    ) {}

    /** @return array{total: int, dibuat: int, diperbarui: int, dibatalkan: int, diabaikan: int, laporan_baru_ids: array<int, int>} */
    public function prosesTanggal(
        CarbonImmutable|string $tanggal,
        ?int $tahunPelajaranId = null,
        ?int $kelasId = null,
        ?int $penggunaId = null,
        bool $paksa = false,
    ): array {
        $tanggal = $tanggal instanceof CarbonImmutable ? $tanggal : CarbonImmutable::parse($tanggal);
        $absensiIds = AbsensiSiswa::query()
            ->whereDate('tanggal', $tanggal->toDateString())
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->when($kelasId, fn ($query) => $query->where('kelas_id', $kelasId))
            ->when(! $paksa, fn ($query) => $query->where(function ($query) {
                $query->whereNull('poin_keterlambatan_diproses_pada')
                    ->orWhereColumn('updated_at', '>', 'poin_keterlambatan_diproses_pada');
            }))
            ->orderBy('id')
            ->pluck('id');

        $hasil = [
            'total' => $absensiIds->count(),
            'dibuat' => 0,
            'diperbarui' => 0,
            'dibatalkan' => 0,
            'diabaikan' => 0,
            'laporan_baru_ids' => [],
        ];

        foreach ($absensiIds as $absensiId) {
            $satu = $this->sinkronkanAbsensi((int) $absensiId, $penggunaId, false);
            $hasil[$satu['hasil']]++;
            if ($satu['laporan_id'] && $satu['hasil'] === 'dibuat') {
                $hasil['laporan_baru_ids'][] = $satu['laporan_id'];
            }
        }

        if ($hasil['laporan_baru_ids'] !== []) {
            $this->kirimNotifikasiRingkas($tanggal, $hasil['laporan_baru_ids'], $penggunaId);
        }

        return $hasil;
    }

    /** @return array{hasil: string, laporan_id: int|null} */
    public function sinkronkanAbsensi(
        AbsensiSiswa|int $absensi,
        ?int $penggunaId = null,
        bool $kirimNotifikasi = true,
    ): array {
        $absensiId = $absensi instanceof AbsensiSiswa ? $absensi->id : $absensi;

        $hasil = DB::transaction(function () use ($absensiId, $penggunaId) {
            $absensi = AbsensiSiswa::query()
                ->with(['siswa', 'kelas', 'anggotaKelas'])
                ->lockForUpdate()
                ->findOrFail($absensiId);

            $laporanTerakhir = LaporanPembinaanSiswa::query()
                ->where('sumber_laporan', 'absensi_otomatis')
                ->where('absensi_siswa_id', $absensi->id)
                ->latest('id')
                ->first();
            $laporanAktif = $laporanTerakhir && $laporanTerakhir->status_verifikasi !== 'dibatalkan'
                ? $laporanTerakhir
                : null;

            $menit = max(0, (int) $absensi->menit_terlambat);
            $hadirDanTerlambat = $absensi->status_kehadiran === 'hadir'
                && $absensi->status_masuk === 'terlambat'
                && $menit > 0;

            if (! $hadirDanTerlambat) {
                $dibatalkan = $this->batalkanLaporanAktif($laporanAktif, $penggunaId, 'Data presensi tidak lagi berstatus terlambat.');
                $this->tandaiAbsensi($absensi, 'tidak_terlambat', 0);

                return ['hasil' => $dibatalkan ? 'dibatalkan' : 'diabaikan', 'laporan_id' => $laporanAktif?->id];
            }

            $rentang = $this->pengaturanPoin->rentangUntukMenit($absensi->tahun_pelajaran_id, $menit);
            if (! $rentang) {
                $dibatalkan = $this->batalkanLaporanAktif($laporanAktif, $penggunaId, 'Otomatisasi atau rentang poin keterlambatan tidak aktif.');
                $this->tandaiAbsensi($absensi, 'otomatis_nonaktif', 0);

                return ['hasil' => $dibatalkan ? 'dibatalkan' : 'diabaikan', 'laporan_id' => $laporanAktif?->id];
            }

            if ((int) $rentang->poin === 0) {
                $dibatalkan = $this->batalkanLaporanAktif($laporanAktif, $penggunaId, 'Keterlambatan masuk ke rentang toleransi 0 poin.');
                $this->tandaiAbsensi($absensi, 'toleransi', 0);

                return ['hasil' => $dibatalkan ? 'dibatalkan' : 'diabaikan', 'laporan_id' => $laporanAktif?->id];
            }

            if ($laporanAktif?->status_verifikasi === 'tidak_terbukti') {
                $this->tandaiAbsensi($absensi, 'laporan_tidak_terbukti', 0);

                return ['hasil' => 'diabaikan', 'laporan_id' => $laporanAktif->id];
            }

            if ($laporanAktif && (int) $laporanAktif->menit_terlambat_tercatat === $menit) {
                $this->tandaiAbsensi($absensi, 'laporan_diajukan', (int) $laporanAktif->total_poin);

                return ['hasil' => 'diabaikan', 'laporan_id' => $laporanAktif->id];
            }

            if (! $laporanAktif && $laporanTerakhir?->status_verifikasi === 'dibatalkan'
                && (int) $laporanTerakhir->menit_terlambat_tercatat === $menit) {
                $this->tandaiAbsensi($absensi, 'laporan_dibatalkan', 0);

                return ['hasil' => 'diabaikan', 'laporan_id' => $laporanTerakhir->id];
            }

            if ($laporanAktif?->status_verifikasi === 'disahkan'
                && (int) $laporanAktif->total_poin !== (int) $rentang->poin) {
                $this->batalkanLaporanAktif(
                    $laporanAktif,
                    $penggunaId,
                    'Koreksi presensi mengubah rentang poin. Laporan pengganti harus diperiksa dan disetujui kembali.',
                );
                $laporanAktif = null;
            }

            if (! $laporanAktif) {
                $laporan = $this->buatLaporan($absensi, $rentang, $penggunaId);
                $this->tandaiAbsensi($absensi, 'laporan_diajukan', (int) $rentang->poin);

                return ['hasil' => 'dibuat', 'laporan_id' => $laporan->id];
            }

            $this->perbaruiLaporan($laporanAktif, $absensi, $rentang, $penggunaId);
            $this->tandaiAbsensi($absensi, 'laporan_diajukan', (int) $rentang->poin);

            return ['hasil' => 'diperbarui', 'laporan_id' => $laporanAktif->id];
        });

        if ($kirimNotifikasi && $hasil['hasil'] === 'dibuat' && $hasil['laporan_id']) {
            $tanggal = CarbonImmutable::parse(AbsensiSiswa::findOrFail($absensiId)->tanggal);
            $this->kirimNotifikasiRingkas($tanggal, [$hasil['laporan_id']], $penggunaId);
        }

        return $hasil;
    }

    private function buatLaporan(AbsensiSiswa $absensi, RentangPoinKeterlambatan $rentang, ?int $penggunaId): LaporanPembinaanSiswa
    {
        $jenis = $this->jenisPelanggaranKeterlambatan();
        $tanggal = CarbonImmutable::parse($absensi->tanggal);
        $versi = LaporanPembinaanSiswa::where('absensi_siswa_id', $absensi->id)->count() + 1;
        $guruWaliId = PenugasanGuruWaliSiswa::query()
            ->where('siswa_id', $absensi->siswa_id)
            ->where('tanggal_mulai', '<=', $tanggal->toDateString())
            ->where(fn ($query) => $query->whereNull('tanggal_selesai')->orWhere('tanggal_selesai', '>=', $tanggal->toDateString()))
            ->latest('tanggal_mulai')
            ->value('guru_wali_pegawai_id');

        $laporan = LaporanPembinaanSiswa::create([
            'nomor_laporan' => sprintf('PKT-%s-%06d-%02d', $tanggal->format('Ymd'), $absensi->id, $versi),
            'jenis_laporan' => 'pelanggaran',
            'sumber_laporan' => 'absensi_otomatis',
            'tanggal_kejadian' => $tanggal->toDateString(),
            'waktu_kejadian' => $absensi->jam_masuk,
            'tempat_kejadian' => 'Gerbang sekolah',
            'siswa_id' => $absensi->siswa_id,
            'kategori_pembinaan_siswa_id' => $jenis->kategori_pembinaan_siswa_id,
            'tahun_pelajaran_id' => $absensi->tahun_pelajaran_id,
            'kelas_id' => $absensi->kelas_id,
            'anggota_kelas_id' => $absensi->anggota_kelas_id,
            'absensi_siswa_id' => $absensi->id,
            'rentang_poin_keterlambatan_id' => $rentang->id,
            'menit_terlambat_tercatat' => $absensi->menit_terlambat,
            'diproses_otomatis_pada' => now(),
            'wali_kelas_pegawai_id' => $absensi->kelas?->wali_kelas_id,
            'guru_wali_pegawai_id' => $guruWaliId,
            'tingkat' => $jenis->tingkat,
            'status' => 'baru',
            'status_verifikasi' => 'diajukan',
            'total_poin' => $rentang->poin,
            'kronologi' => $this->buatKronologi($absensi, $rentang),
            'tindakan_awal' => 'Diajukan otomatis dari rekap presensi harian.',
            'dibuat_oleh_pengguna_id' => $penggunaId,
        ]);

        $laporan->butirPelanggaranLaporan()->create([
            'jenis_pelanggaran_siswa_id' => $jenis->id,
            'kode_pelanggaran' => $jenis->kode,
            'nama_pelanggaran' => $jenis->nama,
            'tingkat' => $jenis->tingkat,
            'poin' => $rentang->poin,
            'catatan' => $rentang->labelRentang().' berdasarkan pengaturan tahun pelajaran.',
        ]);
        $this->pengaturanBatas->tetapkanBatas($laporan);
        $this->riwayatPembinaan->catat(
            $laporan,
            'laporan_otomatis_absensi',
            'Laporan dibuat dari presensi',
            sprintf('Terlambat %d menit dan diajukan %d poin untuk pemeriksaan BK.', $absensi->menit_terlambat, $rentang->poin),
            null,
            'diajukan',
            $penggunaId,
            ['absensi_siswa_id' => $absensi->id, 'menit_terlambat' => $absensi->menit_terlambat, 'poin' => $rentang->poin],
        );

        return $laporan;
    }

    private function perbaruiLaporan(
        LaporanPembinaanSiswa $laporan,
        AbsensiSiswa $absensi,
        RentangPoinKeterlambatan $rentang,
        ?int $penggunaId,
    ): void {
        $statusSebelum = $laporan->status_verifikasi;
        $statusFinalDenganPoinTetap = $statusSebelum === 'disahkan'
            && (int) $laporan->total_poin === (int) $rentang->poin;

        $laporan->update([
            'waktu_kejadian' => $absensi->jam_masuk,
            'rentang_poin_keterlambatan_id' => $rentang->id,
            'menit_terlambat_tercatat' => $absensi->menit_terlambat,
            'diproses_otomatis_pada' => now(),
            'total_poin' => $rentang->poin,
            'kronologi' => $this->buatKronologi($absensi, $rentang),
            'status_verifikasi' => $statusFinalDenganPoinTetap ? 'disahkan' : 'diajukan',
        ]);

        $laporan->butirPelanggaranLaporan()->update([
            'poin' => $rentang->poin,
            'catatan' => $rentang->labelRentang().' berdasarkan pengaturan tahun pelajaran.',
        ]);

        if (! $statusFinalDenganPoinTetap) {
            $laporan->verifikasiBkPelanggaran()->delete();
            $laporan->persetujuanPelanggaran()->delete();
            $this->pengaturanBatas->tetapkanBatas($laporan, 'diajukan');
        }

        $this->riwayatPembinaan->catat(
            $laporan,
            'sinkronisasi_koreksi_absensi',
            'Laporan disinkronkan dengan koreksi presensi',
            $statusFinalDenganPoinTetap
                ? 'Menit keterlambatan diperbarui tanpa mengubah poin yang telah disahkan.'
                : 'Data dan proses verifikasi dimulai kembali karena koreksi presensi.',
            $statusSebelum,
            $laporan->fresh()->status_verifikasi,
            $penggunaId,
            ['menit_terlambat' => $absensi->menit_terlambat, 'poin' => $rentang->poin],
        );
    }

    private function batalkanLaporanAktif(?LaporanPembinaanSiswa $laporan, ?int $penggunaId, string $alasan): bool
    {
        if (! $laporan || in_array($laporan->status_verifikasi, ['tidak_terbukti', 'dibatalkan'], true)) {
            return false;
        }

        if ($laporan->status_verifikasi === 'disahkan') {
            $this->prosesPoin->batalkanPoinLaporan($laporan, $penggunaId, $alasan);

            return true;
        }

        $statusSebelum = $laporan->status_verifikasi;
        $laporan->update([
            'status' => 'dibatalkan',
            'status_verifikasi' => 'dibatalkan',
            'tahap_batas_proses' => null,
            'batas_proses_pada' => null,
        ]);
        $this->riwayatPembinaan->catat(
            $laporan,
            'laporan_otomatis_dibatalkan',
            'Laporan otomatis dibatalkan',
            $alasan,
            $statusSebelum,
            'dibatalkan',
            $penggunaId,
        );

        return true;
    }

    private function tandaiAbsensi(AbsensiSiswa $absensi, string $status, int $poin): void
    {
        $absensi->update([
            'status_poin_keterlambatan' => $status,
            'poin_keterlambatan_terhitung' => $poin,
            'poin_keterlambatan_diproses_pada' => now(),
        ]);
    }

    private function jenisPelanggaranKeterlambatan(): JenisPelanggaranSiswa
    {
        $kategori = KategoriPembinaanSiswa::firstOrCreate(
            ['kode' => 'KEHADIRAN'],
            [
                'nama' => 'Kehadiran',
                'deskripsi' => 'Catatan terkait kehadiran dan keterlambatan siswa.',
                'aktif' => true,
            ],
        );

        return JenisPelanggaranSiswa::firstOrCreate(
            ['kode' => 'R001'],
            [
                'kategori_pembinaan_siswa_id' => $kategori->id,
                'nama' => 'Terlambat datang ke sekolah',
                'tingkat' => 'ringan',
                'poin' => 15,
                'urutan' => ((int) JenisPelanggaranSiswa::max('urutan')) + 1,
                'aktif' => true,
            ],
        );
    }

    private function buatKronologi(AbsensiSiswa $absensi, RentangPoinKeterlambatan $rentang): string
    {
        return sprintf(
            'Rekap presensi mencatat siswa scan masuk pukul %s dan terlambat %d menit. Sistem mengajukan %d poin sesuai rentang %s untuk pemeriksaan BK.',
            substr((string) $absensi->jam_masuk, 0, 5),
            $absensi->menit_terlambat,
            $rentang->poin,
            $rentang->labelRentang(),
        );
    }

    /** @param array<int, int> $laporanIds */
    private function kirimNotifikasiRingkas(CarbonImmutable $tanggal, array $laporanIds, ?int $penggunaId): void
    {
        LaporanPembinaanSiswa::query()
            ->with('kelas:id,nama,tingkat')
            ->whereIn('id', $laporanIds)
            ->get()
            ->groupBy(fn (LaporanPembinaanSiswa $laporan) => $this->penugasanBk->tingkatLaporan($laporan) ?? 'tanpa-tingkat')
            ->each(function ($laporanTingkat, int|string $tingkat) use ($tanggal, $laporanIds, $penggunaId): void {
                $jumlah = $laporanTingkat->count();
                $this->notifikasi->kirimKeBanyak(
                    $this->penugasanBk->penerimaNotifikasi($laporanTingkat->first(), $penggunaId),
                    'peringatan',
                    'Laporan keterlambatan menunggu pemeriksaan',
                    sprintf(
                        '%d laporan keterlambatan%s tanggal %s dibuat dari rekap presensi.',
                        $jumlah,
                        is_numeric($tingkat) ? ' tingkat '.$tingkat : '',
                        $tanggal->locale('id')->translatedFormat('d F Y'),
                    ),
                    route('pusat-verifikasi-pelanggaran.index', ['antrean' => 'bk'], false),
                    'laporan-keterlambatan:'.$tanggal->format('Ymd').':'.max($laporanIds),
                    ['jumlah' => $jumlah, 'laporan_ids' => $laporanTingkat->pluck('id')->all(), 'tingkat' => is_numeric($tingkat) ? (int) $tingkat : null],
                );
            });
    }
}
