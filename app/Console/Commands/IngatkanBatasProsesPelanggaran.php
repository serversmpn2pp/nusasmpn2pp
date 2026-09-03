<?php

namespace App\Console\Commands;

use App\Models\LaporanPembinaanSiswa;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use App\Services\Pembinaan\AntreanVerifikasiPelanggaranService;
use App\Services\Pembinaan\PengaturanBatasProsesPelanggaranService;
use App\Services\Pembinaan\PenugasanGuruBkTingkatService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class IngatkanBatasProsesPelanggaran extends Command
{
    protected $signature = 'pembinaan:ingatkan-batas-proses';

    protected $description = 'Mengirim pengingat tenggat proses pelanggaran kepada petugas terkait';

    public function handle(
        NotifikasiPenggunaService $notifikasi,
        PengaturanBatasProsesPelanggaranService $pengaturanService,
        PenugasanGuruBkTingkatService $penugasanBk,
    ): int {
        $jumlahDiproses = 0;
        $sekarang = now();

        LaporanPembinaanSiswa::query()
            ->with(['siswa:id,nama_lengkap', 'kelas:id,nama,tingkat'])
            ->where('status_verifikasi', '!=', 'tidak_perlu')
            ->whereNotIn('status_verifikasi', AntreanVerifikasiPelanggaranService::STATUS_FINAL)
            ->whereNotNull('batas_proses_pada')
            ->orderBy('id')
            ->chunkById(100, function ($laporan) use (
                $notifikasi,
                $pengaturanService,
                $penugasanBk,
                $sekarang,
                &$jumlahDiproses,
            ) {
                foreach ($laporan as $item) {
                    $pengaturan = $pengaturanService->nilaiUntukTahun($item->tahun_pelajaran_id);
                    $terlambat = $sekarang->greaterThanOrEqualTo($item->batas_proses_pada);
                    $hariKeBatas = (int) $sekarang->copy()->startOfDay()
                        ->diffInDays($item->batas_proses_pada->copy()->startOfDay(), false);

                    $jenisNotifikasi = null;
                    if ($terlambat && $pengaturan->notifikasi_terlambat_aktif) {
                        $jenisNotifikasi = 'terlambat';
                    } elseif (
                        ! $terlambat
                        && $pengaturan->notifikasi_pengingat_aktif
                        && $hariKeBatas >= 0
                        && $hariKeBatas <= (int) $pengaturan->pengingat_hari_sebelum_batas
                    ) {
                        $jenisNotifikasi = 'pengingat';
                    }

                    if (! $jenisNotifikasi) {
                        continue;
                    }

                    $penerima = $this->penerimaTahap($notifikasi, $penugasanBk, $item);
                    if ($penerima->isEmpty()) {
                        continue;
                    }

                    $labelTahap = $this->labelTahap($item->tahap_batas_proses);
                    $judul = $jenisNotifikasi === 'terlambat'
                        ? 'Batas proses pelanggaran terlewati'
                        : 'Batas proses pelanggaran segera tiba';
                    $pesan = sprintf(
                        '%s untuk %s (%s) pada tahap %s. Batas: %s.',
                        $item->nomor_laporan,
                        $item->siswa?->nama_lengkap ?? 'siswa',
                        $item->kelas?->nama ?? 'kelas belum ditentukan',
                        $labelTahap,
                        $item->batas_proses_pada->format('d/m/Y H:i'),
                    );
                    $kunci = sprintf(
                        'batas-proses:%d:%s:%s:%s',
                        $item->id,
                        $item->tahap_batas_proses,
                        $item->batas_proses_pada->format('YmdHi'),
                        $jenisNotifikasi,
                    );

                    $notifikasi->kirimKeBanyak(
                        $penerima,
                        $jenisNotifikasi === 'terlambat' ? 'penting' : 'peringatan',
                        $judul,
                        $pesan,
                        route('laporan-pembinaan-siswa.show', $item, false),
                        $kunci,
                        ['laporan_id' => $item->id, 'tahap' => $item->tahap_batas_proses],
                    );
                    $jumlahDiproses++;
                }
            });

        $this->info("Pengingat batas proses selesai. {$jumlahDiproses} laporan diproses.");

        return self::SUCCESS;
    }

    private function penerimaTahap(
        NotifikasiPenggunaService $notifikasi,
        PenugasanGuruBkTingkatService $penugasanBk,
        LaporanPembinaanSiswa $laporan,
    ): Collection {
        if ($laporan->tahap_batas_proses === PengaturanBatasProsesPelanggaranService::TAHAP_PENGESAHAN_WAKIL) {
            return $notifikasi->penggunaDenganIzin('poin_siswa.sahkan_wakil')->unique('id')->values();
        }

        return $penugasanBk->penerimaNotifikasi($laporan)->unique('id')->values();
    }

    private function labelTahap(?string $tahap): string
    {
        return match ($tahap) {
            PengaturanBatasProsesPelanggaranService::TAHAP_PEMERIKSAAN_BK => 'keputusan BK',
            PengaturanBatasProsesPelanggaranService::TAHAP_PENGESAHAN_WAKIL => 'pengesahan Wakil Kesiswaan',
            default => 'pemeriksaan BK',
        };
    }
}
