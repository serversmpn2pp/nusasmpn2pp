<?php

namespace App\Console\Commands;

use App\Models\LaporanPembinaanSiswa;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use App\Services\Pembinaan\AntreanVerifikasiPelanggaranService;
use App\Services\Pembinaan\PengaturanBatasProsesPelanggaranService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class IngatkanBatasProsesPelanggaran extends Command
{
    protected $signature = 'pembinaan:ingatkan-batas-proses';

    protected $description = 'Mengirim pengingat tenggat proses pelanggaran kepada petugas terkait';

    public function handle(
        NotifikasiPenggunaService $notifikasi,
        PengaturanBatasProsesPelanggaranService $pengaturanService,
        AntreanVerifikasiPelanggaranService $antreanService,
    ): int {
        $jumlahDiproses = 0;
        $sekarang = now();

        LaporanPembinaanSiswa::query()
            ->with(['siswa:id,nama_lengkap', 'kelas:id,nama'])
            ->where('jenis_laporan', 'pelanggaran')
            ->whereNotIn('status_verifikasi', AntreanVerifikasiPelanggaranService::STATUS_FINAL)
            ->whereNotNull('batas_proses_pada')
            ->orderBy('id')
            ->chunkById(100, function ($laporan) use (
                $notifikasi,
                $pengaturanService,
                $antreanService,
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

                    $penerima = $this->penerimaTahap($item, $notifikasi, $antreanService);
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
        LaporanPembinaanSiswa $laporan,
        NotifikasiPenggunaService $notifikasi,
        AntreanVerifikasiPelanggaranService $antreanService,
    ): Collection {
        if ($laporan->tahap_batas_proses === PengaturanBatasProsesPelanggaranService::TAHAP_PEMERIKSAAN_BK) {
            return $notifikasi->penggunaDenganIzin('poin_siswa.verifikasi_bk')->unique('id')->values();
        }

        if ($laporan->tahap_batas_proses === PengaturanBatasProsesPelanggaranService::TAHAP_MUSYAWARAH) {
            return $notifikasi->penggunaDenganIzin('poin_siswa.putus_konflik')->unique('id')->values();
        }

        $penerima = collect();
        foreach (array_filter([$laporan->wali_kelas_pegawai_id, $laporan->guru_wali_pegawai_id]) as $pegawaiId) {
            $penerima = $penerima->merge($notifikasi->penggunaUntukPegawai((int) $pegawaiId));
        }

        if ($antreanService->memerlukanPengganti($laporan)) {
            $penerima = $penerima->merge($notifikasi->penggunaDenganIzin('poin_siswa.putus_konflik'));
        }

        return $penerima->unique('id')->values();
    }

    private function labelTahap(?string $tahap): string
    {
        return match ($tahap) {
            PengaturanBatasProsesPelanggaranService::TAHAP_PEMERIKSAAN_BK => 'pemeriksaan BK',
            PengaturanBatasProsesPelanggaranService::TAHAP_PERSETUJUAN => 'persetujuan Wali Kelas dan Guru Wali',
            PengaturanBatasProsesPelanggaranService::TAHAP_MUSYAWARAH => 'musyawarah/penyetuju pengganti',
            default => 'verifikasi',
        };
    }
}
