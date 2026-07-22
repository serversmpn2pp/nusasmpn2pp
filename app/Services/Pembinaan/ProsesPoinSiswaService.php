<?php

namespace App\Services\Pembinaan;

use App\Models\AturanSanksiPoin;
use App\Models\LaporanPembinaanSiswa;
use App\Models\PenguranganPoinSiswa;
use App\Models\SanksiPoinSiswa;
use App\Models\TransaksiPoinSiswa;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProsesPoinSiswaService
{
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

            $kunci = 'pelanggaran:' . $laporan->id;
            if (TransaksiPoinSiswa::where('kunci_sumber', $kunci)->exists()) {
                $laporan->update([
                    'status_verifikasi' => 'disahkan',
                    'total_poin' => $totalButir,
                ]);

                return;
            }

            $poinSebelum = $this->totalPoin($laporan->siswa_id, $laporan->tahun_pelajaran_id);

            TransaksiPoinSiswa::create([
                'siswa_id' => $laporan->siswa_id,
                'tahun_pelajaran_id' => $laporan->tahun_pelajaran_id,
                'laporan_pembinaan_siswa_id' => $laporan->id,
                'kunci_sumber' => $kunci,
                'jenis' => 'pelanggaran',
                'poin' => $totalButir,
                'keterangan' => 'Poin dari laporan ' . $laporan->nomor_laporan,
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

            $this->buatSanksiYangTerpicu($laporan->siswa_id, $laporan->tahun_pelajaran_id, $poinSebelum, $poinSesudah);
        });
    }

    public function batalkanPoinLaporan(LaporanPembinaanSiswa $laporan): void
    {
        DB::transaction(function () use ($laporan) {
            $laporan = LaporanPembinaanSiswa::query()->lockForUpdate()->findOrFail($laporan->id);
            $transaksiAwal = TransaksiPoinSiswa::where('kunci_sumber', 'pelanggaran:' . $laporan->id)->first();

            if ($transaksiAwal && ! TransaksiPoinSiswa::where('kunci_sumber', 'pembatalan:' . $laporan->id)->exists()) {
                TransaksiPoinSiswa::create([
                    'siswa_id' => $laporan->siswa_id,
                    'tahun_pelajaran_id' => $laporan->tahun_pelajaran_id,
                    'laporan_pembinaan_siswa_id' => $laporan->id,
                    'kunci_sumber' => 'pembatalan:' . $laporan->id,
                    'jenis' => 'pembatalan',
                    'poin' => -abs($transaksiAwal->poin),
                    'keterangan' => 'Pembatalan poin laporan ' . $laporan->nomor_laporan,
                    'tercatat_pada' => now(),
                    'dibuat_oleh_pengguna_id' => auth()->id(),
                ]);
            }

            $laporan->update([
                'status' => 'dibatalkan',
                'status_verifikasi' => 'dibatalkan',
            ]);
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
                    ['kunci_sumber' => 'reward:' . $pengurangan->id],
                    [
                        'siswa_id' => $pengurangan->siswa_id,
                        'tahun_pelajaran_id' => $pengurangan->tahun_pelajaran_id,
                        'pengurangan_poin_siswa_id' => $pengurangan->id,
                        'jenis' => 'pengurangan',
                        'poin' => -$diterapkan,
                        'keterangan' => 'Pengurangan poin: ' . $pengurangan->jenis_kegiatan,
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
                SanksiPoinSiswa::firstOrCreate(
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
            });
    }
}
