<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKelas;
use App\Models\Pengguna;
use App\Models\PublikasiNilaiSiswa;
use App\Models\Siswa;
use App\Models\SkemaBobotNilai;
use App\Models\SurveiPembelajaran;
use App\Models\TahunPelajaran;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class NilaiSayaController extends Controller
{
    public function index(Request $request)
    {
        $siswa = $this->siswaDariPengguna($request->user());
        $daftarTahunPelajaran = TahunPelajaran::query()
            ->whereHas('anggotaKelas', fn ($query) => $query->where('siswa_id', $siswa?->id ?: 0))
            ->orderByDesc('aktif')
            ->orderByDesc('tanggal_mulai')
            ->get();
        $tahunPelajaranAktif = $daftarTahunPelajaran->firstWhere('aktif', true)
            ?: $daftarTahunPelajaran->first();
        $tahunPelajaranId = (int) ($request->input('tahun_pelajaran_id') ?: $tahunPelajaranAktif?->id);
        $semester = $request->input('semester', $this->semesterSaatIni());

        if (! in_array($semester, ['ganjil', 'genap'], true)) {
            $semester = $this->semesterSaatIni();
        }

        $tahunPelajaranDipilih = $daftarTahunPelajaran->firstWhere('id', $tahunPelajaranId)
            ?: $tahunPelajaranAktif;
        $tahunPelajaranId = (int) ($tahunPelajaranDipilih?->id ?: 0);
        $anggotaKelas = $this->ambilAnggotaKelas($siswa, $tahunPelajaranId);
        $skemaBobotNilai = $anggotaKelas
            ? $this->ambilSkemaBobotNilai(
                $tahunPelajaranId,
                $semester,
                $anggotaKelas->kelas?->tingkat,
            )
            : null;
        $publikasiNilai = $this->ambilPublikasiNilai(
            $siswa,
            $tahunPelajaranId,
            $semester,
            $anggotaKelas?->kelas_id,
        );
        $daftarNilai = $publikasiNilai
            ->map(fn (PublikasiNilaiSiswa $publikasi) => $this->susunNilaiMataPelajaran(
                $publikasi,
                $siswa,
                $skemaBobotNilai,
            ));
        $ringkasan = [
            'mata_pelajaran' => $daftarNilai->count(),
            'nilai_terbuka' => $daftarNilai->where('survei_diisi', true)->count(),
            'survei_belum_diisi' => $daftarNilai->where('survei_diisi', false)->count(),
        ];

        return view('nilai-saya.index', compact(
            'siswa',
            'daftarTahunPelajaran',
            'tahunPelajaranDipilih',
            'tahunPelajaranId',
            'semester',
            'anggotaKelas',
            'skemaBobotNilai',
            'daftarNilai',
            'ringkasan',
        ));
    }

    private function siswaDariPengguna(?Pengguna $pengguna): ?Siswa
    {
        abort_unless($pengguna?->akunSiswa() || $pengguna?->memilikiPeran('siswa'), 403);

        return $pengguna->siswa()->first();
    }

    private function semesterSaatIni(): string
    {
        return now()->month >= 7 ? 'ganjil' : 'genap';
    }

    private function ambilAnggotaKelas(?Siswa $siswa, int $tahunPelajaranId): ?AnggotaKelas
    {
        return AnggotaKelas::query()
            ->with('kelas:id,nama,tingkat')
            ->where('siswa_id', $siswa?->id ?: 0)
            ->where('tahun_pelajaran_id', $tahunPelajaranId ?: 0)
            ->orderByRaw("case when status_keanggotaan = 'aktif' then 0 else 1 end")
            ->latest('id')
            ->first();
    }

    private function ambilSkemaBobotNilai(
        int $tahunPelajaranId,
        string $semester,
        ?int $tingkat,
    ): ?SkemaBobotNilai {
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

    private function ambilPublikasiNilai(
        ?Siswa $siswa,
        int $tahunPelajaranId,
        string $semester,
        ?int $kelasId,
    ): Collection {
        if (! $siswa || ! $tahunPelajaranId || ! $kelasId) {
            return collect();
        }

        return PublikasiNilaiSiswa::query()
            ->with([
                'guruMataPelajaran.tahunPelajaran:id,nama',
                'guruMataPelajaran.kelas:id,nama,tingkat',
                'guruMataPelajaran.mataPelajaran.pengaturanTingkat',
                'guruMataPelajaran.pegawai:id,nama_lengkap',
                'guruMataPelajaran.surveiPembelajaran' => fn ($query) => $query
                    ->where('siswa_id', $siswa->id)
                    ->where('semester', $semester),
                'guruMataPelajaran.komponenNilai' => fn ($query) => $query
                    ->where('semester', $semester)
                    ->where('aktif', true)
                    ->with(['nilaiSiswa' => fn ($query) => $query->where('siswa_id', $siswa->id)])
                    ->orderBy('jenis_komponen')
                    ->orderBy('urutan')
                    ->orderBy('nama'),
            ])
            ->where('semester', $semester)
            ->where('dipublikasikan', true)
            ->whereHas('guruMataPelajaran', function ($query) use ($tahunPelajaranId, $kelasId) {
                $query->where('tahun_pelajaran_id', $tahunPelajaranId)
                    ->where('kelas_id', $kelasId);
            })
            ->get()
            ->sortBy(fn (PublikasiNilaiSiswa $publikasi) => sprintf(
                '%05d|%s|%08d',
                $publikasi->guruMataPelajaran?->mataPelajaran?->urutan ?? 9999,
                $publikasi->guruMataPelajaran?->mataPelajaran?->nama ?? '',
                $publikasi->id,
            ))
            ->values();
    }

    private function susunNilaiMataPelajaran(
        PublikasiNilaiSiswa $publikasi,
        Siswa $siswa,
        ?SkemaBobotNilai $skemaBobotNilai,
    ): array {
        $guruMataPelajaran = $publikasi->guruMataPelajaran;
        $mataPelajaran = $guruMataPelajaran?->mataPelajaran;
        $surveiPembelajaran = $guruMataPelajaran?->surveiPembelajaran
            ?->firstWhere('semester', $publikasi->semester);
        $surveiDiisi = $surveiPembelajaran instanceof SurveiPembelajaran;
        $menggunakanPredikat = $mataPelajaran?->menggunakanPredikat() ?? false;
        $komponen = $guruMataPelajaran?->komponenNilai
            ?->map(function ($item) use ($siswa) {
                $nilai = $item->nilaiSiswa->firstWhere('siswa_id', $siswa->id);

                return [
                    'id' => $item->id,
                    'nama' => $item->nama,
                    'jenis' => $item->jenis_komponen,
                    'label_jenis' => $item->labelJenis(),
                    'tanggal' => $item->tanggal_penilaian,
                    'nilai' => $nilai?->nilai !== null ? (float) $nilai->nilai : null,
                    'predikat' => $nilai?->predikat,
                    'catatan' => $nilai?->catatan,
                ];
            })
            ->values() ?? collect();
        $kategori = $this->hitungKategori($komponen, $skemaBobotNilai);
        $lengkap = ! $menggunakanPredikat
            && $skemaBobotNilai !== null
            && $this->kategoriLengkap($kategori);
        $nilaiAkhir = $lengkap ? $this->hitungNilaiAkhir($kategori) : null;
        $tingkat = $guruMataPelajaran?->kelas?->tingkat;
        $pengaturanMataPelajaran = $mataPelajaran?->pengaturanUntuk(
            (int) $guruMataPelajaran?->tahun_pelajaran_id,
            (int) $tingkat,
        );
        $kkm = $mataPelajaran?->menggunakanPredikat()
            ? null
            : ($pengaturanMataPelajaran?->kkm ?? $mataPelajaran?->kkm);

        return [
            'publikasi' => $publikasi,
            'guru_mata_pelajaran' => $guruMataPelajaran,
            'mata_pelajaran' => $mataPelajaran,
            'komponen' => $komponen,
            'kategori' => $kategori,
            'survei' => $surveiPembelajaran,
            'survei_diisi' => $surveiDiisi,
            'menggunakan_predikat' => $menggunakanPredikat,
            'nilai_akhir' => $surveiDiisi ? $nilaiAkhir : null,
            'lengkap' => $lengkap,
            'kkm' => $kkm,
            'tuntas' => $surveiDiisi && $nilaiAkhir !== null && $kkm !== null
                ? $nilaiAkhir >= $kkm
                : null,
        ];
    }

    private function hitungKategori(Collection $komponen, ?SkemaBobotNilai $skema): array
    {
        $bobot = [
            'formatif' => $skema?->bobot_formatif ?? 0,
            'sumatif' => $skema?->bobot_sumatif ?? 0,
            'sts' => $skema?->bobot_sts ?? 0,
            'sas_saj' => $skema?->bobot_sas_saj ?? 0,
        ];

        return collect(['formatif', 'sumatif', 'sts', 'sas_saj'])
            ->mapWithKeys(function (string $jenis) use ($komponen, $bobot) {
                $daftar = $komponen->where('jenis', $jenis);
                $nilai = $daftar->pluck('nilai')->filter(fn ($nilai) => $nilai !== null);

                return [$jenis => [
                    'rata' => $nilai->isNotEmpty() ? round((float) $nilai->avg(), 2) : null,
                    'terisi' => $nilai->count(),
                    'target' => $daftar->count(),
                    'bobot' => $bobot[$jenis],
                ]];
            })
            ->all();
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
        return round(collect($kategori)->sum(
            fn (array $item) => ((float) $item['rata']) * ($item['bobot'] / 100),
        ), 2);
    }
}
