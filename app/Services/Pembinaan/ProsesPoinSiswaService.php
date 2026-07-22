<?php

namespace App\Services\Pembinaan;

use App\Models\AturanSanksiPoin;
use App\Models\LaporanPembinaanSiswa;
use App\Models\PenguranganPoinSiswa;
use App\Models\SanksiPoinSiswa;
use App\Models\TransaksiPoinSiswa;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProsesPoinSiswaService
{
    public function __construct(
        private CatatRiwayatPembinaanService $riwayatPembinaan,
        private PengaturanBatasProsesPelanggaranService $pengaturanBatasProses,
        private CatatRiwayatSanksiPoinService $riwayatSanksi,
        private NotifikasiPenggunaService $notifikasi,
    ) {}

    public function totalPoin(int $siswaId, int $tahunPelajaranId): int
    {
        return max(0, (int) TransaksiPoinSiswa::query()
            ->where('siswa_id', $siswaId)
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->sum('poin'));
    }

    public function perbaruiStatusPersetujuan(LaporanPembinaanSiswa $laporan): bool
    {
        if ($laporan->jenis_laporan !== 'pelanggaran') {
            return false;
        }

        $verifikasiTerakhir = $laporan->verifikasiBkPelanggaran()
            ->latest('diverifikasi_pada')
            ->first();

        if (! $verifikasiTerakhir || $verifikasiTerakhir->hasil !== 'terbukti') {
            return false;
        }

        $persetujuan = $laporan->persetujuanPelanggaran()->get();
        $jumlahOrangSetuju = $persetujuan
            ->where('keputusan', 'setuju')
            ->pluck('pegawai_id')
            ->filter()
            ->unique()
            ->count();

        if ($jumlahOrangSetuju >= 2) {
            $this->sahkanLaporan($laporan);

            return true;
        }

        $status = match (true) {
            $persetujuan->contains('keputusan', 'tidak_setuju') => 'perlu_musyawarah',
            $jumlahOrangSetuju === 1 => 'disetujui_sebagian',
            default => 'menunggu_persetujuan',
        };

        $laporan->update(['status_verifikasi' => $status]);
        [$tahapBaru] = $this->pengaturanBatasProses->tahapDanJumlahHari($status, $laporan->tahun_pelajaran_id);
        if ($tahapBaru && $laporan->tahap_batas_proses !== $tahapBaru) {
            $this->pengaturanBatasProses->tetapkanBatas($laporan, $status);
        }

        return false;
    }

    public function sahkanLaporan(LaporanPembinaanSiswa $laporan): void
    {
        DB::transaction(function () use ($laporan) {
            $laporan = LaporanPembinaanSiswa::query()->lockForUpdate()->findOrFail($laporan->id);

            if ($laporan->jenis_laporan !== 'pelanggaran' || ! $laporan->tahun_pelajaran_id) {
                throw ValidationException::withMessages([
                    'laporan' => 'Laporan pelanggaran belum memiliki tahun pelajaran yang valid.',
                ]);
            }

            $totalButir = (int) $laporan->butirPelanggaranLaporan()->sum('poin');
            if ($totalButir <= 0) {
                throw ValidationException::withMessages([
                    'laporan' => 'Laporan belum memiliki butir pelanggaran berpoin.',
                ]);
            }

            $kunci = 'pelanggaran:'.$laporan->id;
            if (TransaksiPoinSiswa::where('kunci_sumber', $kunci)->exists()) {
                $laporan->update([
                    'status_verifikasi' => 'disahkan',
                    'total_poin' => $totalButir,
                ]);

                return;
            }

            $poinSebelum = $this->totalPoin($laporan->siswa_id, $laporan->tahun_pelajaran_id);
            $statusSebelum = $laporan->status_verifikasi;

            TransaksiPoinSiswa::create([
                'siswa_id' => $laporan->siswa_id,
                'tahun_pelajaran_id' => $laporan->tahun_pelajaran_id,
                'laporan_pembinaan_siswa_id' => $laporan->id,
                'kunci_sumber' => $kunci,
                'jenis' => 'pelanggaran',
                'poin' => $totalButir,
                'keterangan' => 'Poin dari laporan '.$laporan->nomor_laporan,
                'tercatat_pada' => now(),
                'dibuat_oleh_pengguna_id' => auth()->id(),
            ]);

            $poinSesudah = $poinSebelum + $totalButir;
            $laporan->update([
                'status_verifikasi' => 'disahkan',
                'status' => $laporan->status === 'baru' ? 'diproses' : $laporan->status,
                'total_poin' => $totalButir,
                'poin_ditetapkan_pada' => now(),
            ]);

            $this->riwayatPembinaan->catat(
                $laporan,
                'poin_disahkan',
                'Poin pelanggaran disahkan',
                $totalButir.' poin resmi ditetapkan setelah dua persetujuan.',
                $statusSebelum,
                'disahkan',
                auth()->id(),
                ['total_poin' => $totalButir],
            );

            $this->buatSanksiYangTerpicu($laporan->siswa_id, $laporan->tahun_pelajaran_id, $poinSebelum, $poinSesudah);
        });
    }

    public function batalkanPoinLaporan(LaporanPembinaanSiswa $laporan, ?int $penggunaId = null, ?string $alasan = null): void
    {
        DB::transaction(function () use ($laporan, $penggunaId, $alasan) {
            $laporan = LaporanPembinaanSiswa::query()->lockForUpdate()->findOrFail($laporan->id);
            $statusSebelum = $laporan->status_verifikasi;
            $transaksiAwal = TransaksiPoinSiswa::where('kunci_sumber', 'pelanggaran:'.$laporan->id)->first();

            if ($transaksiAwal && ! TransaksiPoinSiswa::where('kunci_sumber', 'pembatalan:'.$laporan->id)->exists()) {
                TransaksiPoinSiswa::create([
                    'siswa_id' => $laporan->siswa_id,
                    'tahun_pelajaran_id' => $laporan->tahun_pelajaran_id,
                    'laporan_pembinaan_siswa_id' => $laporan->id,
                    'kunci_sumber' => 'pembatalan:'.$laporan->id,
                    'jenis' => 'pembatalan',
                    'poin' => -abs($transaksiAwal->poin),
                    'keterangan' => 'Pembatalan poin laporan '.$laporan->nomor_laporan,
                    'tercatat_pada' => now(),
                    'dibuat_oleh_pengguna_id' => $penggunaId ?? auth()->id(),
                ]);
            }

            $laporan->update([
                'status' => 'dibatalkan',
                'status_verifikasi' => 'dibatalkan',
            ]);
            $this->riwayatPembinaan->catat(
                $laporan,
                'laporan_dibatalkan',
                'Laporan dibatalkan',
                $alasan ?: ($transaksiAwal ? 'Laporan dibatalkan dan transaksi poin dikoreksi.' : 'Laporan dibatalkan sebelum poin ditetapkan.'),
                $statusSebelum,
                'dibatalkan',
                $penggunaId ?? auth()->id(),
            );
        });
    }

    public function setujuiPengurangan(PenguranganPoinSiswa $pengurangan, ?int $pegawaiId, ?string $catatan = null): int
    {
        return DB::transaction(function () use ($pengurangan, $pegawaiId, $catatan) {
            $pengurangan = PenguranganPoinSiswa::query()->lockForUpdate()->findOrFail($pengurangan->id);
            $saldo = $this->totalPoin($pengurangan->siswa_id, $pengurangan->tahun_pelajaran_id);
            $diterapkan = min($saldo, (int) $pengurangan->poin_pengurangan);

            $pengurangan->update([
                'poin_pengurangan' => $diterapkan,
                'status' => 'disetujui',
                'disetujui_oleh_pegawai_id' => $pegawaiId,
                'diputuskan_pada' => now(),
                'catatan_keputusan' => filled($catatan) ? trim($catatan) : null,
            ]);

            if ($diterapkan > 0) {
                TransaksiPoinSiswa::firstOrCreate(
                    ['kunci_sumber' => 'reward:'.$pengurangan->id],
                    [
                        'siswa_id' => $pengurangan->siswa_id,
                        'tahun_pelajaran_id' => $pengurangan->tahun_pelajaran_id,
                        'pengurangan_poin_siswa_id' => $pengurangan->id,
                        'jenis' => 'pengurangan',
                        'poin' => -$diterapkan,
                        'keterangan' => 'Pengurangan poin: '.$pengurangan->jenis_kegiatan,
                        'tercatat_pada' => now(),
                        'dibuat_oleh_pengguna_id' => auth()->id(),
                    ],
                );
            }

            return $diterapkan;
        });
    }

    private function buatSanksiYangTerpicu(int $siswaId, int $tahunPelajaranId, int $sebelum, int $sesudah): void
    {
        AturanSanksiPoin::query()
            ->where('aktif', true)
            ->where('batas_poin', '>', $sebelum)
            ->where('batas_poin', '<=', $sesudah)
            ->orderBy('batas_poin')
            ->each(function (AturanSanksiPoin $aturan) use ($siswaId, $tahunPelajaranId, $sesudah) {
                $sanksi = SanksiPoinSiswa::firstOrCreate(
                    [
                        'siswa_id' => $siswaId,
                        'tahun_pelajaran_id' => $tahunPelajaranId,
                        'aturan_sanksi_poin_id' => $aturan->id,
                    ],
                    [
                        'poin_saat_terpicu' => $sesudah,
                        'status' => 'menunggu',
                        'terpicu_pada' => now(),
                    ],
                );

                if ($sanksi->wasRecentlyCreated) {
                    $this->riwayatSanksi->catat(
                        $sanksi,
                        'sanksi_terpicu',
                        'Sanksi terbentuk dari akumulasi poin',
                        null,
                        'menunggu',
                        $aturan->nama.' terpicu saat saldo mencapai '.$sesudah.' poin.',
                        auth()->id(),
                        ['poin_saat_terpicu' => $sesudah, 'batas_poin' => $aturan->batas_poin],
                    );

                    $sanksi->loadMissing(['siswa:id,nama_lengkap', 'aturanSanksiPoin:id,nama']);
                    $this->notifikasi->kirimKeBanyak(
                        $this->notifikasi->penggunaDenganIzin('poin_siswa.sanksi_kelola', auth()->id()),
                        'peringatan',
                        'Sanksi siswa perlu ditindaklanjuti',
                        sprintf('%s untuk %s terpicu pada saldo %d poin.', $aturan->nama, $sanksi->siswa?->nama_lengkap ?? 'siswa', $sesudah),
                        route('sanksi-poin-siswa.show', $sanksi, false),
                        "sanksi-terpicu:{$sanksi->id}",
                    );
                }
            });
    }
}
