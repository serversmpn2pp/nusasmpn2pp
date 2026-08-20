<?php

namespace App\Http\Controllers;

use App\Models\KonfirmasiBerhalanganIbadah;
use App\Models\PeriodeBerhalanganIbadah;
use App\Models\TahunPelajaran;
use App\Services\Ibadah\AksesBerhalanganIbadah;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RekapBerhalanganIbadahController extends Controller
{
    public function index(Request $request, AksesBerhalanganIbadah $akses)
    {
        return view('rekap-berhalangan-ibadah.index', $this->siapkanData($request, $akses));
    }

    public function cetak(Request $request, AksesBerhalanganIbadah $akses)
    {
        return view('rekap-berhalangan-ibadah.cetak', $this->siapkanData($request, $akses, true));
    }

    private function siapkanData(Request $request, AksesBerhalanganIbadah $akses, bool $cetak = false): array
    {
        $data = $request->validate([
            'bulan' => ['nullable', 'date_format:Y-m'],
            'kelas_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::in([
                'semua',
                PeriodeBerhalanganIbadah::STATUS_AKTIF,
                PeriodeBerhalanganIbadah::STATUS_PERLU_KONFIRMASI,
                PeriodeBerhalanganIbadah::STATUS_SELESAI,
            ])],
            'cari' => ['nullable', 'string', 'max:100'],
        ]);
        $tahunPelajaran = TahunPelajaran::query()
            ->where('aktif', true)
            ->orderByDesc('tanggal_mulai')
            ->firstOrFail();
        abort_unless(
            $akses->dapatMengonfirmasi($request->user(), $tahunPelajaran),
            403,
            'Rekap privat hanya dapat dibuka oleh petugas yang berwenang.'
        );

        $bulan = Carbon::createFromFormat('Y-m', $data['bulan'] ?? now()->format('Y-m'))->startOfMonth();
        $bulanMinimum = $tahunPelajaran->tanggal_mulai->copy()->startOfMonth();
        $bulanMaksimum = now()->startOfMonth()->min($tahunPelajaran->tanggal_selesai->copy()->startOfMonth());

        if ($bulan->lt($bulanMinimum) || $bulan->gt($bulanMaksimum)) {
            throw ValidationException::withMessages([
                'bulan' => 'Pilih bulan dalam tahun pelajaran aktif dan tidak melewati bulan berjalan.',
            ]);
        }

        $tanggalMulai = $bulan->copy()->startOfMonth()->max($tahunPelajaran->tanggal_mulai->copy()->startOfDay());
        $tanggalSelesai = $bulan->copy()->endOfMonth()
            ->min($tahunPelajaran->tanggal_selesai->copy()->endOfDay())
            ->min(now()->endOfDay());
        $daftarKelas = $akses->kelasTercakup($request->user(), $tahunPelajaran);
        $kelasId = filled($data['kelas_id'] ?? null) ? (int) $data['kelas_id'] : null;

        if ($kelasId && ! $daftarKelas->contains('id', $kelasId)) {
            abort(403, 'Kelas berada di luar cakupan pendampingan Anda.');
        }

        $status = $data['status'] ?? 'semua';
        $cari = trim((string) ($data['cari'] ?? ''));
        $dasar = PeriodeBerhalanganIbadah::query()
            ->where('tahun_pelajaran_id', $tahunPelajaran->id)
            ->whereDate('tanggal_mulai', '<=', $tanggalSelesai->toDateString())
            ->where(function ($query) use ($tanggalMulai) {
                $query->whereNull('tanggal_selesai')
                    ->orWhereDate('tanggal_selesai', '>=', $tanggalMulai->toDateString());
            })
            ->when($kelasId, fn ($query) => $query->where('kelas_id', $kelasId))
            ->when($cari !== '', function ($query) use ($cari) {
                $kataKunci = '%'.mb_strtolower($cari).'%';
                $query->whereHas('siswa', fn ($query) => $query
                    ->whereRaw('LOWER(nama_lengkap) LIKE ?', [$kataKunci])
                    ->orWhereRaw('LOWER(nisn) LIKE ?', [$kataKunci]));
            });
        $akses->batasiPeriodeSesuaiCakupan($dasar, $request->user(), $tahunPelajaran);

        $ringkasan = [
            'periode' => (clone $dasar)->count(),
            'siswi' => (clone $dasar)->distinct()->count('siswa_id'),
            'aktif' => (clone $dasar)->where('status', PeriodeBerhalanganIbadah::STATUS_AKTIF)->count(),
            'perlu_konfirmasi' => (clone $dasar)->where('status', PeriodeBerhalanganIbadah::STATUS_PERLU_KONFIRMASI)->count(),
            'selesai' => (clone $dasar)->where('status', PeriodeBerhalanganIbadah::STATUS_SELESAI)->count(),
        ];

        $query = (clone $dasar)
            ->with([
                'siswa:id,nama_lengkap,nisn,foto',
                'kelas:id,nama',
                'konfirmasiTerakhir.dikonfirmasiOlehPengguna:id,nama',
            ])
            ->withCount([
                'presensiHarian as presensi_bulan_count' => fn ($query) => $query
                    ->whereDate('tanggal', '>=', $tanggalMulai->toDateString())
                    ->whereDate('tanggal', '<=', $tanggalSelesai->toDateString()),
                'riwayatKonfirmasi as konfirmasi_bulan_count' => fn ($query) => $query
                    ->whereBetween('dikonfirmasi_pada', [$tanggalMulai, $tanggalSelesai]),
            ])
            ->when($status !== 'semua', fn ($query) => $query->where('status', $status))
            ->orderByDesc('tanggal_mulai')
            ->orderByDesc('id');
        $daftarPeriode = $cetak
            ? $query->get()
            : $query->paginate(15)->withQueryString();

        return [
            'tahunPelajaran' => $tahunPelajaran,
            'bulan' => $bulan->format('Y-m'),
            'bulanLabel' => $bulan->locale('id')->translatedFormat('F Y'),
            'bulanMinimum' => $bulanMinimum->format('Y-m'),
            'bulanMaksimum' => $bulanMaksimum->format('Y-m'),
            'tanggalMulai' => $tanggalMulai,
            'tanggalSelesai' => $tanggalSelesai,
            'daftarKelas' => $daftarKelas,
            'kelasId' => $kelasId,
            'kelasDipilih' => $daftarKelas->firstWhere('id', $kelasId),
            'status' => $status,
            'cari' => $cari,
            'daftarStatus' => [
                'semua' => 'Semua status',
                PeriodeBerhalanganIbadah::STATUS_AKTIF => 'Sedang dipantau',
                PeriodeBerhalanganIbadah::STATUS_PERLU_KONFIRMASI => 'Perlu konfirmasi',
                PeriodeBerhalanganIbadah::STATUS_SELESAI => 'Selesai',
            ],
            'ringkasan' => $ringkasan,
            'daftarPeriode' => $daftarPeriode,
            'tanggalCetak' => now()->locale('id')->translatedFormat('d F Y H:i'),
            'hasilKonfirmasi' => KonfirmasiBerhalanganIbadah::DAFTAR_HASIL,
        ];
    }
}
