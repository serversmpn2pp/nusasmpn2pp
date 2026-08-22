<?php

namespace App\Http\Controllers;

use App\Models\JenisUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\PanitiaUjianCbt;
use App\Models\Pegawai;
use App\Models\TahunPelajaran;
use App\Services\Cbt\SinkronkanPeranPanitiaUjian;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UjianTerpusatController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'kata_kunci' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['semua', ...array_keys(KegiatanUjianCbt::DAFTAR_STATUS)])],
        ]);
        $kataKunci = trim((string) ($data['kata_kunci'] ?? ''));
        $status = $data['status'] ?? 'semua';
        $pengguna = $request->user();

        $queryDasar = KegiatanUjianCbt::query()
            ->when(! $pengguna->memilikiIzin(['cbt.kelola', 'cbt.terpusat_lihat']), function (Builder $query) use ($pengguna) {
                $query->whereHas('panitiaUjianCbt', fn (Builder $query) => $query
                    ->where('pegawai_id', $pengguna->pegawai_id)
                    ->where('aktif', true));
            });

        $daftarKegiatan = (clone $queryDasar)
            ->with(['jenisUjianCbt', 'tahunPelajaran'])
            ->withCount(['panitiaUjianCbt', 'sesiKegiatanUjianCbt', 'ruangKegiatanUjianCbt', 'jadwalUjianCbt'])
            ->when($status !== 'semua', fn (Builder $query) => $query->where('status', $status))
            ->when($kataKunci !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($kataKunci) {
                $query->where('nama', 'like', '%'.$kataKunci.'%')
                    ->orWhere('kode', 'like', '%'.$kataKunci.'%')
                    ->orWhereHas('jenisUjianCbt', fn (Builder $query) => $query->where('nama', 'like', '%'.$kataKunci.'%'));
            }))
            ->orderByRaw("CASE WHEN status IN ('draft', 'aktif') THEN 0 ELSE 1 END")
            ->orderByDesc('tanggal_mulai')
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        return view('ujian-terpusat.index', [
            'daftarKegiatan' => $daftarKegiatan,
            'kataKunci' => $kataKunci,
            'status' => $status,
            'daftarStatus' => KegiatanUjianCbt::DAFTAR_STATUS,
            'ringkasan' => [
                'total' => (clone $queryDasar)->where('status', '!=', 'nonaktif')->count(),
                'persiapan' => (clone $queryDasar)->where('status', 'draft')->count(),
                'aktif' => (clone $queryDasar)->where('status', 'aktif')->count(),
                'selesai' => (clone $queryDasar)->where('status', 'selesai')->count(),
            ],
        ]);
    }

    public function create()
    {
        return view('ujian-terpusat.create', $this->dataForm([
            'tahunPelajaranAwal' => TahunPelajaran::query()->where('aktif', true)->first(),
        ]));
    }

    public function store(Request $request)
    {
        $data = $this->rapikan($request->validate($this->aturanValidasi()));
        $kegiatan = KegiatanUjianCbt::create([
            ...$data,
            'kode' => $this->buatKodeSaran($data['tahun_pelajaran_id']),
            'dibuat_oleh_pengguna_id' => $request->user()?->id,
        ]);

        return redirect()
            ->route('ujian-terpusat.show', $kegiatan)
            ->with('berhasil', 'Ujian Terpusat berhasil dibuat. Lanjutkan dengan menentukan panitia, sesi, dan ruang.');
    }

    public function show(Request $request, KegiatanUjianCbt $kegiatanUjianCbt)
    {
        $this->pastikanDapatDiakses($request, $kegiatanUjianCbt);
        $kegiatanUjianCbt->load([
            'jenisUjianCbt',
            'tahunPelajaran',
            'dibuatOleh',
            'panitiaUjianCbt' => fn ($query) => $query->with('pegawai.pengguna')->orderBy('jabatan')->orderBy('id'),
            'sesiKegiatanUjianCbt' => fn ($query) => $query->orderBy('urutan')->orderBy('waktu_mulai'),
            'ruangKegiatanUjianCbt' => fn ($query) => $query->orderBy('urutan')->orderBy('kode'),
            'kelompokPesertaKegiatanUjianCbt' => fn ($query) => $query
                ->withCount('penempatanPesertaUjianCbt')
                ->orderBy('tingkat'),
            'jadwalUjianCbt.ujianCbt',
        ]);
        $kegiatanUjianCbt->loadCount('jadwalUjianCbt');

        return view('ujian-terpusat.show', [
            'kegiatan' => $kegiatanUjianCbt,
            'daftarPegawai' => Pegawai::query()
                ->with('pengguna')
                ->where('aktif', true)
                ->orderBy('nama_lengkap')
                ->get(),
            'daftarJabatan' => PanitiaUjianCbt::DAFTAR_JABATAN,
            'bolehKelolaUtama' => $request->user()->memilikiIzin('cbt.kelola'),
            'bolehKelolaPersiapan' => $request->user()->memilikiIzin(['cbt.kelola', 'cbt.panitia']),
        ]);
    }

    public function edit(Request $request, KegiatanUjianCbt $kegiatanUjianCbt)
    {
        $this->pastikanDapatDiakses($request, $kegiatanUjianCbt);

        return view('ujian-terpusat.edit', $this->dataForm([
            'kegiatan' => $kegiatanUjianCbt,
            'tahunPelajaranAwal' => null,
        ]));
    }

    public function update(Request $request, KegiatanUjianCbt $kegiatanUjianCbt, SinkronkanPeranPanitiaUjian $sinkronkanPeran)
    {
        $this->pastikanDapatDiakses($request, $kegiatanUjianCbt);
        $data = $this->rapikan($request->validate($this->aturanValidasi()));
        $kegiatanUjianCbt->update($data);
        $kegiatanUjianCbt->panitiaUjianCbt()->with('pegawai')->get()
            ->each(fn (PanitiaUjianCbt $panitia) => $sinkronkanPeran->sinkronkan($panitia->pegawai));

        return redirect()
            ->route('ujian-terpusat.show', $kegiatanUjianCbt)
            ->with('berhasil', 'Informasi Ujian Terpusat berhasil diperbarui.');
    }

    public function destroy(Request $request, KegiatanUjianCbt $kegiatanUjianCbt, SinkronkanPeranPanitiaUjian $sinkronkanPeran)
    {
        $this->pastikanDapatDiakses($request, $kegiatanUjianCbt);

        if ($kegiatanUjianCbt->status !== 'draft' || $kegiatanUjianCbt->jadwalUjianCbt()->exists()) {
            throw ValidationException::withMessages([
                'kegiatan' => 'Hanya Ujian Terpusat berstatus persiapan dan belum memiliki jadwal yang dapat dihapus.',
            ]);
        }

        $pegawai = $kegiatanUjianCbt->panitiaUjianCbt()->with('pegawai')->get()->pluck('pegawai')->filter();
        $kegiatanUjianCbt->delete();
        $pegawai->each(fn (Pegawai $item) => $sinkronkanPeran->sinkronkan($item));

        return redirect()->route('ujian-terpusat.index')->with('berhasil', 'Ujian Terpusat berhasil dihapus.');
    }

    private function dataForm(array $tambahan = []): array
    {
        return array_merge([
            'daftarJenis' => JenisUjianCbt::query()
                ->where('aktif', true)
                ->where('kode', '!=', 'ASESMEN_KELAS')
                ->orderBy('urutan')
                ->orderBy('nama')
                ->get(),
            'daftarTahunPelajaran' => TahunPelajaran::query()
                ->orderByDesc('aktif')
                ->orderByDesc('tanggal_mulai')
                ->get(),
            'daftarStatus' => KegiatanUjianCbt::DAFTAR_STATUS,
        ], $tambahan);
    }

    private function aturanValidasi(): array
    {
        return [
            'jenis_ujian_cbt_id' => ['required', 'integer', Rule::exists('jenis_ujian_cbt', 'id')->where(fn ($query) => $query->where('aktif', true)->where('kode', '!=', 'ASESMEN_KELAS'))],
            'tahun_pelajaran_id' => ['required', 'integer', 'exists:tahun_pelajaran,id'],
            'nama' => ['required', 'string', 'max:180'],
            'semester' => ['required', Rule::in(['ganjil', 'genap'])],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'status' => ['required', Rule::in(array_keys(KegiatanUjianCbt::DAFTAR_STATUS))],
            'keterangan' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function rapikan(array $data): array
    {
        return [
            'jenis_ujian_cbt_id' => (int) $data['jenis_ujian_cbt_id'],
            'tahun_pelajaran_id' => (int) $data['tahun_pelajaran_id'],
            'nama' => trim($data['nama']),
            'semester' => $data['semester'],
            'tanggal_mulai' => $data['tanggal_mulai'],
            'tanggal_selesai' => $data['tanggal_selesai'],
            'status' => $data['status'],
            'keterangan' => filled($data['keterangan'] ?? null) ? trim($data['keterangan']) : null,
        ];
    }

    private function buatKodeSaran(int $tahunPelajaranId): string
    {
        $tahun = TahunPelajaran::find($tahunPelajaranId)?->nama ?: now()->format('Y');
        $tahun = preg_replace('/\D+/', '', $tahun);
        $prefix = 'UT-'.substr($tahun, 0, 4);
        $urutan = KegiatanUjianCbt::query()->where('kode', 'like', $prefix.'-%')->count() + 1;

        return sprintf('%s-%03d', $prefix, $urutan);
    }

    private function pastikanDapatDiakses(Request $request, KegiatanUjianCbt $kegiatan): void
    {
        abort_unless($kegiatan->dapatDiaksesOleh($request->user()), 403);
    }
}
