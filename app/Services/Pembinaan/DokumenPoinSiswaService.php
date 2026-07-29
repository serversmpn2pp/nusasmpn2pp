<?php

namespace App\Services\Pembinaan;

use App\Models\LaporanPembinaanSiswa;
use App\Models\Pegawai;
use App\Models\PendampinganSiswa;
use App\Models\PenguranganPoinSiswa;
use App\Models\SanksiPoinSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Models\TransaksiPoinSiswa;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class DokumenPoinSiswaService
{
    public function laporan(
        Siswa $siswa,
        TahunPelajaran $tahunPelajaran,
        CarbonInterface $tanggalMulai,
        CarbonInterface $tanggalSelesai,
    ): array {
        $transaksiTahun = TransaksiPoinSiswa::query()
            ->where('siswa_id', $siswa->id)
            ->where('tahun_pelajaran_id', $tahunPelajaran->id);
        $transaksiPeriode = (clone $transaksiTahun)
            ->whereBetween('tercatat_pada', [$tanggalMulai, $tanggalSelesai])
            ->get();

        $pelanggaran = LaporanPembinaanSiswa::query()
            ->with([
                'kategoriPembinaanSiswa:id,nama',
                'kelas:id,nama',
                'butirPelanggaranLaporan:id,laporan_pembinaan_siswa_id,kode_pelanggaran,nama_pelanggaran,poin',
            ])
            ->where('siswa_id', $siswa->id)
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->where('jenis_laporan', 'pelanggaran')
            ->where('status_verifikasi', 'disahkan')
            ->whereBetween('tanggal_kejadian', [$tanggalMulai->toDateString(), $tanggalSelesai->toDateString()])
            ->orderBy('tanggal_kejadian')
            ->orderBy('id')
            ->get();

        $penguranganPoin = PenguranganPoinSiswa::query()
            ->with('disetujuiOlehPegawai:id,nama_lengkap')
            ->where('siswa_id', $siswa->id)
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->where('status', 'disetujui')
            ->whereBetween('tanggal_kegiatan', [$tanggalMulai->toDateString(), $tanggalSelesai->toDateString()])
            ->orderBy('tanggal_kegiatan')
            ->orderBy('id')
            ->get();

        $daftarSanksi = SanksiPoinSiswa::query()
            ->with(['aturanSanksiPoin:id,nama,batas_poin', 'petugasPegawai:id,nama_lengkap'])
            ->where('siswa_id', $siswa->id)
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->whereBetween('terpicu_pada', [$tanggalMulai, $tanggalSelesai])
            ->orderBy('terpicu_pada')
            ->orderBy('id')
            ->get();

        $daftarPendampingan = PendampinganSiswa::query()
            ->with(['petugasPegawai:id,nama_lengkap', 'peringatanDiniSiswa:id,jenis'])
            ->where('siswa_id', $siswa->id)
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->whereBetween('tanggal_tindak_lanjut', [$tanggalMulai->toDateString(), $tanggalSelesai->toDateString()])
            ->orderBy('tanggal_tindak_lanjut')
            ->orderBy('id')
            ->get();

        return $this->dataDasar($siswa, $tahunPelajaran) + [
            'pelanggaran' => $pelanggaran,
            'penguranganPoin' => $penguranganPoin,
            'daftarSanksi' => $daftarSanksi,
            'daftarPendampingan' => $daftarPendampingan,
            'ringkasan' => [
                'jumlah_pelanggaran' => $pelanggaran->count(),
                'poin_masuk_periode' => (int) $transaksiPeriode->where('poin', '>', 0)->sum('poin'),
                'poin_dikurangi_periode' => abs((int) $transaksiPeriode->where('poin', '<', 0)->sum('poin')),
                'perubahan_poin_periode' => (int) $transaksiPeriode->sum('poin'),
                'total_poin_terkini' => max(0, (int) (clone $transaksiTahun)->sum('poin')),
            ],
        ];
    }

    public function surat(Siswa $siswa, TahunPelajaran $tahunPelajaran): array
    {
        $pelanggaranTerakhir = LaporanPembinaanSiswa::query()
            ->with('butirPelanggaranLaporan:id,laporan_pembinaan_siswa_id,kode_pelanggaran,nama_pelanggaran,poin')
            ->where('siswa_id', $siswa->id)
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->where('jenis_laporan', 'pelanggaran')
            ->where('status_verifikasi', 'disahkan')
            ->latest('tanggal_kejadian')
            ->latest('id')
            ->limit(5)
            ->get();

        return $this->dataDasar($siswa, $tahunPelajaran) + [
            'pelanggaranTerakhir' => $pelanggaranTerakhir,
            'totalPoinTerkini' => max(0, (int) TransaksiPoinSiswa::query()
                ->where('siswa_id', $siswa->id)
                ->where('tahun_pelajaran_id', $tahunPelajaran->id)
                ->sum('poin')),
            'kepalaSekolah' => $this->pegawaiDenganPeran('pimpinan'),
            'wakilKesiswaan' => $this->pegawaiDenganPeran('wakil_pimpinan_kesiswaan'),
        ];
    }

    private function dataDasar(Siswa $siswa, TahunPelajaran $tahunPelajaran): array
    {
        $anggotaKelas = $siswa->anggotaKelas()
            ->with('kelas.waliKelas:id,nama_lengkap,nip')
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->where('status_keanggotaan', 'aktif')
            ->first();
        $penugasanGuruWali = $siswa->penugasanGuruWaliSiswa()
            ->with('guruWali:id,nama_lengkap,nip')
            ->where('aktif', true)
            ->latest('tanggal_mulai')
            ->first();

        return [
            'siswa' => $siswa,
            'tahunPelajaran' => $tahunPelajaran,
            'anggotaKelas' => $anggotaKelas,
            'waliKelas' => $anggotaKelas?->kelas?->waliKelas,
            'guruWali' => $penugasanGuruWali?->guruWali,
            'guruBk' => $this->pegawaiDenganPeran('bk'),
            'namaOrangTua' => $this->namaOrangTua($siswa),
        ];
    }

    private function namaOrangTua(Siswa $siswa): string
    {
        if (filled($siswa->nama_wali)) {
            return $siswa->nama_wali;
        }

        $nama = collect([$siswa->nama_ayah, $siswa->nama_ibu])
            ->filter()
            ->implode(' / ');

        return $nama !== '' ? $nama : 'Orang Tua/Wali Siswa';
    }

    private function pegawaiDenganPeran(string $kodePeran): ?Pegawai
    {
        return Pegawai::query()
            ->where('aktif', true)
            ->whereHas('pengguna', function (Builder $query) use ($kodePeran) {
                $query->where('aktif', true)
                    ->whereHas('daftarPeran', function (Builder $query) use ($kodePeran) {
                        $query->where('peran.kode', $kodePeran)
                            ->where('peran.aktif', true);
                    });
            })
            ->orderBy('nama_lengkap')
            ->first();
    }
}
