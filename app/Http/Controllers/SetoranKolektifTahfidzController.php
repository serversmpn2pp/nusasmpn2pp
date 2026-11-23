<?php

namespace App\Http\Controllers;

use App\Models\SetoranTahfidz;
use App\Models\Siswa;
use App\Models\SurahTahfidz;
use App\Services\Tahfidz\AksesTahfidz;
use App\Services\Tahfidz\SetoranTahfidzService;
use Illuminate\Http\Request;

class SetoranKolektifTahfidzController extends Controller
{
    public const SISWA_PER_HALAMAN = 15;

    public function __construct(
        private readonly AksesTahfidz $akses,
        private readonly SetoranTahfidzService $layanan,
    ) {}

    public function index(Request $request, int $kelompok, int $pertemuan)
    {
        [$kelompok, $pertemuan] = $this->konteks($request, $kelompok, $pertemuan);
        if ($pertemuan->selesai_pada) {
            return redirect()->route('tahfidz.pertemuan.show', [$kelompok, $pertemuan])
                ->with('berhasil', 'Pertemuan sudah selesai. Riwayat setoran tetap tersedia.');
        }
        $filter = $request->validate($this->aturanFilter());
        $peserta = $this->layanan->peserta($kelompok)->with('siswa:id,nama_lengkap')->get()
            ->sortBy(fn ($anggota) => $anggota->siswa->nama_lengkap, SORT_NATURAL | SORT_FLAG_CASE)->values();
        $siswaDipilih = isset($filter['siswa']) ? (int) $filter['siswa'] : null;
        abort_if($siswaDipilih && ! $peserta->contains('id', $siswaDipilih), 404);
        $surah = SurahTahfidz::whereBetween('nomor', [78, 114])->orderBy('nomor')->get();
        $surahDipilih = $surah->whereIn('nomor', $filter['surah'] ?? [])->values();
        $anggota = $surahDipilih->isEmpty() ? null : $this->layanan->peserta($kelompok)
            ->when($siswaDipilih, fn ($q) => $q->whereKey($siswaDipilih))
            ->orderBy(Siswa::select('nama_lengkap')->whereColumn('siswa.id', 'anggota_tahfidz.siswa_id'))
            ->orderBy('anggota_tahfidz.id')
            ->select('anggota_tahfidz.*')->with('siswa:id,nama_lengkap')
            ->paginate(self::SISWA_PER_HALAMAN)->withQueryString();
        $terakhir = collect();
        if ($anggota?->isNotEmpty()) {
            $peringkat = SetoranTahfidz::query()->join('pertemuan_tahfidz as pertemuan', 'pertemuan.id', '=', 'setoran_tahfidz.pertemuan_tahfidz_id')
                ->where('pertemuan.kelompok_tahfidz_id', $kelompok->id)->whereDate('pertemuan.tanggal', '<=', $pertemuan->tanggal->toDateString())
                ->whereIn('setoran_tahfidz.anggota_tahfidz_id', $anggota->pluck('id'))
                ->whereIn('setoran_tahfidz.nomor_surah', $surahDipilih->pluck('nomor'))
                ->select('setoran_tahfidz.anggota_tahfidz_id', 'nomor_surah', 'nilai', 'pertemuan.tanggal as tanggal_setoran')
                ->selectRaw('ROW_NUMBER() OVER (PARTITION BY anggota_tahfidz_id, nomor_surah ORDER BY pertemuan.tanggal DESC, setoran_tahfidz.id DESC) AS urutan');
            $terakhir = SetoranTahfidz::query()->fromSub($peringkat, 'peringkat_setoran')->where('urutan', 1)->get()
                ->groupBy('anggota_tahfidz_id')->map(fn ($nilai) => $nilai->keyBy('nomor_surah'));
        }

        $jumlahSetoran = $pertemuan->setoran()->count();

        return response()->view('tahfidz.kolektif', compact(
            'kelompok', 'pertemuan', 'peserta', 'siswaDipilih', 'surah', 'surahDipilih', 'anggota', 'terakhir', 'jumlahSetoran'
        ))->header('Cache-Control', 'private, no-store');
    }

    public function store(Request $request, int $kelompok, int $pertemuan)
    {
        [$kelompok, $pertemuan] = $this->konteks($request, $kelompok, $pertemuan);
        $api = $request->is('api/*');
        $nilai = $request->input('nilai');
        if (is_array($nilai)) {
            $request->merge(['nilai' => array_map(fn ($daftar) => is_array($daftar)
                ? array_map(fn ($angka) => is_string($angka) ? str_replace(',', '.', trim($angka)) : $angka, $daftar)
                : $daftar, $nilai)]);
        }
        $data = $request->validate([
            ...$this->aturanFilter(),
            'surah' => ['required', 'array', 'list', 'min:1', 'max:37'],
            'siswa' => [$api ? 'required' : 'nullable', 'integer', 'min:1'],
            'token_batch' => ['required', 'uuid'],
            'nilai' => ['required', 'array', 'min:1', 'max:'.($api ? 1 : self::SISWA_PER_HALAMAN)],
            'nilai.*' => ['required', 'array', 'min:1', 'max:37'],
            'nilai.*.*' => ['nullable', 'numeric', 'regex:/^\d{1,3}(?:\.\d{1,2})?$/D', 'between:0,100'],
        ], [
            'nilai.*.*.regex' => 'Masukkan nilai 0-100 dengan maksimal dua angka desimal.',
            'nilai.*.*.between' => 'Nilai harus antara 0 dan 100.',
            'nilai.*.*.numeric' => 'Isian nilai harus berupa angka.',
        ]);
        $jumlah = $this->layanan->simpanKolektif($request->user(), $kelompok, $pertemuan->id, $data);
        $pesan = $jumlah.' setoran berhasil disimpan. Riwayat sebelumnya tetap tersimpan.';
        if ($api) {
            return response()->json(['data' => ['jumlah_setoran' => $jumlah], 'pesan' => $pesan])
                ->header('Cache-Control', 'private, no-store');
        }

        return redirect()->route('tahfidz.kolektif.index', [
            'kelompok' => $kelompok, 'pertemuan' => $pertemuan,
            'surah' => $data['surah'], 'siswa' => $data['siswa'] ?? null, 'page' => $data['page'] ?? null,
        ])->with('berhasil', $pesan);
    }

    private function aturanFilter(): array
    {
        return [
            'surah' => ['nullable', 'array', 'list', 'max:37'],
            'surah.*' => ['required', 'integer', 'between:78,114', 'distinct', 'exists:surah_tahfidz,nomor'],
            'siswa' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }

    private function konteks(Request $request, int $kelompok, int $pertemuan): array
    {
        $kelompok = $this->akses->cakupan($request->user())->with('tahunPelajaran')->findOrFail($kelompok);
        abort_unless($this->akses->catat($request->user(), $kelompok), 403);

        return [$kelompok, $kelompok->pertemuan()->findOrFail($pertemuan)];
    }
}
