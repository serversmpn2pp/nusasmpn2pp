<?php

namespace App\Http\Controllers;

use App\Models\PublikasiRaporTahfidz;
use App\Models\SurahTahfidz;
use App\Models\TahunPelajaran;
use App\Models\TargetRaporTahfidz;
use App\Services\Tahfidz\AksesTahfidz;
use App\Services\Tahfidz\PublikasiTahfidzService;
use App\Services\Tahfidz\RaporTahfidzService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RaporTahfidzController extends Controller
{
    public function __construct(private readonly AksesTahfidz $akses, private readonly RaporTahfidzService $rapor,
        private readonly PublikasiTahfidzService $publikasi) {}

    public function index(Request $request)
    {
        $cakupan = $this->kelompokRapor($request);
        $filter = $request->validate(['kelompok' => ['nullable', 'integer', 'min:1']]);
        $kelompokList = $cakupan->orderByDesc('aktif')->orderByDesc('tahun_pelajaran_id')->orderBy('nama')->get();
        $kelompok = isset($filter['kelompok']) ? $kelompokList->firstWhere('id', (int) $filter['kelompok'])
            : ($kelompokList->first(fn ($item) => $item->aktif && $item->tahunPelajaran->aktif) ?? $kelompokList->first());
        abort_if(isset($filter['kelompok']) && ! $kelompok, 404);
        $daftar = $kelompok ? $this->rapor->daftar($kelompok) : collect();
        $siap = $daftar->whereNotNull('target')->count();
        $bolehKelola = $this->akses->kelola($request->user());

        return response()->view('tahfidz.rapor-index', compact('kelompokList', 'kelompok', 'daftar', 'siap', 'bolehKelola'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function cetakKelompok(Request $request, int $kelompok)
    {
        $kelompok = $this->kelompokRapor($request)->findOrFail($kelompok);
        $daftar = $this->rapor->daftar($kelompok);
        $kembali = route('tahfidz.rapor.index', ['kelompok' => $kelompok->id]);
        if ($daftar->isEmpty() || $daftar->contains(fn ($item) => ! $item['target'])) {
            return redirect($kembali)->withErrors(['rapor' => $daftar->isEmpty() ? 'Belum ada anggota kelompok untuk dicetak.'
                : 'Lengkapi kelas dan target rapor yang belum tersedia sebelum mencetak seluruh kelompok.']);
        }

        return response()->view('tahfidz.rapor-kelompok-cetak', ['kelompok' => $kelompok,
            'dokumenList' => $this->rapor->dokumenKelompok($kelompok, $daftar), 'kembali' => $kembali,
        ])->header('Cache-Control', 'private, no-store');
    }

    private function kelompokRapor(Request $request): Builder
    {
        $pengguna = $request->user();

        return $this->akses->cakupan($pengguna)->when(! $this->akses->kelola($pengguna), fn ($q) => $q->where('aktif', true))
            ->with(['tahunPelajaran', 'pembimbing']);
    }

    public function pengaturan(Request $request)
    {
        abort_unless($this->akses->kelola($request->user()), 403);
        $filter = $request->validate(['tahun' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'tingkat' => ['nullable', 'integer', Rule::in([7, 8, 9])]]);
        $tahun = TahunPelajaran::orderByDesc('aktif')->orderByDesc('tanggal_mulai')->orderByDesc('id')->get();
        $terpilih = $filter['tahun'] ?? $tahun->first()?->id;
        $tingkat = (int) ($filter['tingkat'] ?? 7);
        $target = TargetRaporTahfidz::where('tahun_pelajaran_id', $terpilih)->where('tingkat', $tingkat)->first();
        $surah = SurahTahfidz::orderBy('nomor')->get();

        return view('tahfidz.target-rapor', compact('tahun', 'terpilih', 'tingkat', 'target', 'surah'));
    }

    public function simpan(Request $request)
    {
        abort_unless($this->akses->kelola($request->user()), 403);
        $data = $request->validate([
            'tahun_pelajaran_id' => ['required', 'integer', 'exists:tahun_pelajaran,id'],
            'tingkat' => ['required', 'integer', Rule::in([7, 8, 9])],
            'target' => ['required', 'integer', 'between:1,37'],
            'mode' => ['required', Rule::in(['tetap', 'bebas'])],
            'surah' => ['exclude_unless:mode,tetap', 'required', 'array', 'list', 'max:37'],
            'surah.*' => ['required', 'integer', 'distinct', 'between:78,114', 'exists:surah_tahfidz,nomor'],
            'tanggal_rapor' => ['required', 'date_format:Y-m-d'],
            'batas_cukup' => ['required', 'integer', 'between:1,98'],
            'batas_baik' => ['required', 'integer', 'between:2,99', 'gt:batas_cukup'],
            'batas_sangat_baik' => ['required', 'integer', 'between:3,100', 'gt:batas_baik'],
        ], ['surah.required' => 'Pilih surah target terlebih dahulu.']);
        $tahun = TahunPelajaran::findOrFail($data['tahun_pelajaran_id']);
        if (($tahun->tanggal_mulai && $data['tanggal_rapor'] < $tahun->tanggal_mulai->toDateString())
            || ($tahun->tanggal_selesai && $data['tanggal_rapor'] > $tahun->tanggal_selesai->toDateString())) {
            throw ValidationException::withMessages(['tanggal_rapor' => 'Tanggal rapor harus berada dalam tahun pelajaran yang dipilih.']);
        }
        if ($data['mode'] === 'tetap' && count($data['surah']) !== (int) $data['target']) {
            throw ValidationException::withMessages(['surah' => 'Jumlah surah yang dipilih harus sama dengan target pembagi.']);
        }
        $data['surah'] = $data['mode'] === 'tetap' ? array_map('intval', $data['surah']) : [];
        TargetRaporTahfidz::updateOrCreate(['tahun_pelajaran_id' => $tahun->id, 'tingkat' => $data['tingkat']], $data);

        if ($request->is('api/*')) {
            return response()->json(['data' => ['tersimpan' => true]])->header('Cache-Control', 'private, no-store');
        }

        return redirect()->route('tahfidz.rapor.pengaturan', ['tahun' => $tahun->id, 'tingkat' => $data['tingkat']])
            ->with('berhasil', 'Target rapor Tahfidz disimpan.');
    }

    public function cetak(Request $request, int $kelompok, int $anggota)
    {
        [$kelompok, $anggota] = $this->akses->pesertaDokumen($request->user(), $kelompok, $anggota);
        $rapor = $this->rapor->siswa($kelompok, $anggota);
        if (! isset($rapor['dokumen'])) {
            return redirect()->route('tahfidz.rekap.siswa', [$kelompok, $anggota])->withErrors(['rapor' => $rapor['pesan']]);
        }
        $bolehPublikasi = $this->akses->kelola($request->user());
        $terbit = $bolehPublikasi ? PublikasiRaporTahfidz::where('siswa_id', $anggota->siswa_id)
            ->where('tahun_pelajaran_id', $kelompok->tahun_pelajaran_id)->whereNotNull('dipublikasikan_pada')->first() : null;

        return response()->view('tahfidz.rapor-cetak', [...$rapor, 'kembali' => $request->query('asal') === 'kelompok'
            ? route('tahfidz.rapor.index', ['kelompok' => $kelompok->id]) : route('tahfidz.rekap.siswa', [$kelompok, $anggota]),
            'aksiPublikasi' => $bolehPublikasi ? route('tahfidz.rapor.publikasi', [$kelompok, $anggota]) : null,
            'terbit' => $terbit, 'sidik' => $this->sidik($rapor['dokumen']),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function publikasi(Request $request, int $kelompok, int $anggota)
    {
        abort_unless($this->akses->kelola($request->user()), 403);
        [$kelompok, $anggota] = $this->akses->pesertaDokumen($request->user(), $kelompok, $anggota);
        $data = $request->validate(['bagikan' => ['required', 'boolean'], 'sidik' => ['required_if:bagikan,1', 'nullable', 'string', 'size:64']]);
        $bagikan = (bool) $data['bagikan'];
        $dokumen = null;
        if ($bagikan) {
            $rapor = $this->rapor->siswa($kelompok, $anggota);
            if (! isset($rapor['dokumen'])) {
                throw ValidationException::withMessages(['rapor' => $rapor['pesan']]);
            }
            $dokumen = $rapor['dokumen'];
            if (! hash_equals($this->sidik($dokumen), $data['sidik'] ?? '')) {
                throw ValidationException::withMessages(['rapor' => 'Isi rapor berubah. Periksa pratinjau terbaru sebelum membagikannya.']);
            }
        }
        $this->publikasi->rapor($request->user(), $kelompok, $anggota, $dokumen);

        if ($request->is('api/*')) {
            return response()->json(['data' => ['tersimpan' => true]])->header('Cache-Control', 'private, no-store');
        }

        return redirect()->route('tahfidz.rapor.cetak', [$kelompok, $anggota])
            ->with('berhasil', $bagikan ? 'Rapor dibagikan ke akun orang tua.' : 'Publikasi rapor ditarik.');
    }

    private function sidik(array $dokumen): string
    {
        return hash('sha256', json_encode($dokumen, JSON_THROW_ON_ERROR));
    }
}
