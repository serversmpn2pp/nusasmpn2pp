<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKelas;
use App\Models\Kelas;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\AkunSiswaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class AkunSiswaController extends Controller
{
    public function __construct(private readonly AkunSiswaService $akunSiswaService) {}

    public function index(Request $request)
    {
        $pengguna = $request->user();
        $dapatMengaksesSemuaKelas = $pengguna?->administrator()
            || $pengguna?->memilikiIzin('akun_siswa.kelola');
        $kelasWaliIds = $pengguna?->kelasWaliIds() ?? [];
        $tahunPelajaranAktif = TahunPelajaran::query()
            ->where('aktif', true)
            ->when(! $dapatMengaksesSemuaKelas, fn ($query) => $query
                ->whereHas('kelas', fn ($query) => $query->whereIn('id', $kelasWaliIds)))
            ->orderByDesc('tanggal_mulai')
            ->orderByDesc('id')
            ->first();
        $daftarKelas = $this->queryKelasYangDapatDiakses($pengguna)
            ->withCount([
                'anggotaKelas as jumlah_siswa_aktif' => fn ($query) => $query
                    ->where('status_keanggotaan', 'aktif')
                    ->whereHas('siswa', fn ($query) => $query->where('aktif', true)),
            ])
            ->when(
                $tahunPelajaranAktif,
                fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranAktif->id),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->where('aktif', true)
            ->orderBy('tingkat')
            ->orderBy('nama')
            ->get();
        $kelasId = $request->integer('kelas_id');

        if ($kelasId && ! $daftarKelas->contains('id', $kelasId)) {
            $kelasId = 0;
        }

        $kelasDipilih = $kelasId ? $daftarKelas->firstWhere('id', $kelasId) : null;
        $statusAkun = $request->input('status_akun', 'semua');
        $kataKunci = trim((string) $request->input('kata_kunci'));

        if (! in_array($statusAkun, ['semua', 'sudah', 'belum', 'tanpa_nisn'], true)) {
            $statusAkun = 'semua';
        }

        $kelasYangDapatDiakses = $daftarKelas->pluck('id')->map(fn ($id) => (int) $id);
        $anggotaKelas = AnggotaKelas::query()
            ->with(['siswa.pengguna.daftarPeran', 'kelas.tahunPelajaran'])
            ->whereIn('kelas_id', $kelasYangDapatDiakses)
            ->when($kelasId, fn ($query) => $query->where('kelas_id', $kelasId))
            ->where('status_keanggotaan', 'aktif')
            ->whereHas('siswa', function ($query) use ($statusAkun, $kataKunci) {
                $query->where('aktif', true)
                    ->when($kataKunci, function ($query) use ($kataKunci) {
                        $query->where(function ($query) use ($kataKunci) {
                            $query->whereLike('nama_lengkap', '%'.$kataKunci.'%')
                                ->orWhereLike('nis', '%'.$kataKunci.'%')
                                ->orWhereLike('nisn', '%'.$kataKunci.'%');
                        });
                    })
                    ->when($statusAkun === 'sudah', fn ($query) => $query->whereHas('pengguna'))
                    ->when($statusAkun === 'belum', fn ($query) => $query
                        ->whereDoesntHave('pengguna')
                        ->whereNotNull('nisn')
                        ->where('nisn', '<>', ''))
                    ->when($statusAkun === 'tanpa_nisn', fn ($query) => $query
                        ->where(function ($query) {
                            $query->whereNull('nisn')->orWhere('nisn', '');
                        }));
            })
            ->orderBy(Kelas::query()
                ->select('tingkat')
                ->whereColumn('kelas.id', 'anggota_kelas.kelas_id')
                ->limit(1))
            ->orderBy(Kelas::query()
                ->select('nama')
                ->whereColumn('kelas.id', 'anggota_kelas.kelas_id')
                ->limit(1))
            ->orderByRaw('nomor_absen IS NULL')
            ->orderBy('nomor_absen')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        $ringkasan = $this->ringkasanKelas($kelasYangDapatDiakses, $kelasId);

        return view('akun-siswa.index', compact(
            'tahunPelajaranAktif',
            'daftarKelas',
            'kelasDipilih',
            'kelasId',
            'statusAkun',
            'kataKunci',
            'anggotaKelas',
            'ringkasan',
        ));
    }

    public function store(Siswa $siswa)
    {
        $pengguna = $this->akunSiswaService->buat($siswa);

        return back()->with(
            'berhasil',
            'Akun '.$siswa->nama_lengkap.' berhasil dibuat dengan username '.$pengguna->username.'.',
        );
    }

    public function storeMassal(Kelas $kelas)
    {
        $ringkasan = [
            'dibuat' => 0,
            'dilewati' => 0,
            'catatan' => [],
        ];

        $anggota = AnggotaKelas::query()
            ->with('siswa.pengguna')
            ->where('kelas_id', $kelas->id)
            ->where('status_keanggotaan', 'aktif')
            ->whereHas('siswa', fn ($query) => $query->where('aktif', true))
            ->orderByRaw('nomor_absen IS NULL')
            ->orderBy('nomor_absen')
            ->get();

        foreach ($anggota as $item) {
            $siswa = $item->siswa;

            if (! $siswa || $siswa->pengguna) {
                $ringkasan['dilewati']++;

                continue;
            }

            try {
                $this->akunSiswaService->buat($siswa);
                $ringkasan['dibuat']++;
            } catch (ValidationException $exception) {
                $ringkasan['dilewati']++;
                $ringkasan['catatan'][] = $siswa->nama_lengkap.': '.collect($exception->errors())->flatten()->first();
            }
        }

        return back()
            ->with('berhasil', 'Pembuatan akun siswa kelas '.$kelas->nama.' selesai.')
            ->with('ringkasan_akun_siswa', $ringkasan);
    }

    public function resetPassword(Pengguna $pengguna)
    {
        $this->akunSiswaService->resetKataSandi($pengguna);

        return back()->with('berhasil', 'Password awal akun '.$pengguna->nama.' berhasil dibuat ulang.');
    }

    public function ubahStatus(Pengguna $pengguna)
    {
        abort_unless($pengguna->siswa_id && ! $pengguna->akun_sistem, 404);

        $pengguna->update([
            'aktif' => ! $pengguna->aktif,
        ]);

        return back()->with('berhasil', 'Status akun '.$pengguna->nama.' berhasil diperbarui.');
    }

    public function cetak(Request $request, Kelas $kelas)
    {
        $this->pastikanDapatMengaksesKelas($request->user(), $kelas);

        $kelas->load(['tahunPelajaran', 'waliKelas']);
        $anggotaKelas = AnggotaKelas::query()
            ->with('siswa.pengguna')
            ->where('kelas_id', $kelas->id)
            ->where('status_keanggotaan', 'aktif')
            ->whereHas('siswa', fn ($query) => $query->where('aktif', true))
            ->orderByRaw('nomor_absen IS NULL')
            ->orderBy('nomor_absen')
            ->orderBy('id')
            ->get();

        return view('akun-siswa.cetak', compact('kelas', 'anggotaKelas'));
    }

    private function queryKelasYangDapatDiakses(?Pengguna $pengguna): Builder
    {
        $query = Kelas::query();

        if ($pengguna?->administrator() || $pengguna?->memilikiIzin('akun_siswa.kelola')) {
            return $query;
        }

        return $query->whereIn('id', $pengguna?->kelasWaliIds() ?? []);
    }

    private function pastikanDapatMengaksesKelas(?Pengguna $pengguna, Kelas $kelas): void
    {
        if ($pengguna?->administrator() || $pengguna?->memilikiIzin('akun_siswa.kelola')) {
            return;
        }

        abort_unless(
            $pengguna?->memilikiPeran('wali_kelas')
            && in_array((int) $kelas->id, $pengguna->kelasWaliIds(), true),
            403,
        );
    }

    private function ringkasanKelas(Collection $kelasYangDapatDiakses, int $kelasId): array
    {
        $kelasUntukRingkasan = $kelasId ? collect([$kelasId]) : $kelasYangDapatDiakses;
        $query = Siswa::query()
            ->where('aktif', true)
            ->whereHas('anggotaKelas', fn ($query) => $query
                ->whereIn('kelas_id', $kelasUntukRingkasan)
                ->where('status_keanggotaan', 'aktif'));

        return [
            'jumlah_siswa' => (clone $query)->count(),
            'sudah_akun' => (clone $query)->whereHas('pengguna')->count(),
            'belum_akun' => (clone $query)
                ->whereDoesntHave('pengguna')
                ->whereNotNull('nisn')
                ->where('nisn', '<>', '')
                ->count(),
            'tanpa_nisn' => (clone $query)
                ->where(function ($query) {
                    $query->whereNull('nisn')->orWhere('nisn', '');
                })
                ->count(),
        ];
    }
}
