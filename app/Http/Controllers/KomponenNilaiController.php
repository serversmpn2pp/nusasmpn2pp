<?php

namespace App\Http\Controllers;

use App\Models\GuruMataPelajaran;
use App\Models\KomponenNilai;
use App\Models\TahunPelajaran;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KomponenNilaiController extends Controller
{
    public function index(Request $request)
    {
        $kata_kunci = $request->kata_kunci;
        $status = $request->input('status', 'semua');
        $semester = $request->input('semester', 'semua');
        $jenis = $request->input('jenis_komponen', 'semua');
        $tahunPelajaranId = $request->input('tahun_pelajaran_id');

        if (! in_array($status, ['semua', 'aktif', 'nonaktif'], true)) {
            $status = 'semua';
        }

        if (! in_array($semester, ['semua', 'ganjil', 'genap'], true)) {
            $semester = 'semua';
        }

        if (! in_array($jenis, ['semua', 'formatif', 'sumatif', 'sts', 'sas_saj'], true)) {
            $jenis = 'semua';
        }

        $tahunPelajaran = TahunPelajaran::query()
            ->orderByDesc('aktif')
            ->orderByDesc('nama')
            ->get();

        $komponenNilai = $this->queryKomponenDalamCakupan($request)
            ->with([
                'guruMataPelajaran.tahunPelajaran',
                'guruMataPelajaran.kelas',
                'guruMataPelajaran.mataPelajaran',
                'guruMataPelajaran.pegawai',
            ])
            ->when($tahunPelajaranId, function ($query, $tahunPelajaranId) {
                $query->whereHas('guruMataPelajaran', function ($query) use ($tahunPelajaranId) {
                    $query->where('tahun_pelajaran_id', $tahunPelajaranId);
                });
            })
            ->when($status === 'aktif', function ($query) {
                $query->where('aktif', true);
            })
            ->when($status === 'nonaktif', function ($query) {
                $query->where('aktif', false);
            })
            ->when($semester !== 'semua', function ($query) use ($semester) {
                $query->where('semester', $semester);
            })
            ->when($jenis !== 'semua', function ($query) use ($jenis) {
                $query->where('jenis_komponen', $jenis);
            })
            ->when($kata_kunci, function ($query, $kata_kunci) {
                $query->where(function ($query) use ($kata_kunci) {
                    $query->where('nama', 'ilike', '%'.$kata_kunci.'%')
                        ->orWhereHas('guruMataPelajaran.pegawai', function ($query) use ($kata_kunci) {
                            $query->where('nama_lengkap', 'ilike', '%'.$kata_kunci.'%');
                        })
                        ->orWhereHas('guruMataPelajaran.mataPelajaran', function ($query) use ($kata_kunci) {
                            $query->where('nama', 'ilike', '%'.$kata_kunci.'%')
                                ->orWhere('kode', 'ilike', '%'.$kata_kunci.'%');
                        })
                        ->orWhereHas('guruMataPelajaran.kelas', function ($query) use ($kata_kunci) {
                            $query->where('nama', 'ilike', '%'.$kata_kunci.'%');
                        });
                });
            })
            ->orderByDesc('aktif')
            ->orderBy('semester')
            ->orderBy('jenis_komponen')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString();

        $jumlahKomponenNilai = $this->queryKomponenDalamCakupan($request)->count();
        $jumlahAktif = $this->queryKomponenDalamCakupan($request)->where('aktif', true)->count();
        $jumlahNonaktif = $this->queryKomponenDalamCakupan($request)->where('aktif', false)->count();

        return view('komponen-nilai.index', compact(
            'komponenNilai',
            'tahunPelajaran',
            'tahunPelajaranId',
            'kata_kunci',
            'status',
            'semester',
            'jenis',
            'jumlahKomponenNilai',
            'jumlahAktif',
            'jumlahNonaktif',
        ));
    }

    public function create(Request $request)
    {
        return view('komponen-nilai.create', $this->dataForm($request, [
            'guruMataPelajaranDipilih' => $request->input('guru_mata_pelajaran_id'),
        ]));
    }

    public function store(Request $request)
    {
        $data = $this->rapikanData($request->validate($this->aturanValidasi()));
        $data['aktif'] = $request->boolean('aktif');
        $this->pastikanBolehAksesGuruMataPelajaran($request, (int) $data['guru_mata_pelajaran_id']);
        $this->pastikanStsDanSasTunggal($data);

        $komponenNilai = KomponenNilai::create($data);

        return redirect()
            ->route('komponen-nilai.show', $komponenNilai)
            ->with('berhasil', 'Komponen nilai berhasil ditambahkan.');
    }

    public function show(Request $request, KomponenNilai $komponenNilai)
    {
        $this->pastikanBolehAksesKomponen($request, $komponenNilai);

        $komponenNilai->load([
            'guruMataPelajaran.tahunPelajaran',
            'guruMataPelajaran.kelas',
            'guruMataPelajaran.mataPelajaran.pengaturanTingkat',
            'guruMataPelajaran.pegawai',
        ]);

        return view('komponen-nilai.show', compact('komponenNilai'));
    }

    public function edit(Request $request, KomponenNilai $komponenNilai)
    {
        $this->pastikanBolehAksesKomponen($request, $komponenNilai);

        return view('komponen-nilai.edit', $this->dataForm($request, [
            'komponenNilai' => $komponenNilai,
            'guruMataPelajaranDipilih' => null,
        ]));
    }

    public function update(Request $request, KomponenNilai $komponenNilai)
    {
        $data = $this->rapikanData($request->validate($this->aturanValidasi($komponenNilai)));
        $data['aktif'] = $request->boolean('aktif');
        $this->pastikanBolehAksesKomponen($request, $komponenNilai);
        $this->pastikanBolehAksesGuruMataPelajaran($request, (int) $data['guru_mata_pelajaran_id']);
        $this->pastikanStsDanSasTunggal($data, $komponenNilai);

        $komponenNilai->update($data);

        return redirect()
            ->route('komponen-nilai.show', $komponenNilai)
            ->with('berhasil', 'Komponen nilai berhasil diperbarui.');
    }

    public function destroy(Request $request, KomponenNilai $komponenNilai)
    {
        $this->pastikanBolehAksesKomponen($request, $komponenNilai);

        $komponenNilai->update(['aktif' => false]);

        return redirect()
            ->route('komponen-nilai.index')
            ->with('berhasil', 'Komponen nilai berhasil dinonaktifkan.');
    }

    private function dataForm(Request $request, array $tambahan = []): array
    {
        return array_merge([
            'guruMataPelajaran' => $this->queryGuruMataPelajaranDalamCakupan($request)
                ->with(['tahunPelajaran', 'kelas', 'mataPelajaran', 'pegawai'])
                ->where('aktif', true)
                ->orderByDesc(
                    TahunPelajaran::select('aktif')
                        ->whereColumn('tahun_pelajaran.id', 'guru_mata_pelajaran.tahun_pelajaran_id')
                        ->limit(1)
                )
                ->orderByDesc(
                    TahunPelajaran::select('nama')
                        ->whereColumn('tahun_pelajaran.id', 'guru_mata_pelajaran.tahun_pelajaran_id')
                        ->limit(1)
                )
                ->get(),
        ], $tambahan);
    }

    private function aturanValidasi(?KomponenNilai $komponenNilai = null): array
    {
        return [
            'guru_mata_pelajaran_id' => 'required|exists:guru_mata_pelajaran,id',
            'semester' => 'required|in:ganjil,genap',
            'jenis_komponen' => 'required|in:formatif,sumatif,sts,sas_saj',
            'nama' => [
                'required',
                'string',
                'max:255',
                Rule::unique('komponen_nilai', 'nama')
                    ->where('guru_mata_pelajaran_id', request('guru_mata_pelajaran_id'))
                    ->where('semester', request('semester'))
                    ->where('jenis_komponen', request('jenis_komponen'))
                    ->ignore($komponenNilai),
            ],
            'tanggal_penilaian' => 'nullable|date',
            'urutan' => 'nullable|integer|min:0|max:999',
            'aktif' => 'nullable|boolean',
            'keterangan' => 'nullable|string',
        ];
    }

    private function rapikanData(array $data): array
    {
        $data['nama'] = trim($data['nama']);
        $data['urutan'] = (int) ($data['urutan'] ?? 0);

        return $data;
    }

    private function pastikanStsDanSasTunggal(array $data, ?KomponenNilai $komponenNilai = null): void
    {
        if (! $data['aktif'] || ! in_array($data['jenis_komponen'], ['sts', 'sas_saj'], true)) {
            return;
        }

        $sudahAda = KomponenNilai::query()
            ->where('guru_mata_pelajaran_id', $data['guru_mata_pelajaran_id'])
            ->where('semester', $data['semester'])
            ->where('jenis_komponen', $data['jenis_komponen'])
            ->where('aktif', true)
            ->when($komponenNilai, function ($query, $komponenNilai) {
                $query->whereKeyNot($komponenNilai->id);
            })
            ->exists();

        if ($sudahAda) {
            $label = $data['jenis_komponen'] === 'sts' ? 'STS' : 'SAS/SAJ';

            throw ValidationException::withMessages([
                'jenis_komponen' => $label.' hanya boleh dibuat satu kali untuk guru mapel dan semester yang sama.',
            ]);
        }
    }

    private function queryKomponenDalamCakupan(Request $request)
    {
        $query = KomponenNilai::query();

        if ($this->membatasiCakupanGuruMapel($request)) {
            $query->whereHas('guruMataPelajaran', function ($query) use ($request) {
                $query->where('pegawai_id', $request->user()->pegawai_id ?: 0);
            });
        }

        return $query;
    }

    private function queryGuruMataPelajaranDalamCakupan(Request $request)
    {
        $query = GuruMataPelajaran::query();

        if ($this->membatasiCakupanGuruMapel($request)) {
            $query->where('pegawai_id', $request->user()->pegawai_id ?: 0);
        }

        return $query;
    }

    private function pastikanBolehAksesKomponen(Request $request, KomponenNilai $komponenNilai): void
    {
        if (! $this->membatasiCakupanGuruMapel($request)) {
            return;
        }

        $komponenNilai->loadMissing('guruMataPelajaran:id,pegawai_id');

        abort_unless(
            (int) $komponenNilai->guruMataPelajaran?->pegawai_id === (int) $request->user()->pegawai_id,
            403,
        );
    }

    private function pastikanBolehAksesGuruMataPelajaran(Request $request, int $guruMataPelajaranId): void
    {
        if (! $this->membatasiCakupanGuruMapel($request)) {
            return;
        }

        abort_unless(
            GuruMataPelajaran::query()
                ->whereKey($guruMataPelajaranId)
                ->where('pegawai_id', $request->user()->pegawai_id ?: 0)
                ->exists(),
            403,
        );
    }

    private function membatasiCakupanGuruMapel(Request $request): bool
    {
        $pengguna = $request->user();

        if (! $pengguna || $pengguna->administrator()) {
            return false;
        }

        if (! $pengguna->memilikiPeran('guru_mapel')) {
            return false;
        }

        return ! $pengguna->memilikiPeran(['pimpinan', 'wakil_pimpinan_kurikulum']);
    }
}
