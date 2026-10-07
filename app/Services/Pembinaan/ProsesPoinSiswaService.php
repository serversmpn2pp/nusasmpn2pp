<?php

namespace App\Services\Pembinaan;

use App\Models\AturanSanksiPoin;
use App\Models\JenisPelanggaranSiswa;
use App\Models\LaporanPembinaanSiswa;
use App\Models\PenguranganPoinSiswa;
use App\Models\SanksiPoinSiswa;
use App\Models\Siswa;
use App\Models\TransaksiPoinSiswa;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProsesPoinSiswaService
{
    public function __construct(
        private CatatRiwayatPembinaanService $riwayatPembinaan,
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

    public function tetapkanPoinPresensi(LaporanPembinaanSiswa $laporan, int $poin, ?int $penggunaId, string $alasan): bool
    {
        return DB::transaction(function () use ($laporan, $poin, $penggunaId, $alasan) {
            $laporan = LaporanPembinaanSiswa::lockForUpdate()->findOrFail($laporan->id);
            abort_unless($laporan->kunci_presensi_otomatis, 422);
            Siswa::lockForUpdate()->findOrFail($laporan->siswa_id);
            $transaksi = TransaksiPoinSiswa::where('laporan_pembinaan_siswa_id', $laporan->id);
            $sebelumKejadian = (int) (clone $transaksi)->sum('poin');
            $selisih = $poin - $sebelumKejadian;
            if ($selisih === 0) {
                return false;
            }
            $saldoSebelum = $this->totalPoin($laporan->siswa_id, $laporan->tahun_pelajaran_id);
            $statusSebelum = $laporan->status_verifikasi;
            TransaksiPoinSiswa::create([
                'siswa_id' => $laporan->siswa_id,
                'tahun_pelajaran_id' => $laporan->tahun_pelajaran_id,
                'laporan_pembinaan_siswa_id' => $laporan->id,
                'kunci_sumber' => 'presensi:'.$laporan->id.':'.((clone $transaksi)->count() + 1),
                'jenis' => $selisih > 0 ? 'pelanggaran' : 'pembatalan',
                'poin' => $selisih,
                'keterangan' => $alasan,
                'tercatat_pada' => now(),
                'dibuat_oleh_pengguna_id' => $penggunaId,
            ]);
            // Reversed violations must not leave banked negative points after earlier rewards.
            $saldoMentah = (int) TransaksiPoinSiswa::where('siswa_id', $laporan->siswa_id)
                ->where('tahun_pelajaran_id', $laporan->tahun_pelajaran_id)->sum('poin');
            if ($saldoMentah < 0) {
                TransaksiPoinSiswa::create([
                    'siswa_id' => $laporan->siswa_id, 'tahun_pelajaran_id' => $laporan->tahun_pelajaran_id,
                    'kunci_sumber' => 'penyesuaian-presensi:'.$laporan->id.':'.(clone $transaksi)->count(),
                    'jenis' => 'penyesuaian', 'poin' => abs($saldoMentah),
                    'keterangan' => 'Penyesuaian pengurangan terdahulu setelah koreksi presensi '.$laporan->nomor_laporan.'; saldo tidak menjadi kredit negatif.',
                    'tercatat_pada' => now(), 'dibuat_oleh_pengguna_id' => $penggunaId,
                ]);
            }
            $laporan->update([
                'total_poin' => $poin,
                'status_verifikasi' => $poin > 0 ? 'disahkan' : 'dibatalkan',
                'status' => $poin > 0 ? 'diproses' : 'dibatalkan',
                'poin_ditetapkan_pada' => $laporan->poin_ditetapkan_pada ?? now(),
                'tahap_batas_proses' => null,
                'batas_proses_pada' => null,
            ]);
            $this->riwayatPembinaan->catat(
                $laporan, 'poin_presensi_otomatis', $selisih > 0 ? 'Poin presensi ditetapkan otomatis' : 'Poin presensi dikoreksi',
                $alasan, $statusSebelum, $laporan->status_verifikasi, $penggunaId,
                ['poin_sebelum' => $sebelumKejadian, 'poin_sesudah' => $poin, 'selisih' => $selisih],
            );
            if ($selisih > 0) {
                $this->buatSanksiYangTerpicu($laporan->siswa_id, $laporan->tahun_pelajaran_id, $saldoSebelum, $saldoSebelum + $selisih);
            } else {
                $saldoSesudah = $this->totalPoin($laporan->siswa_id, $laporan->tahun_pelajaran_id);
                SanksiPoinSiswa::where('siswa_id', $laporan->siswa_id)->where('tahun_pelajaran_id', $laporan->tahun_pelajaran_id)
                    ->where('status', 'menunggu')->whereNull('petugas_pegawai_id')
                    ->whereHas('aturanSanksiPoin', fn ($q) => $q->where('batas_poin', '>', $saldoSesudah))
                    ->lockForUpdate()->get()->each(function ($sanksi) use ($penggunaId, $saldoSesudah) {
                        $sanksi->update(['status' => 'dibatalkan']);
                        $this->riwayatSanksi->catat($sanksi, 'koreksi_poin_presensi', 'Sanksi menunggu dibatalkan setelah koreksi poin',
                            'menunggu', 'dibatalkan', 'Saldo tidak lagi memenuhi batas sanksi. Sanksi yang sudah diproses tidak diubah.',
                            $penggunaId, ['saldo_poin' => $saldoSesudah]);
                    });
            }

            return true;
        });
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
                    'tahap_batas_proses' => null,
                    'batas_proses_pada' => null,
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
                'tahap_batas_proses' => null,
                'batas_proses_pada' => null,
            ]);

            $this->riwayatPembinaan->catat(
                $laporan,
                'poin_disahkan',
                'Poin pelanggaran disahkan',
                $totalButir.' poin resmi ditetapkan berdasarkan rekomendasi BK dan pengesahan Wakil Kesiswaan.',
                $statusSebelum,
                'disahkan',
                auth()->id(),
                ['total_poin' => $totalButir],
            );

            $this->buatSanksiYangTerpicu($laporan->siswa_id, $laporan->tahun_pelajaran_id, $poinSebelum, $poinSesudah);
        });
    }

    public function siapkanSanksiPoin(LaporanPembinaanSiswa $laporan, array $jenisPelanggaranIds): void
    {
        $jenisPelanggaranIds = collect($jenisPelanggaranIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();
        $jenisPelanggaran = JenisPelanggaranSiswa::query()
            ->whereIn('id', $jenisPelanggaranIds)
            ->where('aktif', true)
            ->orderBy('urutan')
            ->get();

        if ($jenisPelanggaran->isEmpty() || $jenisPelanggaran->count() !== $jenisPelanggaranIds->count()) {
            throw ValidationException::withMessages([
                'jenis_pelanggaran_ids' => 'Pilih minimal satu butir pelanggaran aktif untuk membuat rekomendasi poin.',
            ]);
        }

        DB::transaction(function () use ($laporan, $jenisPelanggaran) {
            $laporan = LaporanPembinaanSiswa::query()->lockForUpdate()->findOrFail($laporan->id);
            $urutanTingkat = ['ringan' => 1, 'sedang' => 2, 'berat' => 3];
            $tingkatTertinggi = $jenisPelanggaran
                ->sortByDesc(fn ($item) => $urutanTingkat[$item->tingkat] ?? 0)
                ->first()?->tingkat ?? 'ringan';

            $laporan->butirPelanggaranLaporan()->delete();
            foreach ($jenisPelanggaran as $jenis) {
                $laporan->butirPelanggaranLaporan()->create([
                    'jenis_pelanggaran_siswa_id' => $jenis->id,
                    'kode_pelanggaran' => $jenis->kode,
                    'nama_pelanggaran' => $jenis->nama,
                    'tingkat' => $jenis->tingkat,
                    'poin' => $jenis->poin,
                ]);
            }

            $laporan->update([
                'jenis_laporan' => 'pelanggaran',
                'kategori_pembinaan_siswa_id' => $jenisPelanggaran->first()?->kategori_pembinaan_siswa_id,
                'tingkat' => $tingkatTertinggi,
                'total_poin' => (int) $jenisPelanggaran->sum('poin'),
            ]);
        });
    }

    public function tetapkanPembinaan(LaporanPembinaanSiswa $laporan): void
    {
        DB::transaction(function () use ($laporan) {
            $laporan = LaporanPembinaanSiswa::query()->lockForUpdate()->findOrFail($laporan->id);
            $statusSebelum = $laporan->status_verifikasi;

            $laporan->butirPelanggaranLaporan()->delete();

            $laporan->update([
                'jenis_laporan' => 'pembinaan',
                'kategori_pembinaan_siswa_id' => null,
                'tingkat' => 'ringan',
                'status_verifikasi' => 'ditetapkan_pembinaan',
                'status' => $laporan->status === 'baru' ? 'diproses' : $laporan->status,
                'total_poin' => 0,
                'poin_ditetapkan_pada' => null,
                'tahap_batas_proses' => null,
                'batas_proses_pada' => null,
            ]);

            $this->riwayatPembinaan->catat(
                $laporan,
                'pembinaan_ditetapkan',
                'Pembinaan tanpa poin ditetapkan',
                'BK menetapkan kejadian ditangani melalui pembinaan tanpa penambahan poin.',
                $statusSebelum,
                'ditetapkan_pembinaan',
                auth()->id(),
            );
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

                $dipulihkan = $sanksi->status === 'dibatalkan'
                    && $sanksi->riwayatSanksiPoinSiswa()->latest('id')->value('jenis_kegiatan') === 'koreksi_poin_presensi';
                if ($dipulihkan) {
                    $sanksi->update(['status' => 'menunggu', 'poin_saat_terpicu' => $sesudah, 'terpicu_pada' => now()]);
                }
                if ($sanksi->wasRecentlyCreated || $dipulihkan) {
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
                        "sanksi-terpicu:{$sanksi->id}".($dipulihkan ? ':'.$sanksi->riwayatSanksiPoinSiswa()->count() : ''),
                    );
                }
            });
    }
}
