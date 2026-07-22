<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\SanksiPoinSiswa;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use Illuminate\Http\Request;

class RekapPoinSiswaController extends Controller
{
    public function index(Request $request)
    {
        $tahunPelajaranId = $this->inputId($request, 'tahun_pelajaran_id')
            ?? TahunPelajaran::where('aktif', true)->latest('tanggal_mulai')->value('id');
        $kelasId = $this->inputId($request, 'kelas_id');
        $kataKunci = trim((string) $request->input('kata_kunci', ''));
        $pengguna = $request->user();

        $query = Siswa::query()
            ->with([
                'anggotaKelas' => fn ($query) => $query
                    ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
                    ->where('status_keanggotaan', 'aktif')
                    ->with('kelas:id,nama'),
                'penugasanGuruWaliSiswa' => fn ($query) => $query->where('aktif', true)->with('guruWali:id,nama_lengkap'),
            ])
            ->withSum(['transaksiPoinSiswa as total_poin' => fn ($query) => $query->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))], 'poin')
            ->where('aktif', true)
            ->when($tahunPelajaranId, fn ($query) => $query->whereHas('anggotaKelas', fn ($query) => $query
                ->where('tahun_pelajaran_id', $tahunPelajaranId)
                ->where('status_keanggotaan', 'aktif')))
            ->when($kelasId, fn ($query) => $query->whereHas('anggotaKelas', fn ($query) => $query
                ->where('kelas_id', $kelasId)
                ->where('status_keanggotaan', 'aktif')))
            ->when($kataKunci !== '', fn ($query) => $query->where(function ($query) use ($kataKunci) {
                $query->where('nama_lengkap', 'ilike', '%' . $kataKunci . '%')
                    ->orWhere('nisn', 'ilike', '%' . $kataKunci . '%')
                    ->orWhere('nis', 'ilike', '%' . $kataKunci . '%');
            }));

        if (! $this->aksesLuas($request)) {
            $kelasWaliIds = $pengguna?->kelasWaliIds() ?? [];
            $siswaWaliIds = $pengguna?->siswaWaliIds() ?? [];
            if ($kelasWaliIds === [] && $siswaWaliIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(function ($query) use ($kelasWaliIds, $siswaWaliIds) {
                    $query->when($kelasWaliIds !== [], fn ($query) => $query->whereHas('anggotaKelas', fn ($query) => $query->whereIn('kelas_id', $kelasWaliIds)))
                        ->when($siswaWaliIds !== [], fn ($query) => $query->orWhereIn('id', $siswaWaliIds));
                });
            }
        }

        $daftarSiswa = $query->orderBy('nama_lengkap')->paginate(15)->withQueryString();
        $sanksiMenunggu = SanksiPoinSiswa::query()
            ->with(['siswa:id,nama_lengkap,nisn', 'aturanSanksiPoin:id,batas_poin,nama'])
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->when(! $this->aksesLuas($request), function ($query) use ($pengguna) {
                $kelasWaliIds = $pengguna?->kelasWaliIds() ?? [];
                $siswaWaliIds = $pengguna?->siswaWaliIds() ?? [];

                if ($kelasWaliIds === [] && $siswaWaliIds === []) {
                    $query->whereRaw('1 = 0');

                    return;
                }

                $query->whereHas('siswa', function ($query) use ($kelasWaliIds, $siswaWaliIds) {
                    $query->where(function ($query) use ($kelasWaliIds, $siswaWaliIds) {
                        $query->when($kelasWaliIds !== [], fn ($query) => $query->whereHas('anggotaKelas', fn ($query) => $query->whereIn('kelas_id', $kelasWaliIds)))
                            ->when($siswaWaliIds !== [], fn ($query) => $query->orWhereIn('id', $siswaWaliIds));
                    });
                });
            })
            ->whereIn('status', ['menunggu', 'diproses'])
            ->latest('terpicu_pada')
            ->limit(20)
            ->get();

        $daftarTahunPelajaran = TahunPelajaran::orderByDesc('aktif')->orderByDesc('tanggal_mulai')->get();
        $daftarKelas = Kelas::when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->orderBy('tingkat')->orderBy('nama')->get();

        return view('rekap-poin-siswa.index', compact(
            'daftarSiswa', 'sanksiMenunggu', 'daftarTahunPelajaran', 'daftarKelas',
            'tahunPelajaranId', 'kelasId', 'kataKunci',
        ));
    }

    private function aksesLuas(Request $request): bool
    {
        return $request->user()?->administrator()
            || $request->user()?->memilikiPeran(['pimpinan', 'wakil_pimpinan_kesiswaan', 'bk']);
    }

    private function inputId(Request $request, string $field): ?int
    {
        $value = $request->input($field);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }
}
