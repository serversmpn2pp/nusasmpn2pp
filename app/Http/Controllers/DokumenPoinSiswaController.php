<?php

namespace App\Http\Controllers;

use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Pembinaan\AksesRekapPoinSiswaService;
use App\Services\Pembinaan\DokumenPoinSiswaService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DokumenPoinSiswaController extends Controller
{
    public function __construct(
        private AksesRekapPoinSiswaService $akses,
        private DokumenPoinSiswaService $dokumen,
    ) {}

    public function laporan(Request $request, Siswa $siswa)
    {
        $data = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'periode' => ['nullable', Rule::in(['semester', 'rentang'])],
            'semester' => ['nullable', Rule::in(['ganjil', 'genap'])],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_selesai' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ]);
        $tahunPelajaran = $this->tahunPelajaran($data['tahun_pelajaran_id'] ?? null);
        abort_unless($this->akses->bolehLihat($request->user(), $siswa, $tahunPelajaran->id), 403);

        $periode = $data['periode'] ?? 'semester';
        $semester = $data['semester'] ?? $this->semesterSaatIni();
        [$tanggalMulai, $tanggalSelesai] = $this->rentangPeriode($data, $periode, $semester, $tahunPelajaran);
        $labelPeriode = $periode === 'semester'
            ? 'Semester '.ucfirst($semester).' ('.$this->formatTanggal($tanggalMulai).' s.d. '.$this->formatTanggal($tanggalSelesai).')'
            : $this->formatTanggal($tanggalMulai).' s.d. '.$this->formatTanggal($tanggalSelesai);

        return view('dokumen-poin-siswa.laporan', $this->dokumen->laporan(
            $siswa,
            $tahunPelajaran,
            $tanggalMulai,
            $tanggalSelesai,
        ) + [
            'daftarTahunPelajaran' => TahunPelajaran::orderByDesc('aktif')->orderByDesc('tanggal_mulai')->get(),
            'periode' => $periode,
            'semester' => $semester,
            'tanggalMulai' => $tanggalMulai,
            'tanggalSelesai' => $tanggalSelesai,
            'labelPeriode' => $labelPeriode,
            'tanggalCetak' => now()->locale('id')->translatedFormat('d F Y, H:i'),
        ]);
    }

    public function surat(Request $request, Siswa $siswa)
    {
        $data = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
        ]);
        $tahunPelajaran = $this->tahunPelajaran($data['tahun_pelajaran_id'] ?? null);
        abort_unless($this->akses->bolehLihat($request->user(), $siswa, $tahunPelajaran->id), 403);
        $dataSurat = $this->dokumen->surat($siswa, $tahunPelajaran);

        return view('dokumen-poin-siswa.surat', $dataSurat + [
            'daftarTahunPelajaran' => TahunPelajaran::orderByDesc('aktif')->orderByDesc('tanggal_mulai')->get(),
            'nilaiAwal' => [
                'jenis_surat' => 'pemberitahuan',
                'tanggal_surat' => today()->toDateString(),
                'nama_penerima' => $dataSurat['namaOrangTua'],
                'alamat_penerima' => $siswa->alamat,
                'tempat_pertemuan' => 'SMP Negeri 2 Padang Panjang',
                'keperluan' => 'Membahas perkembangan pembinaan dan poin siswa.',
            ],
        ]);
    }

    public function cetakSurat(Request $request, Siswa $siswa)
    {
        $data = $request->validate([
            'tahun_pelajaran_id' => ['required', 'integer', 'exists:tahun_pelajaran,id'],
            'jenis_surat' => ['required', Rule::in(['pemberitahuan', 'pemanggilan'])],
            'nomor_surat' => ['nullable', 'string', 'max:100'],
            'tanggal_surat' => ['required', 'date'],
            'nama_penerima' => ['required', 'string', 'max:160'],
            'alamat_penerima' => ['nullable', 'string', 'max:500'],
            'tanggal_pertemuan' => ['nullable', 'required_if:jenis_surat,pemanggilan', 'date'],
            'jam_pertemuan' => ['nullable', 'required_if:jenis_surat,pemanggilan', 'date_format:H:i'],
            'tempat_pertemuan' => ['nullable', 'required_if:jenis_surat,pemanggilan', 'string', 'max:200'],
            'keperluan' => ['nullable', 'required_if:jenis_surat,pemanggilan', 'string', 'max:1000'],
            'catatan_tambahan' => ['nullable', 'string', 'max:1000'],
        ]);
        $tahunPelajaran = $this->tahunPelajaran((int) $data['tahun_pelajaran_id']);
        abort_unless($this->akses->bolehLihat($request->user(), $siswa, $tahunPelajaran->id), 403);

        return view('dokumen-poin-siswa.cetak-surat', $this->dokumen->surat($siswa, $tahunPelajaran) + [
            'dataSurat' => $this->rapikanDataSurat($data),
            'tanggalSurat' => Carbon::parse($data['tanggal_surat']),
            'tanggalPertemuan' => filled($data['tanggal_pertemuan'] ?? null)
                ? Carbon::parse($data['tanggal_pertemuan'])
                : null,
        ]);
    }

    private function tahunPelajaran(?int $tahunPelajaranId): TahunPelajaran
    {
        $tahunPelajaran = $tahunPelajaranId
            ? TahunPelajaran::find($tahunPelajaranId)
            : TahunPelajaran::where('aktif', true)->latest('tanggal_mulai')->first();
        $tahunPelajaran ??= TahunPelajaran::latest('tanggal_mulai')->first();

        abort_unless($tahunPelajaran, 404, 'Tahun pelajaran belum tersedia.');

        return $tahunPelajaran;
    }

    private function rentangPeriode(
        array $data,
        string $periode,
        string $semester,
        TahunPelajaran $tahunPelajaran,
    ): array {
        $awalTahun = $tahunPelajaran->tanggal_mulai->copy()->startOfDay();
        $akhirTahun = ($tahunPelajaran->tanggal_selesai ?? $awalTahun->copy()->addYear()->subDay())->copy()->endOfDay();

        if ($periode === 'rentang') {
            $mulai = Carbon::parse($data['tanggal_mulai'] ?? $awalTahun)->startOfDay();
            $tanggalSelesaiBawaan = now()->lessThan($akhirTahun) ? now() : $akhirTahun;
            $selesai = Carbon::parse($data['tanggal_selesai'] ?? $tanggalSelesaiBawaan)->endOfDay();
        } elseif ($semester === 'ganjil') {
            $mulai = $awalTahun->copy();
            $selesai = Carbon::create($awalTahun->year, 12, 31)->endOfDay();
        } else {
            $mulai = Carbon::create($awalTahun->year + 1, 1, 1)->startOfDay();
            $selesai = $akhirTahun->copy();
        }

        $mulai = $mulai->lessThan($awalTahun) ? $awalTahun : $mulai;
        $selesai = $selesai->greaterThan($akhirTahun) ? $akhirTahun : $selesai;

        if ($mulai->greaterThan($selesai)) {
            throw ValidationException::withMessages([
                'tanggal_mulai' => 'Rentang tanggal tidak berada dalam tahun pelajaran yang dipilih.',
            ]);
        }

        return [$mulai, $selesai];
    }

    private function semesterSaatIni(): string
    {
        return now()->month >= 7 ? 'ganjil' : 'genap';
    }

    private function formatTanggal(Carbon $tanggal): string
    {
        return $tanggal->locale('id')->translatedFormat('d M Y');
    }

    private function rapikanDataSurat(array $data): array
    {
        foreach ([
            'nomor_surat',
            'nama_penerima',
            'alamat_penerima',
            'tempat_pertemuan',
            'keperluan',
            'catatan_tambahan',
        ] as $field) {
            $data[$field] = filled($data[$field] ?? null) ? trim((string) $data[$field]) : null;
        }

        return $data;
    }
}
