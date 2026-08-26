<?php

namespace App\Http\Controllers;

use App\Models\KomponenNilai;
use App\Models\TahunPelajaran;
use App\Services\Nilai\KomponenNilaiService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KomponenNilaiController extends Controller
{
    public function __construct(private KomponenNilaiService $service) {}

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

        $komponenNilai = $this->service->queryKomponenDalamCakupan($request->user())
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

        $jumlahKomponenNilai = $this->service->queryKomponenDalamCakupan($request->user())->count();
        $jumlahAktif = $this->service->queryKomponenDalamCakupan($request->user())->where('aktif', true)->count();
        $jumlahNonaktif = $this->service->queryKomponenDalamCakupan($request->user())->where('aktif', false)->count();

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
        $data = $request->validate($this->aturanValidasi());
        $data['aktif'] = $request->boolean('aktif');
        $komponenNilai = $this->service->tambah($request->user(), $data);

        return redirect()
            ->route('komponen-nilai.show', $komponenNilai)
            ->with('berhasil', 'Komponen nilai berhasil ditambahkan.');
    }

    public function show(Request $request, KomponenNilai $komponenNilai)
    {
        $this->service->pastikanBolehAksesKomponen($request->user(), $komponenNilai);

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
        $this->service->pastikanBolehAksesKomponen($request->user(), $komponenNilai);

        return view('komponen-nilai.edit', $this->dataForm($request, [
            'komponenNilai' => $komponenNilai,
            'guruMataPelajaranDipilih' => null,
        ]));
    }

    public function update(Request $request, KomponenNilai $komponenNilai)
    {
        $data = $request->validate($this->aturanValidasi($komponenNilai));
        $data['aktif'] = $request->boolean('aktif');
        $this->service->ubah($request->user(), $komponenNilai, $data);

        return redirect()
            ->route('komponen-nilai.show', $komponenNilai)
            ->with('berhasil', 'Komponen nilai berhasil diperbarui.');
    }

    public function destroy(Request $request, KomponenNilai $komponenNilai)
    {
        $this->service->nonaktifkan($request->user(), $komponenNilai);

        return redirect()
            ->route('komponen-nilai.index')
            ->with('berhasil', 'Komponen nilai berhasil dinonaktifkan.');
    }

    private function dataForm(Request $request, array $tambahan = []): array
    {
        return array_merge([
            'guruMataPelajaran' => $this->service
                ->queryGuruMataPelajaranDalamCakupan($request->user())
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
}
