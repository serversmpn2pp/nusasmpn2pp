<?php

namespace App\Http\Controllers;

use App\Models\LaporanPembinaanSiswa;
use App\Models\Pengguna;
use App\Models\Siswa;
use App\Models\TahunPelajaran;
use App\Services\Pembinaan\PresentasiProgressKasusService;
use Illuminate\Http\Request;

class ProgressKasusSiswaController extends Controller
{
    public function __construct(private PresentasiProgressKasusService $presentasiProgress) {}

    public function index(Request $request)
    {
        $siswa = $this->siswaDariPengguna($request->user());
        $tahunPelajaranAktif = TahunPelajaran::query()
            ->where('aktif', true)
            ->orderByDesc('tanggal_mulai')
            ->first();

        $query = LaporanPembinaanSiswa::query()
            ->with([
                'tahunPelajaran:id,nama',
                'kelas:id,nama',
                'kategoriPembinaanSiswa:id,nama',
            ])
            ->where('siswa_id', $siswa?->id ?: 0);

        $ringkasan = [
            'semua' => (clone $query)->count(),
            'diproses' => (clone $query)
                ->where(function ($query) {
                    $query->whereIn('status_verifikasi', [
                        'diajukan',
                        'pemeriksaan_bk',
                        'perlu_klarifikasi',
                        'dikembalikan_bk',
                        'menunggu_pengesahan_wakil',
                        'menunggu_persetujuan',
                        'disetujui_sebagian',
                        'perlu_musyawarah',
                    ])->orWhere(function ($query) {
                        $query->where('status_verifikasi', 'tidak_perlu')
                            ->whereNotIn('status', ['selesai', 'dibatalkan']);
                    });
                })
                ->count(),
            'pembinaan' => (clone $query)
                ->where('status_verifikasi', 'ditetapkan_pembinaan')
                ->count(),
            'poin_resmi' => (int) (clone $query)
                ->where('status_verifikasi', 'disahkan')
                ->sum('total_poin'),
        ];

        $laporan = $query
            ->latest('tanggal_kejadian')
            ->latest('id')
            ->paginate(10);
        $presentasiStatus = $laporan->getCollection()
            ->mapWithKeys(fn (LaporanPembinaanSiswa $item) => [
                $item->id => $this->presentasiProgress->status($item),
            ]);

        return view('progress-kasus-siswa.index', compact(
            'siswa',
            'tahunPelajaranAktif',
            'ringkasan',
            'laporan',
            'presentasiStatus',
        ));
    }

    public function show(Request $request, LaporanPembinaanSiswa $laporanPembinaanSiswa)
    {
        $siswa = $this->siswaDariPengguna($request->user());
        abort_unless($siswa && (int) $laporanPembinaanSiswa->siswa_id === (int) $siswa->id, 404);

        $laporanPembinaanSiswa->load([
            'tahunPelajaran:id,nama',
            'kelas:id,nama',
            'kategoriPembinaanSiswa:id,nama',
            'butirPelanggaranLaporan' => fn ($query) => $query->orderBy('id'),
            'riwayatProsesPembinaanSiswa' => fn ($query) => $query
                ->oldest('terjadi_pada')
                ->oldest('id'),
            'tindakLanjutPembinaanSiswa' => fn ($query) => $query
                ->oldest('tanggal_tindak_lanjut')
                ->oldest('waktu_tindak_lanjut')
                ->oldest('id'),
        ]);

        return view('progress-kasus-siswa.show', [
            'siswa' => $siswa,
            'laporan' => $laporanPembinaanSiswa,
            'presentasiStatus' => $this->presentasiProgress->status($laporanPembinaanSiswa),
            'linimasa' => $this->presentasiProgress->linimasaPublik($laporanPembinaanSiswa),
        ]);
    }

    private function siswaDariPengguna(?Pengguna $pengguna): ?Siswa
    {
        abort_unless($pengguna?->akunSiswa() || $pengguna?->memilikiPeran('siswa'), 403);

        return $pengguna->siswa()->first();
    }
}
