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
use Illuminate\Validation\ValidationException;

class AkunSiswaController extends Controller
{
    public function __construct(private readonly AkunSiswaService $akunSiswaService) {}

    public function index(Request $request)
    {
        $pengguna = $request->user();
        $tahunPelajaranId = $request->integer('tahun_pelajaran_id')
            ?: TahunPelajaran::where('aktif', true)->value('id');
        $daftarTahunPelajaran = TahunPelajaran::query()
            ->orderByDesc('aktif')
            ->orderByDesc('tanggal_mulai')
            ->get();
        $daftarKelas = $this->queryKelasYangDapatDiakses($pengguna)
            ->with('tahunPelajaran')
            ->withCount([
                'anggotaKelas as jumlah_siswa_aktif' => fn ($query) => $query
                    ->where('status_keanggotaan', 'aktif')
                    ->whereHas('siswa', fn ($query) => $query->where('aktif', true)),
            ])
            ->when($tahunPelajaranId, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaranId))
            ->orderBy('tingkat')
            ->orderBy('nama')
            ->get();
        $kelasId = $request->integer('kelas_id');

        if (! $kelasId || ! $daftarKelas->contains('id', $kelasId)) {
            $kelasId = (int) ($daftarKelas->first()?->id ?? 0);
        }

        $kelasDipilih = $daftarKelas->firstWhere('id', $kelasId);
        $statusAkun = $request->input('status_akun', 'semua');
        $kataKunci = trim((string) $request->input('kata_kunci'));

        if (! in_array($statusAkun, ['semua', 'sudah', 'belum', 'tanpa_nisn'], true)) {
            $statusAkun = 'semua';
        }

        $anggotaKelas = AnggotaKelas::query()
            ->with(['siswa.pengguna.daftarPeran', 'kelas.tahunPelajaran'])
            ->where('kelas_id', $kelasId ?: 0)
            ->where('status_keanggotaan', 'aktif')
            ->whereHas('siswa', function ($query) use ($statusAkun, $kataKunci) {
                $query->where('aktif', true)
                    ->when($kataKunci, function ($query) use ($kataKunci) {
                        $query->where(function ($query) use ($kataKunci) {
                            $query->where('nama_lengkap', 'ilike', '%'.$kataKunci.'%')
                                ->orWhere('nis', 'ilike', '%'.$kataKunci.'%')
                                ->orWhere('nisn', 'ilike', '%'.$kataKunci.'%');
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
            ->orderByRaw('nomor_absen IS NULL')
            ->orderBy('nomor_absen')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        $ringkasan = $this->ringkasanKelas($kelasId);

        return view('akun-siswa.index', compact(
            'daftarTahunPelajaran',
            'daftarKelas',
            'tahunPelajaranId',
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

    private function ringkasanKelas(int $kelasId): array
    {
        $query = Siswa::query()
            ->where('aktif', true)
            ->whereHas('anggotaKelas', fn ($query) => $query
                ->where('kelas_id', $kelasId ?: 0)
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
