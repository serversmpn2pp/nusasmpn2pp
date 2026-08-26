<?php

namespace App\Http\Controllers;

use App\Models\SkemaBobotNilai;
use App\Models\TahunPelajaran;
use App\Services\Nilai\SkemaBobotNilaiService;
use Illuminate\Http\Request;

class SkemaBobotNilaiController extends Controller
{
    public function __construct(private SkemaBobotNilaiService $service) {}

    public function index(Request $request)
    {
        $status = $request->input('status', 'semua');
        $semester = $request->input('semester', 'semua');
        $tingkat = $request->input('tingkat', 'semua');
        $tahunPelajaranId = $request->input('tahun_pelajaran_id');

        if (! in_array($status, ['semua', 'aktif', 'nonaktif'], true)) {
            $status = 'semua';
        }

        if (! in_array($semester, ['semua', 'ganjil', 'genap'], true)) {
            $semester = 'semua';
        }

        if (! in_array($tingkat, ['semua', '7', '8', '9'], true)) {
            $tingkat = 'semua';
        }

        $tahunPelajaran = TahunPelajaran::query()
            ->orderByDesc('aktif')
            ->orderByDesc('nama')
            ->get();

        $skemaBobotNilai = SkemaBobotNilai::query()
            ->with('tahunPelajaran')
            ->when($tahunPelajaranId, function ($query, $tahunPelajaranId) {
                $query->where('tahun_pelajaran_id', $tahunPelajaranId);
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
            ->when($tingkat !== 'semua', function ($query) use ($tingkat) {
                $query->where('tingkat', (int) $tingkat);
            })
            ->orderByDesc(
                TahunPelajaran::select('aktif')
                    ->whereColumn('tahun_pelajaran.id', 'skema_bobot_nilai.tahun_pelajaran_id')
                    ->limit(1)
            )
            ->orderByDesc(
                TahunPelajaran::select('nama')
                    ->whereColumn('tahun_pelajaran.id', 'skema_bobot_nilai.tahun_pelajaran_id')
                    ->limit(1)
            )
            ->orderBy('semester')
            ->orderByRaw('COALESCE(tingkat, 0)')
            ->paginate(10)
            ->withQueryString();

        $jumlahSkemaBobotNilai = SkemaBobotNilai::count();
        $jumlahAktif = SkemaBobotNilai::where('aktif', true)->count();
        $jumlahNonaktif = SkemaBobotNilai::where('aktif', false)->count();

        return view('skema-bobot-nilai.index', compact(
            'skemaBobotNilai',
            'tahunPelajaran',
            'tahunPelajaranId',
            'status',
            'semester',
            'tingkat',
            'jumlahSkemaBobotNilai',
            'jumlahAktif',
            'jumlahNonaktif',
        ));
    }

    public function create(Request $request)
    {
        return view('skema-bobot-nilai.create', [
            'tahunPelajaran' => $this->ambilTahunPelajaran(),
            'tahunPelajaranDipilih' => $request->input('tahun_pelajaran_id'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->aturanValidasi());
        $data['aktif'] = $request->boolean('aktif');
        $skemaBobotNilai = $this->service->tambah($data);

        return redirect()
            ->route('skema-bobot-nilai.show', $skemaBobotNilai)
            ->with('berhasil', 'Skema bobot nilai berhasil ditambahkan.');
    }

    public function show(SkemaBobotNilai $skemaBobotNilai)
    {
        $skemaBobotNilai->load('tahunPelajaran');

        return view('skema-bobot-nilai.show', compact('skemaBobotNilai'));
    }

    public function edit(SkemaBobotNilai $skemaBobotNilai)
    {
        return view('skema-bobot-nilai.edit', [
            'skemaBobotNilai' => $skemaBobotNilai,
            'tahunPelajaran' => $this->ambilTahunPelajaran(),
            'tahunPelajaranDipilih' => null,
        ]);
    }

    public function update(Request $request, SkemaBobotNilai $skemaBobotNilai)
    {
        $data = $request->validate($this->aturanValidasi());
        $data['aktif'] = $request->boolean('aktif');
        $this->service->ubah($skemaBobotNilai, $data);

        return redirect()
            ->route('skema-bobot-nilai.show', $skemaBobotNilai)
            ->with('berhasil', 'Skema bobot nilai berhasil diperbarui.');
    }

    public function destroy(SkemaBobotNilai $skemaBobotNilai)
    {
        $this->service->nonaktifkan($skemaBobotNilai);

        return redirect()
            ->route('skema-bobot-nilai.index')
            ->with('berhasil', 'Skema bobot nilai berhasil dinonaktifkan.');
    }

    private function aturanValidasi(): array
    {
        return [
            'tahun_pelajaran_id' => 'required|exists:tahun_pelajaran,id',
            'semester' => 'required|in:ganjil,genap',
            'tingkat' => 'nullable|integer|min:7|max:9',
            'bobot_formatif' => 'required|integer|min:0|max:100',
            'bobot_sumatif' => 'required|integer|min:0|max:100',
            'bobot_sts' => 'required|integer|min:0|max:100',
            'bobot_sas_saj' => 'required|integer|min:0|max:100',
            'aktif' => 'nullable|boolean',
            'keterangan' => 'nullable|string',
        ];
    }

    private function ambilTahunPelajaran()
    {
        return TahunPelajaran::query()
            ->orderByDesc('aktif')
            ->orderByDesc('nama')
            ->get();
    }
}
