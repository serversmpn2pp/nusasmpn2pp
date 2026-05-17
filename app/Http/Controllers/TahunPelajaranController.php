<?php

namespace App\Http\Controllers;

use App\Models\TahunPelajaran;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TahunPelajaranController extends Controller
{
    public function index(Request $request)
    {
        $kata_kunci = $request->kata_kunci;
        $status = $request->input('status', 'semua');

        if (! in_array($status, ['semua', 'aktif', 'nonaktif'], true)) {
            $status = 'semua';
        }

        $tahunPelajaran = TahunPelajaran::query()
            ->withCount('kelas')
            ->when($status === 'aktif', function ($query) {
                $query->where('aktif', true);
            })
            ->when($status === 'nonaktif', function ($query) {
                $query->where('aktif', false);
            })
            ->when($kata_kunci, function ($query, $kata_kunci) {
                $query->where('nama', 'ilike', '%' . $kata_kunci . '%');
            })
            ->orderByDesc('aktif')
            ->orderByDesc('nama')
            ->paginate(10)
            ->withQueryString();

        $jumlahTahunPelajaran = TahunPelajaran::count();
        $jumlahAktif = TahunPelajaran::where('aktif', true)->count();
        $jumlahNonaktif = TahunPelajaran::where('aktif', false)->count();

        return view('tahun-pelajaran.index', compact(
            'tahunPelajaran',
            'kata_kunci',
            'status',
            'jumlahTahunPelajaran',
            'jumlahAktif',
            'jumlahNonaktif',
        ));
    }

    public function create()
    {
        return view('tahun-pelajaran.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->aturanValidasi());
        $data['aktif'] = $request->boolean('aktif');

        if ($data['aktif']) {
            TahunPelajaran::query()->update(['aktif' => false]);
        }

        TahunPelajaran::create($data);

        return redirect()
            ->route('tahun-pelajaran.index')
            ->with('berhasil', 'Tahun pelajaran berhasil ditambahkan.');
    }

    public function show(TahunPelajaran $tahunPelajaran)
    {
        $tahunPelajaran->loadCount('kelas');
        $kelas = $tahunPelajaran->kelas()
            ->with('waliKelas')
            ->withCount('anggotaKelas')
            ->orderBy('tingkat')
            ->orderBy('nama')
            ->get();

        return view('tahun-pelajaran.show', compact('tahunPelajaran', 'kelas'));
    }

    public function edit(TahunPelajaran $tahunPelajaran)
    {
        return view('tahun-pelajaran.edit', compact('tahunPelajaran'));
    }

    public function update(Request $request, TahunPelajaran $tahunPelajaran)
    {
        $data = $request->validate($this->aturanValidasi($tahunPelajaran));
        $data['aktif'] = $request->boolean('aktif');

        if ($data['aktif']) {
            TahunPelajaran::query()
                ->whereKeyNot($tahunPelajaran->id)
                ->update(['aktif' => false]);
        }

        $tahunPelajaran->update($data);

        return redirect()
            ->route('tahun-pelajaran.show', $tahunPelajaran)
            ->with('berhasil', 'Tahun pelajaran berhasil diperbarui.');
    }

    public function destroy(TahunPelajaran $tahunPelajaran)
    {
        $tahunPelajaran->update(['aktif' => false]);

        return redirect()
            ->route('tahun-pelajaran.index')
            ->with('berhasil', 'Tahun pelajaran berhasil dinonaktifkan.');
    }

    private function aturanValidasi(?TahunPelajaran $tahunPelajaran = null): array
    {
        return [
            'nama' => [
                'required',
                'string',
                'max:50',
                Rule::unique('tahun_pelajaran', 'nama')->ignore($tahunPelajaran),
            ],
            'tanggal_mulai' => 'nullable|date',
            'tanggal_selesai' => 'nullable|date|after_or_equal:tanggal_mulai',
            'aktif' => 'nullable|boolean',
            'keterangan' => 'nullable|string',
        ];
    }
}
