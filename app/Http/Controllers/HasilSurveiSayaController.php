<?php

namespace App\Http\Controllers;

use App\Models\GuruMataPelajaran;
use App\Models\SurveiPembelajaran;
use App\Models\TahunPelajaran;
use App\Services\Survei\RekapSurveiPembelajaranService;
use Illuminate\Http\Request;

class HasilSurveiSayaController extends Controller
{
    public function __construct(private RekapSurveiPembelajaranService $rekapSurvei) {}

    public function index(Request $request)
    {
        $pengguna = $request->user();
        abort_unless($pengguna?->pegawai_id, 403);

        $daftarTahunPelajaran = TahunPelajaran::query()
            ->whereHas('guruMataPelajaran', fn ($query) => $query->where('pegawai_id', $pengguna->pegawai_id))
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
        $daftarPenugasan = GuruMataPelajaran::query()
            ->with([
                'kelas:id,nama,tingkat',
                'mataPelajaran:id,nama',
                'tahunPelajaran:id,nama',
            ])
            ->where('pegawai_id', $pengguna->pegawai_id)
            ->when(
                $tahunPelajaranDipilih,
                fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranDipilih->id),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->orderByDesc('aktif')
            ->get()
            ->sortBy(fn (GuruMataPelajaran $item) => sprintf(
                '%02d|%s|%s|%08d',
                $item->kelas?->tingkat ?? 99,
                $item->mataPelajaran?->nama ?? '',
                $item->kelas?->nama ?? '',
                $item->id,
            ))
            ->values();
        $penugasanDipilih = $daftarPenugasan
            ->firstWhere('id', (int) $request->input('guru_mata_pelajaran_id'))
            ?: $daftarPenugasan->first();

        $hasil = $penugasanDipilih
            ? $this->rekapSurvei->untukPenugasan($penugasanDipilih, $semester)
            : [
                'jumlahSiswa' => 0,
                'jumlahPengisi' => 0,
                'persentasePengisian' => 0,
                'hasilTerbuka' => false,
                'rataRataKeseluruhan' => null,
                'rincianPertanyaan' => collect(),
                'daftarSaran' => collect(),
            ];

        $jumlahSiswa = $hasil['jumlahSiswa'];
        $jumlahPengisi = $hasil['jumlahPengisi'];
        $persentasePengisian = $hasil['persentasePengisian'];
        $hasilTerbuka = $hasil['hasilTerbuka'];
        $rataRataKeseluruhan = $hasil['rataRataKeseluruhan'];
        $rincianPertanyaan = $hasil['rincianPertanyaan'];
        $daftarSaran = $hasil['daftarSaran'];

        return view('hasil-survei-saya.index', compact(
            'daftarTahunPelajaran',
            'tahunPelajaranDipilih',
            'semester',
            'daftarPenugasan',
            'penugasanDipilih',
            'jumlahSiswa',
            'jumlahPengisi',
            'persentasePengisian',
            'hasilTerbuka',
            'rataRataKeseluruhan',
            'rincianPertanyaan',
            'daftarSaran',
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
