<?php

namespace App\Http\Controllers;

use App\Models\KegiatanUjianCbt;
use App\Services\Nilai\LegerStsService;
use App\Services\Nilai\RaporStsService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LegerStsController extends Controller
{
    public function index(Request $request, RaporStsService $raporSts, LegerStsService $legerSts)
    {
        abort_unless(RaporStsService::dapatMengakses($request->user()), 403);
        $data = $request->validate([
            'mode' => ['nullable', Rule::in(['kelas', 'tingkat'])],
            'kegiatan_id' => ['nullable', 'integer'],
            'kelas_id' => ['nullable', 'integer'],
            'tingkat' => ['nullable', 'integer', 'min:1', 'max:12'],
        ]);
        $mode = $data['mode'] ?? 'kelas';

        $daftarKegiatan = KegiatanUjianCbt::whereHas('jenisUjianCbt', fn ($query) => $query->where('kode', 'STS'))
            ->whereIn('tahun_pelajaran_id', $raporSts->kelasDalamCakupan($request->user())->select('tahun_pelajaran_id'))
            ->with('tahunPelajaran')
            ->orderByDesc('tanggal_mulai')
            ->get();
        $kegiatan = isset($data['kegiatan_id'])
            ? $daftarKegiatan->firstWhere('id', $data['kegiatan_id'])
            : $daftarKegiatan->first();
        abort_if(isset($data['kegiatan_id']) && ! $kegiatan, 404);

        $daftarKelas = $kegiatan
            ? $raporSts->kelasDalamCakupan($request->user())
                ->where('tahun_pelajaran_id', $kegiatan->tahun_pelajaran_id)
                ->orderBy('tingkat')
                ->orderBy('nama')
                ->get()
            : collect();
        $kelas = isset($data['kelas_id'])
            ? $daftarKelas->firstWhere('id', $data['kelas_id'])
            : $daftarKelas->first();
        abort_if(isset($data['kelas_id']) && ! $kelas, 404);
        $daftarTingkat = $daftarKelas->pluck('tingkat')->filter()->unique()->sort()->values();
        $tingkat = isset($data['tingkat'])
            ? $daftarTingkat->first(fn ($item) => (int) $item === (int) $data['tingkat'])
            : ($kelas?->tingkat ?? $daftarTingkat->first());
        abort_if(isset($data['tingkat']) && $tingkat === null, 404);

        $leger = $mode === 'kelas' && $kegiatan && $kelas ? $legerSts->bangun($kegiatan, $kelas) : null;
        $legerTingkat = $mode === 'tingkat' && $kegiatan && $tingkat !== null
            ? $legerSts->bangunTingkat($kegiatan, $daftarKelas, (int) $tingkat)
            : null;

        return view('leger-sts.index', compact(
            'daftarKegiatan', 'daftarKelas', 'daftarTingkat', 'kegiatan', 'kelas', 'tingkat',
            'mode', 'leger', 'legerTingkat'
        ));
    }

    public function cetak(Request $request, RaporStsService $raporSts, LegerStsService $legerSts)
    {
        abort_unless(RaporStsService::dapatMengakses($request->user()), 403);
        $data = $request->validate([
            'mode' => ['required', Rule::in(['kelas', 'tingkat'])],
            'kegiatan_id' => ['required', 'integer'],
            'kelas_id' => [Rule::requiredIf($request->input('mode') === 'kelas'), 'nullable', 'integer'],
            'tingkat' => [Rule::requiredIf($request->input('mode') === 'tingkat'), 'nullable', 'integer', 'min:1', 'max:12'],
        ]);
        $kegiatan = KegiatanUjianCbt::whereKey($data['kegiatan_id'])
            ->whereHas('jenisUjianCbt', fn ($query) => $query->where('kode', 'STS'))
            ->whereIn('tahun_pelajaran_id', $raporSts->kelasDalamCakupan($request->user())->select('tahun_pelajaran_id'))
            ->with('tahunPelajaran')
            ->firstOrFail();
        $daftarKelas = $raporSts->kelasDalamCakupan($request->user())
            ->where('tahun_pelajaran_id', $kegiatan->tahun_pelajaran_id)
            ->orderBy('tingkat')
            ->orderBy('nama')
            ->get();

        if ($data['mode'] === 'kelas') {
            $kelas = $daftarKelas->firstWhere('id', (int) $data['kelas_id']);
            abort_unless($kelas, 404);
            $tingkat = $kelas->tingkat;
            $leger = $legerSts->bangun($kegiatan, $kelas);
        } else {
            $tingkat = (int) $data['tingkat'];
            abort_unless($daftarKelas->contains(fn ($kelas) => $kelas->tingkat === $tingkat), 404);
            $kelas = null;
            $leger = $legerSts->bangunTingkat($kegiatan, $daftarKelas, $tingkat);
        }

        return response()->view('leger-sts.cetak', [
            'mode' => $data['mode'],
            'kegiatan' => $kegiatan,
            'kelas' => $kelas,
            'tingkat' => $tingkat,
            'leger' => $leger,
        ])->header('Cache-Control', 'private, no-store');
    }

    public function penghargaan(Request $request, RaporStsService $raporSts, LegerStsService $legerSts)
    {
        abort_unless(RaporStsService::dapatMengakses($request->user()), 403);
        $data = $request->validate([
            'cakupan' => ['nullable', Rule::in(['kelas', 'tingkat'])],
            'kegiatan_id' => ['nullable', 'integer'],
            'kelas_id' => ['nullable', 'integer'],
            'tingkat' => ['nullable', 'integer', 'min:1', 'max:12'],
            'kategori' => ['nullable', Rule::in(['keseluruhan', 'mapel'])],
            'mapel_id' => ['nullable', 'integer'],
            'batas' => ['nullable', Rule::in([1, 3, 10])],
        ]);
        $cakupan = $data['cakupan'] ?? 'kelas';
        $kategori = $data['kategori'] ?? 'keseluruhan';
        $batas = (int) ($data['batas'] ?? 10);
        $daftarKegiatan = KegiatanUjianCbt::whereHas('jenisUjianCbt', fn ($query) => $query->where('kode', 'STS'))
            ->whereIn('tahun_pelajaran_id', $raporSts->kelasDalamCakupan($request->user())->select('tahun_pelajaran_id'))
            ->with('tahunPelajaran')
            ->orderByDesc('tanggal_mulai')
            ->get();
        $kegiatan = isset($data['kegiatan_id'])
            ? $daftarKegiatan->firstWhere('id', $data['kegiatan_id'])
            : $daftarKegiatan->first();
        abort_if(isset($data['kegiatan_id']) && ! $kegiatan, 404);
        $daftarKelas = $kegiatan
            ? $raporSts->kelasDalamCakupan($request->user())
                ->where('tahun_pelajaran_id', $kegiatan->tahun_pelajaran_id)
                ->orderBy('tingkat')->orderBy('nama')->get()
            : collect();
        $kelas = isset($data['kelas_id'])
            ? $daftarKelas->firstWhere('id', $data['kelas_id'])
            : $daftarKelas->first();
        abort_if(isset($data['kelas_id']) && ! $kelas, 404);
        $daftarTingkat = $daftarKelas->pluck('tingkat')->filter()->unique()->sort()->values();
        $tingkat = isset($data['tingkat'])
            ? $daftarTingkat->first(fn ($item) => (int) $item === (int) $data['tingkat'])
            : ($kelas?->tingkat ?? $daftarTingkat->first());
        abort_if(isset($data['tingkat']) && $tingkat === null, 404);

        $leger = match (true) {
            ! $kegiatan => null,
            $cakupan === 'tingkat' && $tingkat !== null => $legerSts->bangunTingkat($kegiatan, $daftarKelas, (int) $tingkat),
            $cakupan === 'kelas' && $kelas => $legerSts->bangun($kegiatan, $kelas),
            default => null,
        };
        $mapelId = isset($data['mapel_id'])
            ? (int) $data['mapel_id']
            : ($leger ? $leger['mapel']->first()?->id : null);
        abort_if(isset($data['mapel_id']) && (! $leger || ! $leger['mapel']->contains('id', $mapelId)), 404);
        $penghargaan = $leger
            ? $legerSts->kandidatPenghargaan($leger, $kategori, $mapelId, $batas)
            : null;

        return view('leger-sts.penghargaan', compact(
            'daftarKegiatan', 'daftarKelas', 'daftarTingkat', 'kegiatan', 'kelas', 'tingkat',
            'cakupan', 'kategori', 'batas', 'mapelId', 'leger', 'penghargaan'
        ));
    }
}
