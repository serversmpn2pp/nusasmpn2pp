<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\SurveiPembelajaran;
use App\Models\TahunPelajaran;
use App\Services\Survei\RekapSurveiPembelajaranService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class MonitoringSurveiController extends Controller
{
    public function __construct(private RekapSurveiPembelajaranService $rekapSurvei) {}

    public function index(Request $request)
    {
        $daftarTahunPelajaran = TahunPelajaran::query()
            ->whereHas('guruMataPelajaran')
            ->orderByDesc('aktif')
            ->orderByDesc('tanggal_mulai')
            ->get();
        $tahunPelajaranDipilih = $daftarTahunPelajaran
            ->firstWhere('id', (int) $request->input('tahun_pelajaran_id'))
            ?: $daftarTahunPelajaran->firstWhere('aktif', true)
            ?: $daftarTahunPelajaran->first();
        $semester = in_array($request->input('semester'), ['ganjil', 'genap'], true)
            ? $request->input('semester')
            : $this->semesterSaatIni();
        $status = in_array($request->input('status'), ['belum', 'berjalan', 'lengkap'], true)
            ? $request->input('status')
            : 'semua';
        $kataKunci = trim((string) $request->input('kata_kunci', ''));
        $queryPenugasan = GuruMataPelajaran::query()
            ->with([
                'kelas:id,nama,tingkat',
                'mataPelajaran:id,nama',
                'pegawai:id,nama_lengkap,nip',
                'tahunPelajaran:id,nama',
            ])
            ->when(
                $tahunPelajaranDipilih,
                fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranDipilih->id),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->when($kataKunci !== '', function ($query) use ($kataKunci) {
                $query->where(function ($query) use ($kataKunci) {
                    $query->whereHas('pegawai', fn ($query) => $query
                        ->where('nama_lengkap', 'ilike', '%'.$kataKunci.'%')
                        ->orWhere('nip', 'ilike', '%'.$kataKunci.'%'))
                        ->orWhereHas('mataPelajaran', fn ($query) => $query
                            ->where('nama', 'ilike', '%'.$kataKunci.'%'))
                        ->orWhereHas('kelas', fn ($query) => $query
                            ->where('nama', 'ilike', '%'.$kataKunci.'%'));
                });
            });
        $semuaPenugasan = $queryPenugasan
            ->get()
            ->sortBy(fn (GuruMataPelajaran $item) => sprintf(
                '%s|%02d|%s|%s|%08d',
                $item->pegawai?->nama_lengkap ?? '',
                $item->kelas?->tingkat ?? 99,
                $item->mataPelajaran?->nama ?? '',
                $item->kelas?->nama ?? '',
                $item->id,
            ))
            ->values();
        $penugasanIds = $semuaPenugasan->pluck('id');
        $jumlahSiswaPerKelas = AnggotaKelas::query()
            ->when(
                $tahunPelajaranDipilih,
                fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranDipilih->id),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->where('status_keanggotaan', 'aktif')
            ->selectRaw('kelas_id, count(distinct siswa_id) as jumlah')
            ->groupBy('kelas_id')
            ->pluck('jumlah', 'kelas_id');
        $surveiPerPenugasan = SurveiPembelajaran::query()
            ->whereIn('guru_mata_pelajaran_id', $penugasanIds)
            ->where('semester', $semester)
            ->get(['id', 'guru_mata_pelajaran_id', 'jawaban', 'snapshot_pertanyaan', 'saran', 'diisi_pada'])
            ->groupBy('guru_mata_pelajaran_id');
        $barisMonitoring = $semuaPenugasan
            ->map(function (GuruMataPelajaran $penugasan) use ($jumlahSiswaPerKelas, $surveiPerPenugasan) {
                $ringkasan = $this->rekapSurvei->ringkas(
                    $surveiPerPenugasan->get($penugasan->id, collect()),
                    (int) $jumlahSiswaPerKelas->get($penugasan->kelas_id, 0),
                );

                return ['penugasan' => $penugasan] + $ringkasan;
            })
            ->filter(fn (array $item) => match ($status) {
                'belum' => $item['jumlahPengisi'] === 0,
                'berjalan' => $item['jumlahPengisi'] > 0 && $item['persentasePengisian'] < 100,
                'lengkap' => $item['jumlahSiswa'] > 0 && $item['persentasePengisian'] >= 100,
                default => true,
            })
            ->values();
        $ringkasanMonitoring = [
            'penugasan' => $barisMonitoring->count(),
            'target_respons' => $barisMonitoring->sum('jumlahSiswa'),
            'respons_masuk' => $barisMonitoring->sum('jumlahPengisi'),
            'hasil_terbuka' => $barisMonitoring->where('hasilTerbuka', true)->count(),
        ];
        $halaman = max(1, (int) $request->input('page', 1));
        $perHalaman = 15;
        $monitoring = new LengthAwarePaginator(
            $barisMonitoring->forPage($halaman, $perHalaman)->values(),
            $barisMonitoring->count(),
            $perHalaman,
            $halaman,
            ['path' => $request->url(), 'query' => $request->except('page')],
        );
        $penugasanDipilih = $semuaPenugasan
            ->firstWhere('id', (int) $request->input('guru_mata_pelajaran_id'));
        $hasilDipilih = $penugasanDipilih
            ? $this->rekapSurvei->susun(
                $surveiPerPenugasan->get($penugasanDipilih->id, collect()),
                (int) $jumlahSiswaPerKelas->get($penugasanDipilih->kelas_id, 0),
            )
            : null;

        return view('monitoring-survei.index', compact(
            'daftarTahunPelajaran',
            'tahunPelajaranDipilih',
            'semester',
            'status',
            'kataKunci',
            'monitoring',
            'ringkasanMonitoring',
            'penugasanDipilih',
            'hasilDipilih',
        ) + [
            'minimalResponden' => RekapSurveiPembelajaranService::MINIMAL_RESPONDEN,
            'daftarPilihan' => SurveiPembelajaran::PILIHAN,
        ]);
    }

    private function semesterSaatIni(): string
    {
        return now()->month >= 7 ? 'ganjil' : 'genap';
    }
}
