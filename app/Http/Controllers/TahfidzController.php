<?php

namespace App\Http\Controllers;

use App\Models\KelompokTahfidz;
use App\Models\TahunPelajaran;
use App\Services\Tahfidz\AksesTahfidz;
use App\Services\Tahfidz\KelompokTahfidzService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TahfidzController extends Controller
{
    public function __construct(private readonly AksesTahfidz $akses, private readonly KelompokTahfidzService $layanan) {}

    public function index(Request $request)
    {
        $cakupan = $this->akses->cakupan($request->user());
        $bolehKelola = $this->akses->kelola($request->user());
        $tahun = TahunPelajaran::query()->when(! $bolehKelola, fn ($q) => $q
            ->whereIn('id', (clone $cakupan)->select('tahun_pelajaran_id')))
            ->orderByDesc('aktif')->orderByDesc('tanggal_mulai')->orderByDesc('id')->get();
        $filter = $request->validate([
            'tahun' => ['nullable', 'integer', Rule::in($tahun->modelKeys())],
            'status' => ['nullable', Rule::in(['aktif', 'nonaktif'])],
        ]);
        $tahunId = $filter['tahun'] ?? $tahun->first()?->id;
        $status = $filter['status'] ?? null;
        $kelompok = $cakupan->where('tahun_pelajaran_id', $tahunId)
            ->when($status, fn ($q) => $q->where('aktif', $status === 'aktif'))
            ->with('pembimbing:id,nama_lengkap')->withCount('anggotaAktif')
            ->orderByDesc('aktif')->orderBy('nama')->orderBy('id')->paginate(20)->withQueryString();

        return view('tahfidz.index', compact('kelompok', 'tahun', 'tahunId', 'status', 'bolehKelola'));
    }

    public function surah(Request $request)
    {
        abort_unless($this->akses->lihat($request->user()), 403);

        return view('tahfidz.surah', ['surah' => DB::table('surah_tahfidz')->orderBy('nomor')->get()]);
    }

    public function show(Request $request, int $kelompok)
    {
        $kelompok = $this->akses->cakupan($request->user())->with(['tahunPelajaran', 'pembimbing'])->findOrFail($kelompok);
        $anggota = $this->layanan->daftarAnggota($kelompok);

        return view('tahfidz.show', ['kelompok' => $kelompok, 'anggota' => $anggota,
            'bolehKelola' => $this->akses->kelola($request->user()),
            'bolehCatat' => $this->akses->catat($request->user(), $kelompok),
            'anggotaIds' => $kelompok->anggotaAktif()->pluck('id', 'siswa_id'),
            'pertemuan' => $kelompok->pertemuan()->withCount('setoran')->latest('tanggal')->paginate(10, ['*'], 'pertemuan'),
        ]);
    }

    public function form(Request $request, ?int $kelompok = null)
    {
        abort_unless($this->akses->kelola($request->user()), 403);
        $kelompok = $kelompok === null ? null : KelompokTahfidz::with('tahunPelajaran')->findOrFail($kelompok);
        $pembimbing = $this->layanan->pembimbing();
        if ($kelompok) {
            $pembimbing->orWhere('id', $kelompok->pembimbing_pegawai_id);
        }

        return view('tahfidz.form', [
            'kelompok' => $kelompok,
            'tahun' => $kelompok ? collect() : TahunPelajaran::orderByDesc('aktif')->orderByDesc('tanggal_mulai')->get(),
            'pembimbing' => $pembimbing->orderBy('nama_lengkap')->get(['id', 'nama_lengkap', 'aktif']),
        ]);
    }

    public function simpan(Request $request, ?int $kelompok = null)
    {
        abort_unless($this->akses->kelola($request->user()), 403);
        $kelompok = $kelompok === null ? null : KelompokTahfidz::findOrFail($kelompok);
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:120'],
            'pembimbing_pegawai_id' => ['required', 'integer', 'min:1'],
            'aktif' => ['required', 'boolean'],
            'tahun_pelajaran_id' => $kelompok ? ['prohibited'] : ['required', 'integer', 'exists:tahun_pelajaran,id'],
            'versi' => $kelompok ? ['required', 'integer', 'min:1'] : ['prohibited'],
        ]);
        $baru = $kelompok === null;
        $kelompok = $this->layanan->simpan($data, $kelompok);

        return redirect()->route($baru && $kelompok->aktif ? 'tahfidz.anggota.edit' : 'tahfidz.show', $kelompok)
            ->with('berhasil', $baru ? 'Kelompok berhasil dibuat.' : 'Kelompok berhasil diperbarui.');
    }

    public function anggota(Request $request, int $kelompok)
    {
        abort_unless($this->akses->kelola($request->user()), 403);
        $kelompok = KelompokTahfidz::with(['tahunPelajaran', 'pembimbing'])->findOrFail($kelompok);
        abort_unless($kelompok->aktif, 403);

        return view('tahfidz.anggota', ['kelompok' => $kelompok, ...$this->layanan->pilihanAnggota($kelompok)]);
    }

    public function simpanAnggota(Request $request, int $kelompok)
    {
        abort_unless($this->akses->kelola($request->user()), 403);
        $kelompok = KelompokTahfidz::findOrFail($kelompok);
        $data = $request->validate([
            'versi' => ['required', 'integer', 'min:1'],
            'daftar_lengkap' => ['required', 'accepted'],
            'siswa_ids' => ['sometimes', 'array', 'list', 'max:1000'],
            'siswa_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
        ], ['daftar_lengkap.required' => 'Daftar pilihan tidak diterima lengkap. Muat ulang sebelum menyimpan.']);
        $this->layanan->simpanAnggota($kelompok, array_map('intval', $data['siswa_ids'] ?? []), (int) $data['versi']);

        return redirect()->route('tahfidz.show', $kelompok)->with('berhasil', 'Daftar anggota berhasil disimpan.');
    }
}
