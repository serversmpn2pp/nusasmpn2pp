<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\KomponenNilai;
use App\Models\SkemaBobotNilai;
use Illuminate\Http\Request;

class RekapNilaiRaporController extends Controller
{
    public function index(Request $request)
    {
        $guruMataPelajaranId = $request->input('guru_mata_pelajaran_id');
        $semester = $request->input('semester', 'ganjil');

        if (! in_array($semester, ['ganjil', 'genap'], true)) {
            $semester = 'ganjil';
        }

        $daftarGuruMataPelajaran = $this->ambilDaftarGuruMataPelajaran();
        $guruMataPelajaranDipilih = null;
        $skemaBobotNilai = null;
        $komponenNilai = collect();
        $anggotaKelas = collect();
        $rekapNilai = collect();
        $jumlahKomponen = $this->templateJumlahKomponen();
        $labelNilaiAkhir = 'SAS/SAJ';

        if ($guruMataPelajaranId) {
            $guruMataPelajaranDipilih = $this->ambilGuruMataPelajaranDipilih($guruMataPelajaranId);
            $kelas = $guruMataPelajaranDipilih->kelas;
            $labelNilaiAkhir = $this->labelNilaiAkhir($kelas?->tingkat);
            $skemaBobotNilai = $this->ambilSkemaBobotNilai(
                $guruMataPelajaranDipilih->tahun_pelajaran_id,
                $semester,
                $kelas?->tingkat,
            );

            $anggotaKelas = $this->ambilAnggotaKelas($guruMataPelajaranDipilih->kelas_id);
            $siswaIds = $anggotaKelas->pluck('siswa_id');
            $komponenNilai = $this->ambilKomponenNilai($guruMataPelajaranDipilih->id, $semester, $siswaIds);
            $jumlahKomponen = $this->hitungJumlahKomponen($komponenNilai);
            $rekapNilai = $this->hitungRekapNilai($anggotaKelas, $komponenNilai, $jumlahKomponen, $skemaBobotNilai);
        }

        $jumlahSiswa = $rekapNilai->count();
        $jumlahLengkap = $rekapNilai->where('lengkap', true)->count();
        $jumlahBelumLengkap = max(0, $jumlahSiswa - $jumlahLengkap);
        $rataRataAkhir = $rekapNilai->whereNotNull('nilai_akhir')->avg('nilai_akhir');

        return view('rekap-nilai-rapor.index', compact(
            'daftarGuruMataPelajaran',
            'guruMataPelajaranId',
            'guruMataPelajaranDipilih',
            'semester',
            'skemaBobotNilai',
            'komponenNilai',
            'jumlahKomponen',
            'labelNilaiAkhir',
            'rekapNilai',
            'jumlahSiswa',
            'jumlahLengkap',
            'jumlahBelumLengkap',
            'rataRataAkhir',
        ));
    }

    private function ambilDaftarGuruMataPelajaran()
    {
        return GuruMataPelajaran::query()
            ->with(['tahunPelajaran', 'kelas', 'mataPelajaran', 'pegawai'])
            ->where('aktif', true)
            ->orderBy('tahun_pelajaran_id')
            ->orderBy('kelas_id')
            ->orderBy('mata_pelajaran_id')
            ->get();
    }

    private function ambilGuruMataPelajaranDipilih(int|string $guruMataPelajaranId): GuruMataPelajaran
    {
        return GuruMataPelajaran::query()
            ->with(['tahunPelajaran', 'kelas', 'mataPelajaran', 'pegawai'])
            ->where('aktif', true)
            ->whereKey($guruMataPelajaranId)
            ->firstOrFail();
    }

    private function ambilSkemaBobotNilai(int $tahunPelajaranId, string $semester, ?int $tingkat): ?SkemaBobotNilai
    {
        return SkemaBobotNilai::query()
            ->where('tahun_pelajaran_id', $tahunPelajaranId)
            ->where('semester', $semester)
            ->where('aktif', true)
            ->where(function ($query) use ($tingkat) {
                $query->whereNull('tingkat');

                if ($tingkat) {
                    $query->orWhere('tingkat', $tingkat);
                }
            })
            ->orderByRaw('tingkat IS NULL')
            ->first();
    }

    private function ambilAnggotaKelas(int $kelasId)
    {
        return AnggotaKelas::query()
            ->with('siswa')
            ->where('kelas_id', $kelasId)
            ->where('status_keanggotaan', 'aktif')
            ->orderByRaw('nomor_absen IS NULL')
            ->orderBy('nomor_absen')
            ->orderBy('id')
            ->get();
    }

