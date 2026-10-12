<?php

namespace App\Http\Controllers;

use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Services\Nilai\LampiranPerilakuStsService;
use App\Services\Nilai\MapelRaporStsService;
use App\Services\Nilai\PengelolaanRaporStsService;
use App\Services\Nilai\RaporStsService;
use Illuminate\Http\Request;

class RaporStsController extends Controller
{
    public function index(Request $request, RaporStsService $service)
    {
        abort_unless(RaporStsService::dapatMengakses($request->user()), 403);
        $data = $request->validate(['kegiatan_id' => ['nullable', 'integer'], 'kelas_id' => ['nullable', 'integer']]);
        $daftarKegiatan = KegiatanUjianCbt::whereHas('jenisUjianCbt', fn ($q) => $q->where('kode', 'STS'))
            ->whereIn('tahun_pelajaran_id', $service->kelasDalamCakupan($request->user())->select('tahun_pelajaran_id'))
            ->with('tahunPelajaran')->orderByDesc('tanggal_mulai')->get();
        $kegiatan = isset($data['kegiatan_id']) ? $daftarKegiatan->firstWhere('id', $data['kegiatan_id']) : $daftarKegiatan->first();
        abort_if(isset($data['kegiatan_id']) && ! $kegiatan, 404);
        $daftarKelas = $kegiatan ? $service->kelasDalamCakupan($request->user())
            ->where('tahun_pelajaran_id', $kegiatan->tahun_pelajaran_id)->orderBy('tingkat')->orderBy('nama')->get() : collect();
        $kelas = isset($data['kelas_id']) ? $daftarKelas->firstWhere('id', $data['kelas_id']) : $daftarKelas->first();
        abort_if(isset($data['kelas_id']) && ! $kelas, 404);
        $laporan = $kelas && $kegiatan ? $service->bangun($kegiatan, $kelas) : null;
        $pilihanMapel = $laporan ? app(MapelRaporStsService::class)->konteks($kegiatan, (int) $kelas->tingkat) : null;
        $dapatMengaturMapel = MapelRaporStsService::dapatMengatur($request->user());
        $perilaku = $laporan ? app(LampiranPerilakuStsService::class)->konteks($kegiatan, $kelas) : null;

        return response()->view('rapor-sts.index', compact('daftarKegiatan', 'daftarKelas', 'kegiatan', 'kelas', 'laporan', 'pilihanMapel', 'dapatMengaturMapel', 'perilaku'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function mapel(Request $request, KegiatanUjianCbt $kegiatan, Kelas $kelas, RaporStsService $rapor, MapelRaporStsService $service)
    {
        abort_unless(MapelRaporStsService::dapatMengatur($request->user()), 403);
        $rapor->pastikanCakupan($request->user(), $kegiatan, $kelas);
        $data = $request->validate([
            'versi_mapel' => ['required', 'integer', 'min:0'],
            'sidik_mapel' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'mapel_ids' => ['required', 'array', 'min:1', 'max:100'],
            'mapel_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'alasan_mapel' => ['required', 'string', 'max:500', 'regex:/\S/'],
        ]);
        $service->simpan($request->user(), $kegiatan, (int) $kelas->tingkat, $data);

        return $this->kembali($kegiatan, $kelas)->with('berhasil', 'Pilihan mapel STS disimpan untuk seluruh kelas tingkat '.$kelas->tingkat.'. Rapor, leger, statistik, dan ranking mengikuti pilihan yang sama.');
    }

    public function pengaturan(Request $request, KegiatanUjianCbt $kegiatan, Kelas $kelas, PengelolaanRaporStsService $service)
    {
        $service->pengaturan($request->user(), $kegiatan, $kelas, $request->all());

        return $this->kembali($kegiatan, $kelas)->with('berhasil', 'Periode rapor disimpan. Periksa rekap kehadiran sebelum mencetak.');
    }

    public function kehadiran(Request $request, KegiatanUjianCbt $kegiatan, Kelas $kelas, PengelolaanRaporStsService $service)
    {
        $service->kehadiran($request->user(), $kegiatan, $kelas, $request->all());

        return $this->kembali($kegiatan, $kelas)->with('berhasil', 'Pemeriksaan kehadiran rapor disimpan. Presensi harian siswa tidak diubah.');
    }

    public function pengecualian(Request $request, KegiatanUjianCbt $kegiatan, Kelas $kelas, PengelolaanRaporStsService $service)
    {
        $service->pengecualian($request->user(), $kegiatan, $kelas, $request->all());

        return $this->kembali($kegiatan, $kelas)->with('berhasil', 'Keterangan tidak mengikuti STS disimpan.')
            ->with('rincian_siswa', $request->integer('anggota_id'));
    }

    public function cetak(Request $request, KegiatanUjianCbt $kegiatan, Kelas $kelas, RaporStsService $service)
    {
        $service->pastikanCakupan($request->user(), $kegiatan, $kelas);
        $data = $request->validate(['anggota_id' => ['nullable', 'integer'], 'pratinjau' => ['nullable', 'boolean'], 'perilaku' => ['nullable', 'boolean']]);
        $laporan = $service->bangun($kegiatan, $kelas);
        if (isset($data['anggota_id'])) {
            $laporan['baris'] = $laporan['baris']->where('anggota.id', (int) $data['anggota_id'])->values();
        }
        abort_if($laporan['baris']->isEmpty(), 404);
        $pratinjau = (bool) ($data['pratinjau'] ?? false);
        if (! $pratinjau && ! $laporan['baris']->every(fn ($siswa) => $siswa['siap'])) {
            return $this->kembali($kegiatan, $kelas)->withErrors(['cetak' => 'Rapor belum siap dicetak. Simpan periode rapor, tetapkan wali kelas, dan simpan pemeriksaan kehadiran seluruh siswa yang akan dicetak. Periode presensi harus sudah berakhir.']);
        }

        $lampiran = null;
        if ($data['perilaku'] ?? false) {
            $lampiran = app(LampiranPerilakuStsService::class)->konteks($kegiatan, $kelas);
            $lampiran['baris'] = $lampiran['baris']->whereIn('anggota.id', $laporan['baris']->pluck('anggota.id'))->keyBy('anggota.id');
            // Preview also uses reviewed public summaries, never raw BK notes.
            if (! $lampiran['baris']->every(fn ($b) => $b['siap'])) {
                return $this->kembali($kegiatan, $kelas)->withErrors(['cetak' => 'Lampiran perilaku belum diperiksa atau datanya berubah. Guru BK perlu memeriksa kembali sebelum rapor gabungan dapat dibuka. Cetak nilai saja tetap tersedia.']);
            }
        }

        return response()->view('rapor-sts.cetak', [...$laporan, 'pratinjau' => $pratinjau, 'lampiran' => $lampiran])
            ->header('Cache-Control', 'private, no-store');
    }

    private function kembali(KegiatanUjianCbt $kegiatan, Kelas $kelas)
    {
        return redirect()->route('rapor-sts.index', ['kegiatan_id' => $kegiatan->id, 'kelas_id' => $kelas->id]);
    }
}
