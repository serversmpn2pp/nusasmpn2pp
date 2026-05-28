<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Pegawai;
use App\Models\TahunPelajaran;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KelasController extends Controller
{
    public function index(Request $request)
    {
        $kata_kunci = $request->kata_kunci;
        $status = $request->input('status', 'semua');
        $tahunPelajaranId = $request->input('tahun_pelajaran_id');
        $cakupanWaliKelas = $request->user()?->membatasiCakupanWaliKelas() ?? false;

        if (! in_array($status, ['semua', 'aktif', 'nonaktif'], true)) {
            $status = 'semua';
        }

        $tahunPelajaran = TahunPelajaran::query()
            ->when($cakupanWaliKelas, function ($query) use ($request) {
                $query->whereHas('kelas', fn ($query) => $query->whereIn('id', $request->user()->kelasWaliIds()));
            })
            ->orderByDesc('aktif')
            ->orderByDesc('nama')
            ->get();

        $kelas = Kelas::query()
            ->with(['tahunPelajaran', 'waliKelas'])
            ->withCount('anggotaKelas')
            ->when($cakupanWaliKelas, fn ($query) => $query->whereIn('id', $request->user()->kelasWaliIds()))
            ->when($tahunPelajaranId, function ($query, $tahunPelajaranId) {
                $query->where('tahun_pelajaran_id', $tahunPelajaranId);
            })
            ->when($status === 'aktif', function ($query) {
                $query->where('aktif', true);
            })
            ->when($status === 'nonaktif', function ($query) {
                $query->where('aktif', false);
            })
            ->when($kata_kunci, function ($query, $kata_kunci) {
                $query->where(function ($query) use ($kata_kunci) {
                    $query->where('nama', 'ilike', '%' . $kata_kunci . '%')
                        ->orWhereHas('waliKelas', function ($query) use ($kata_kunci) {
                            $query->where('nama_lengkap', 'ilike', '%' . $kata_kunci . '%');
                        });
                });
            })
            ->orderByDesc(
                TahunPelajaran::select('aktif')
                    ->whereColumn('tahun_pelajaran.id', 'kelas.tahun_pelajaran_id')
                    ->limit(1)
            )
            ->orderByDesc(
                TahunPelajaran::select('nama')
                    ->whereColumn('tahun_pelajaran.id', 'kelas.tahun_pelajaran_id')
                    ->limit(1)
            )
            ->orderBy('tingkat')
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString();

        $jumlahKelas = $this->terapkanCakupanWaliKelas(Kelas::query(), $request)->count();
        $jumlahAktif = $this->terapkanCakupanWaliKelas(Kelas::query(), $request)->where('aktif', true)->count();
        $jumlahNonaktif = $this->terapkanCakupanWaliKelas(Kelas::query(), $request)->where('aktif', false)->count();

        return view('kelas.index', compact(
            'kelas',
            'tahunPelajaran',
            'tahunPelajaranId',
            'kata_kunci',
            'status',
            'jumlahKelas',
            'jumlahAktif',
            'jumlahNonaktif',
            'cakupanWaliKelas',
        ));
    }

    public function create(Request $request)
    {
        return view('kelas.create', [
            'tahunPelajaran' => $this->ambilTahunPelajaran(),
            'pegawai' => $this->ambilPegawai(),
            'tahunPelajaranDipilih' => $request->input('tahun_pelajaran_id'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->aturanValidasi());
        $data['aktif'] = $request->boolean('aktif');

        $kelas = Kelas::create($data);

        return redirect()
            ->route('kelas.show', $kelas)
            ->with('berhasil', 'Kelas berhasil ditambahkan.');
    }

    public function show(Request $request, Kelas $kelas)
    {
        $this->pastikanBolehAksesKelas($request, $kelas);

        $kelas->load(['tahunPelajaran', 'waliKelas'])
            ->loadCount('anggotaKelas');

        return view('kelas.show', compact('kelas'));
    }

    public function edit(Kelas $kelas)
    {
        $this->pastikanBolehAksesKelas(request(), $kelas);

        return view('kelas.edit', [
            'kelas' => $kelas,
            'tahunPelajaran' => $this->ambilTahunPelajaran(),
            'pegawai' => $this->ambilPegawai(),
            'tahunPelajaranDipilih' => null,
        ]);
    }

    public function update(Request $request, Kelas $kelas)
    {
        $this->pastikanBolehAksesKelas($request, $kelas);

        $data = $request->validate($this->aturanValidasi($kelas));
        $data['aktif'] = $request->boolean('aktif');

        $kelas->update($data);

        return redirect()
            ->route('kelas.show', $kelas)
            ->with('berhasil', 'Kelas berhasil diperbarui.');
    }

    public function destroy(Kelas $kelas)
    {
        $this->pastikanBolehAksesKelas(request(), $kelas);

        $kelas->update(['aktif' => false]);

        return redirect()
            ->route('kelas.index')
            ->with('berhasil', 'Kelas berhasil dinonaktifkan.');
    }

    private function aturanValidasi(?Kelas $kelas = null): array
    {
        return [
            'tahun_pelajaran_id' => 'required|exists:tahun_pelajaran,id',
            'wali_kelas_id' => 'nullable|exists:pegawai,id',
            'nama' => [
                'required',
                'string',
                'max:50',
                Rule::unique('kelas', 'nama')
                    ->where('tahun_pelajaran_id', request('tahun_pelajaran_id'))
                    ->ignore($kelas),
            ],
            'tingkat' => 'nullable|integer|min:7|max:9',
            'kapasitas' => 'nullable|integer|min:1|max:500',
            'aktif' => 'nullable|boolean',
            'keterangan' => 'nullable|string',
        ];
    }

    private function terapkanCakupanWaliKelas($query, Request $request)
    {
        $pengguna = $request->user();

        if ($pengguna?->membatasiCakupanWaliKelas()) {
            $query->whereIn('id', $pengguna->kelasWaliIds());
        }

        return $query;
    }

    private function pastikanBolehAksesKelas(Request $request, Kelas $kelas): void
    {
        abort_unless(
            $request->user()?->dapatMengaksesKelasSebagaiWali($kelas->id) ?? false,
            403,
        );
    }

    private function ambilTahunPelajaran()
    {
        return TahunPelajaran::query()
            ->orderByDesc('aktif')
            ->orderByDesc('nama')
            ->get();
    }

    private function ambilPegawai()
    {
        return Pegawai::query()
            ->where('aktif', true)
            ->orderBy('nama_lengkap')
            ->get(['id', 'nama_lengkap', 'jabatan_utama', 'jenis_pegawai']);
    }
}