    private function ambilKomponenNilai(int $guruMataPelajaranId, string $semester, $siswaIds)
    {
        return KomponenNilai::query()
            ->with(['nilaiSiswa' => function ($query) use ($siswaIds) {
                $query->whereIn('siswa_id', $siswaIds);
            }])
            ->where('guru_mata_pelajaran_id', $guruMataPelajaranId)
            ->where('semester', $semester)
            ->where('aktif', true)
            ->orderBy('jenis_komponen')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->get();
    }

    private function hitungJumlahKomponen($komponenNilai): array
    {
        $jumlah = $this->templateJumlahKomponen();

        foreach ($komponenNilai as $komponen) {
            $jumlah[$komponen->jenis_komponen] = ($jumlah[$komponen->jenis_komponen] ?? 0) + 1;
        }

        return $jumlah;
    }

    private function hitungRekapNilai($anggotaKelas, $komponenNilai, array $jumlahKomponen, ?SkemaBobotNilai $skemaBobotNilai)
    {
        $nilaiPerSiswa = [];

        foreach ($komponenNilai as $komponen) {
            foreach ($komponen->nilaiSiswa as $nilaiSiswa) {
                if ($nilaiSiswa->nilai === null) {
                    continue;
                }

                $nilaiPerSiswa[$nilaiSiswa->siswa_id][$komponen->jenis_komponen][] = (float) $nilaiSiswa->nilai;
            }
        }

        $bobot = $this->ambilBobot($skemaBobotNilai);

        return $anggotaKelas->map(function (AnggotaKelas $anggota) use ($nilaiPerSiswa, $jumlahKomponen, $skemaBobotNilai, $bobot) {
            $kategori = [];

            foreach (['formatif', 'sumatif', 'sts', 'sas_saj'] as $jenis) {
                $nilai = $nilaiPerSiswa[$anggota->siswa_id][$jenis] ?? [];
                $kategori[$jenis] = [
                    'rata' => count($nilai) ? round(array_sum($nilai) / count($nilai), 2) : null,
                    'terisi' => count($nilai),
                    'target' => $jumlahKomponen[$jenis] ?? 0,
                    'bobot' => $bobot[$jenis],
                ];
            }

            $lengkap = $skemaBobotNilai !== null && $this->kategoriLengkap($kategori);
            $nilaiAkhir = $lengkap ? $this->hitungNilaiAkhir($kategori) : null;

            return [
                'anggota_kelas' => $anggota,
                'kategori' => $kategori,
                'nilai_akhir' => $nilaiAkhir,
                'lengkap' => $lengkap,
                'status' => $this->statusRekap($skemaBobotNilai, $kategori, $lengkap),
            ];
        });
    }

    private function kategoriLengkap(array $kategori): bool
    {
        foreach ($kategori as $item) {
            if ($item['bobot'] <= 0) {
                continue;
            }

            if ($item['target'] <= 0 || $item['terisi'] < $item['target']) {
                return false;
            }
        }

        return true;
    }

    private function hitungNilaiAkhir(array $kategori): float
    {
        $nilai = 0;

        foreach ($kategori as $item) {
            $nilai += ((float) $item['rata']) * ($item['bobot'] / 100);
        }

        return round($nilai, 2);
    }

    private function statusRekap(?SkemaBobotNilai $skemaBobotNilai, array $kategori, bool $lengkap): string
    {
        if (! $skemaBobotNilai) {
            return 'Skema belum ada';
        }

        if ($lengkap) {
            return 'Lengkap';
        }

        foreach ($kategori as $item) {
            if ($item['bobot'] > 0 && $item['target'] <= 0) {
                return 'Komponen belum ada';
            }
        }

        return 'Nilai belum lengkap';
    }

    private function ambilBobot(?SkemaBobotNilai $skemaBobotNilai): array
    {
        return [
            'formatif' => $skemaBobotNilai?->bobot_formatif ?? 0,
            'sumatif' => $skemaBobotNilai?->bobot_sumatif ?? 0,
            'sts' => $skemaBobotNilai?->bobot_sts ?? 0,
            'sas_saj' => $skemaBobotNilai?->bobot_sas_saj ?? 0,
        ];
    }

    private function templateJumlahKomponen(): array
    {
        return [
            'formatif' => 0,
            'sumatif' => 0,
            'sts' => 0,
            'sas_saj' => 0,
        ];
    }

    private function labelNilaiAkhir(?int $tingkat): string
    {
        return (int) $tingkat === 9 ? 'SAJ' : 'SAS';
    }
}
