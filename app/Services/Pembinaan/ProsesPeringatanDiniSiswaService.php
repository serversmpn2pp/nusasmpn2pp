<?php

namespace App\Services\Pembinaan;

use App\Models\AbsensiSiswa;
use App\Models\AturanSanksiPoin;
use App\Models\LaporanPembinaanSiswa;
use App\Models\Pengguna;
use App\Models\PeringatanDiniSiswa;
use App\Models\SanksiPoinSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Notifikasi\NotifikasiPenggunaService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProsesPeringatanDiniSiswaService
{
    public function __construct(
        private PengaturanPeringatanDiniPoinService $pengaturan,
        private MonitoringPoinSiswaService $monitoring,
        private NotifikasiPenggunaService $notifikasi,
    ) {}

    public function proses(?int $tahunPelajaranId = null): array
    {
        $hasil = [
            'tahun_diproses' => 0,
            'siswa_diproses' => 0,
            'peringatan_baru' => 0,
            'peringatan_diperbarui' => 0,
            'peringatan_diselesaikan' => 0,
            'notifikasi_terkirim' => 0,
        ];

        $daftarTahun = TahunPelajaran::query()
            ->when($tahunPelajaranId, fn ($query) => $query->whereKey($tahunPelajaranId))
            ->when(! $tahunPelajaranId, fn ($query) => $query->where('aktif', true))
            ->orderByDesc('tanggal_mulai')
            ->get();

        foreach ($daftarTahun as $tahun) {
            $nilaiPengaturan = $this->pengaturan->nilaiUntukTahun($tahun->id);
            $hasil['tahun_diproses']++;

            if (! $nilaiPengaturan->aktif) {
                $hasil['peringatan_diselesaikan'] += $this->selesaikanSemuaPeringatanTahun($tahun->id);

                continue;
            }

            $siswa = Siswa::query()
                ->where('aktif', true)
                ->whereHas('anggotaKelas', fn ($query) => $query
                    ->where('tahun_pelajaran_id', $tahun->id)
                    ->where('status_keanggotaan', 'aktif'))
                ->with([
                    'anggotaKelas' => fn ($query) => $query
                        ->where('tahun_pelajaran_id', $tahun->id)
                        ->where('status_keanggotaan', 'aktif')
                        ->with('kelas:id,nama,wali_kelas_id'),
                    'penugasanGuruWaliSiswa' => fn ($query) => $query
                        ->where('aktif', true)
                        ->with('guruWali:id,nama_lengkap'),
                ])
                ->get();

            $hasil['siswa_diproses'] += $siswa->count();
            if ($siswa->isEmpty()) {
                $hasil['peringatan_diselesaikan'] += $this->selesaikanSemuaPeringatanTahun($tahun->id);

                continue;
            }

            $siswaIds = $siswa->pluck('id');
            $saldo = $this->monitoring->saldoPoinPerSiswa($siswaIds, $tahun->id);
            $aturanSanksi = $this->monitoring->aturanAktif();
            $pelanggaran = $this->rekapPelanggaranBerulang(
                $siswaIds,
                $tahun->id,
                (int) $nilaiPengaturan->periode_pelanggaran_hari,
            );
            $keterlambatan = $this->rekapKeterlambatan(
                $siswaIds,
                $tahun->id,
                (int) $nilaiPengaturan->periode_keterlambatan_hari,
            );
            $sanksiAktif = SanksiPoinSiswa::query()
                ->with('aturanSanksiPoin:id,batas_poin,nama')
                ->whereIn('siswa_id', $siswaIds)
                ->where('tahun_pelajaran_id', $tahun->id)
                ->whereNotIn('status', SanksiPoinSiswa::STATUS_FINAL)
                ->get()
                ->groupBy('siswa_id');

            foreach ($siswa as $item) {
                $deteksi = collect();
                $totalPoin = (int) $saldo->get($item->id, 0);

                $deteksi = $deteksi->merge($this->deteksiMendekatiSanksi(
                    $item,
                    $tahun,
                    $totalPoin,
                    $aturanSanksi,
                    (int) $nilaiPengaturan->persentase_mendekati_ambang,
                ));
                $deteksi = $deteksi->merge($this->deteksiPelanggaranBerulang(
                    $item,
                    $tahun,
                    $pelanggaran->get($item->id),
                    (int) $nilaiPengaturan->jumlah_pelanggaran_berulang,
                    (int) $nilaiPengaturan->periode_pelanggaran_hari,
                ));
                $deteksi = $deteksi->merge($this->deteksiKeterlambatan(
                    $item,
                    $tahun,
                    $keterlambatan->get($item->id),
                    (int) $nilaiPengaturan->jumlah_keterlambatan_berulang,
                    (int) $nilaiPengaturan->periode_keterlambatan_hari,
                ));
                $deteksi = $deteksi->merge($this->deteksiSanksiAktif(
                    $item,
                    $tahun,
                    $sanksiAktif->get($item->id, collect()),
                ));

                $hasilSiswa = $this->sinkronkanPeringatanSiswa(
                    $item,
                    $tahun,
                    $deteksi,
                    (bool) $nilaiPengaturan->notifikasi_aktif,
                );

                foreach ($hasilSiswa as $kunci => $jumlah) {
                    $hasil[$kunci] += $jumlah;
                }
            }

            $hasil['peringatan_diselesaikan'] += PeringatanDiniSiswa::query()
                ->where('tahun_pelajaran_id', $tahun->id)
                ->where('status', 'aktif')
                ->whereNotIn('siswa_id', $siswaIds)
                ->update([
                    'status' => 'selesai',
                    'diselesaikan_pada' => now(),
                    'updated_at' => now(),
                ]);
        }

        return $hasil;
    }

    private function deteksiMendekatiSanksi(
        Siswa $siswa,
        TahunPelajaran $tahun,
        int $totalPoin,
        Collection $aturan,
        int $persentaseMinimum,
    ): Collection {
        $aturanBerikutnya = $aturan->first(fn (AturanSanksiPoin $item) => $item->batas_poin > $totalPoin);
        if (! $aturanBerikutnya || $totalPoin <= 0) {
            return collect();
        }

        $persentase = (int) floor(($totalPoin / max(1, $aturanBerikutnya->batas_poin)) * 100);
        if ($persentase < $persentaseMinimum) {
            return collect();
        }

        $jarak = $aturanBerikutnya->batas_poin - $totalPoin;

        return collect([[
            'jenis' => 'mendekati_sanksi',
            'tingkat' => $persentase >= 90 || $jarak <= 5 ? 'penting' : 'peringatan',
            'kunci_unik' => "peringatan:poin:{$tahun->id}:{$siswa->id}:ambang:{$aturanBerikutnya->id}",
            'judul' => 'Siswa mendekati ambang sanksi',
            'pesan' => "{$siswa->nama_lengkap} memiliki {$totalPoin} poin. Tersisa {$jarak} poin menuju {$aturanBerikutnya->nama}.",
            'data_pendukung' => [
                'total_poin' => $totalPoin,
                'ambang_poin' => $aturanBerikutnya->batas_poin,
                'jarak_poin' => $jarak,
                'persentase' => $persentase,
                'aturan_sanksi_poin_id' => $aturanBerikutnya->id,
            ],
        ]]);
    }

    private function deteksiPelanggaranBerulang(
        Siswa $siswa,
        TahunPelajaran $tahun,
        $rekap,
        int $batasJumlah,
        int $periodeHari,
    ): Collection {
        $jumlah = (int) ($rekap?->jumlah ?? 0);
        if ($jumlah < $batasJumlah) {
            return collect();
        }

        $totalPoin = (int) ($rekap?->total_poin ?? 0);

        return collect([[
            'jenis' => 'pelanggaran_berulang',
            'tingkat' => $jumlah >= $batasJumlah + 2 ? 'penting' : 'peringatan',
            'kunci_unik' => "peringatan:pelanggaran-berulang:{$tahun->id}:{$siswa->id}",
            'judul' => 'Pelanggaran siswa berulang',
            'pesan' => "{$siswa->nama_lengkap} memiliki {$jumlah} pelanggaran yang disahkan dalam {$periodeHari} hari terakhir.",
            'data_pendukung' => [
                'jumlah_pelanggaran' => $jumlah,
                'total_poin_periode' => $totalPoin,
                'periode_hari' => $periodeHari,
            ],
        ]]);
    }

    private function deteksiKeterlambatan(
        Siswa $siswa,
        TahunPelajaran $tahun,
        $rekap,
        int $batasJumlah,
        int $periodeHari,
    ): Collection {
        $jumlah = (int) ($rekap?->jumlah ?? 0);
        if ($jumlah < $batasJumlah) {
            return collect();
        }

        $totalMenit = (int) ($rekap?->total_menit ?? 0);

        return collect([[
            'jenis' => 'sering_terlambat',
            'tingkat' => $jumlah >= $batasJumlah + 3 ? 'penting' : 'peringatan',
            'kunci_unik' => "peringatan:sering-terlambat:{$tahun->id}:{$siswa->id}",
            'judul' => 'Keterlambatan siswa berulang',
            'pesan' => "{$siswa->nama_lengkap} terlambat {$jumlah} kali dengan total {$totalMenit} menit dalam {$periodeHari} hari terakhir.",
            'data_pendukung' => [
                'jumlah_keterlambatan' => $jumlah,
                'total_menit' => $totalMenit,
                'periode_hari' => $periodeHari,
            ],
        ]]);
    }

    private function deteksiSanksiAktif(
        Siswa $siswa,
        TahunPelajaran $tahun,
        Collection $sanksi,
    ): Collection {
        return $sanksi->map(function (SanksiPoinSiswa $item) use ($siswa, $tahun) {
            $terlambat = $item->terlambat();
            $namaSanksi = $item->aturanSanksiPoin?->nama ?? 'Sanksi poin';

            return [
                'jenis' => 'sanksi_belum_selesai',
                'tingkat' => $terlambat ? 'penting' : 'peringatan',
                'sanksi_poin_siswa_id' => $item->id,
                'kunci_unik' => "peringatan:sanksi:{$tahun->id}:{$siswa->id}:{$item->id}",
                'judul' => $terlambat ? 'Pelaksanaan sanksi melewati batas' : 'Sanksi siswa belum selesai',
                'pesan' => "{$namaSanksi} untuk {$siswa->nama_lengkap} berstatus {$item->labelStatus()}."
                    .($item->batas_pelaksanaan ? ' Batas pelaksanaan: '.$item->batas_pelaksanaan->format('d/m/Y').'.' : ''),
                'data_pendukung' => [
                    'sanksi_poin_siswa_id' => $item->id,
                    'status_sanksi' => $item->status,
                    'batas_pelaksanaan' => $item->batas_pelaksanaan?->toDateString(),
                    'terlambat' => $terlambat,
                ],
            ];
        });
    }

    private function sinkronkanPeringatanSiswa(
        Siswa $siswa,
        TahunPelajaran $tahun,
        Collection $deteksi,
        bool $kirimNotifikasi,
    ): array {
        $hasil = [
            'peringatan_baru' => 0,
            'peringatan_diperbarui' => 0,
            'peringatan_diselesaikan' => 0,
            'notifikasi_terkirim' => 0,
        ];
        $kunciTerdeteksi = $deteksi->pluck('kunci_unik')->all();

        DB::transaction(function () use (
            $siswa,
            $tahun,
            $deteksi,
            $kirimNotifikasi,
            $kunciTerdeteksi,
            &$hasil,
        ) {
            foreach ($deteksi as $data) {
                $peringatan = PeringatanDiniSiswa::query()
                    ->lockForUpdate()
                    ->firstOrNew(['kunci_unik' => $data['kunci_unik']]);
                $baruAktif = ! $peringatan->exists || $peringatan->status !== 'aktif';
                $siklus = ! $peringatan->exists
                    ? 1
                    : ($baruAktif ? (int) $peringatan->siklus + 1 : (int) $peringatan->siklus);

                $peringatan->fill($data + [
                    'siswa_id' => $siswa->id,
                    'tahun_pelajaran_id' => $tahun->id,
                ]);
                $peringatan->status = 'aktif';
                $peringatan->siklus = $siklus;
                $peringatan->terdeteksi_pada = $baruAktif ? now() : $peringatan->terdeteksi_pada;
                $peringatan->terakhir_terdeteksi_pada = now();
                $peringatan->diselesaikan_pada = null;
                $peringatan->save();

                if ($baruAktif) {
                    $hasil['peringatan_baru']++;
                    if ($kirimNotifikasi) {
                        $hasil['notifikasi_terkirim'] += $this->kirimNotifikasi($peringatan, $siswa, $tahun);
                    }
                } else {
                    $hasil['peringatan_diperbarui']++;
                }
            }

            $untukDiselesaikan = PeringatanDiniSiswa::query()
                ->where('siswa_id', $siswa->id)
                ->where('tahun_pelajaran_id', $tahun->id)
                ->where('status', 'aktif')
                ->when($kunciTerdeteksi !== [], fn ($query) => $query->whereNotIn('kunci_unik', $kunciTerdeteksi))
                ->get();

            foreach ($untukDiselesaikan as $peringatan) {
                $peringatan->update([
                    'status' => 'selesai',
                    'diselesaikan_pada' => now(),
                ]);
                $hasil['peringatan_diselesaikan']++;
            }
        });

        return $hasil;
    }

    private function kirimNotifikasi(
        PeringatanDiniSiswa $peringatan,
        Siswa $siswa,
        TahunPelajaran $tahun,
    ): int {
        $penerima = $this->penerimaPeringatan($siswa, $tahun);
        if ($penerima->isEmpty()) {
            return 0;
        }

        $tautan = route('peringatan-dini-siswa.index', [
            'tahun_pelajaran_id' => $tahun->id,
            'jenis' => $peringatan->jenis,
            'status' => 'aktif',
        ], false);
        $kunci = "peringatan-dini:{$peringatan->id}:siklus:{$peringatan->siklus}";

        return $this->notifikasi->kirimKeBanyak(
            $penerima,
            $peringatan->tingkat === 'penting' ? 'penting' : 'peringatan',
            $peringatan->judul,
            $peringatan->pesan,
            $tautan,
            $kunci,
            [
                'peringatan_dini_siswa_id' => $peringatan->id,
                'siswa_id' => $siswa->id,
                'jenis' => $peringatan->jenis,
                'siklus' => $peringatan->siklus,
            ],
        )->count();
    }

    private function penerimaPeringatan(Siswa $siswa, TahunPelajaran $tahun): Collection
    {
        $penerima = collect();
        $anggota = $siswa->anggotaKelas
            ->first(fn ($item) => (int) $item->tahun_pelajaran_id === (int) $tahun->id);
        $guruWali = $siswa->penugasanGuruWaliSiswa->first();

        foreach (array_filter([
            $anggota?->kelas?->wali_kelas_id,
            $guruWali?->guru_wali_pegawai_id,
        ]) as $pegawaiId) {
            $penerima = $penerima->merge($this->notifikasi->penggunaUntukPegawai((int) $pegawaiId));
        }

        $penerima = $penerima
            ->merge($this->notifikasi->penggunaDenganPeran(['pimpinan', 'wakil_pimpinan_kesiswaan', 'bk']))
            ->merge($this->notifikasi->penggunaDenganIzin(['poin_siswa.verifikasi_bk', 'poin_siswa.putus_konflik']));

        return $penerima
            ->filter(fn ($pengguna) => $pengguna instanceof Pengguna && $pengguna->aktif)
            ->unique('id')
            ->values();
    }

    private function rekapPelanggaranBerulang(Collection $siswaIds, int $tahunPelajaranId, int $periodeHari): Collection
    {
        return LaporanPembinaanSiswa::query()
            ->select('siswa_id', DB::raw('COUNT(*) AS jumlah'), DB::raw('SUM(total_poin) AS total_poin'))
            ->whereIn('siswa_id', $siswaIds)
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->where('jenis_laporan', 'pelanggaran')
            ->where('status_verifikasi', 'disahkan')
            ->whereDate('tanggal_kejadian', '>=', today()->subDays(max(1, $periodeHari) - 1))
            ->groupBy('siswa_id')
            ->get()
            ->keyBy('siswa_id');
    }

    private function rekapKeterlambatan(Collection $siswaIds, int $tahunPelajaranId, int $periodeHari): Collection
    {
        return AbsensiSiswa::query()
            ->select('siswa_id', DB::raw('COUNT(*) AS jumlah'), DB::raw('SUM(menit_terlambat) AS total_menit'))
            ->whereIn('siswa_id', $siswaIds)
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->where('menit_terlambat', '>', 0)
            ->whereDate('tanggal', '>=', today()->subDays(max(1, $periodeHari) - 1))
            ->groupBy('siswa_id')
            ->get()
            ->keyBy('siswa_id');
    }

    private function selesaikanSemuaPeringatanTahun(int $tahunPelajaranId): int
    {
        return PeringatanDiniSiswa::query()
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->where('status', 'aktif')
            ->update([
                'status' => 'selesai',
                'diselesaikan_pada' => now(),
                'updated_at' => now(),
            ]);
    }
}
