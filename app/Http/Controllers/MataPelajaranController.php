<?php

namespace App\Http\Controllers;

use App\Models\MataPelajaran;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MataPelajaranController extends Controller
{
    public function index(Request $request)
    {
        $kata_kunci = $request->kata_kunci;
        $status = $request->input('status', 'semua');
        $tingkat = $request->input('tingkat', 'semua');

        if (! in_array($status, ['semua', 'aktif', 'nonaktif'], true)) {
            $status = 'semua';
        }

        if (! in_array($tingkat, ['semua', '7', '8', '9'], true)) {
            $tingkat = 'semua';
        }

        $mataPelajaran = MataPelajaran::query()
            ->when($status === 'aktif', function ($query) {
                $query->where('aktif', true);
            })
            ->when($status === 'nonaktif', function ($query) {
                $query->where('aktif', false);
            })
            ->when($tingkat !== 'semua', function ($query) use ($tingkat) {
                $query->where('tingkat', (int) $tingkat);
            })
            ->when($kata_kunci, function ($query, $kata_kunci) {
                $query->where(function ($query) use ($kata_kunci) {
                    $query->where('nama', 'ilike', '%' . $kata_kunci . '%')
                        ->orWhere('kode', 'ilike', '%' . $kata_kunci . '%')
                        ->orWhere('kelompok', 'ilike', '%' . $kata_kunci . '%');
                });
            })
            ->orderByDesc('aktif')
            ->orderBy('urutan')
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString();

        $jumlahMataPelajaran = MataPelajaran::count();
        $jumlahAktif = MataPelajaran::where('aktif', true)->count();
        $jumlahNonaktif = MataPelajaran::where('aktif', false)->count();

        return view('mata-pelajaran.index', compact(
            'mataPelajaran',
            'kata_kunci',
            'status',
            'tingkat',
            'jumlahMataPelajaran',
            'jumlahAktif',
            'jumlahNonaktif',
        ));
    }

    public function create()
    {
        return view('mata-pelajaran.create');
    }

    public function store(Request $request)
    {
        $data = $this->rapikanData($request->validate($this->aturanValidasi()));
        $data['aktif'] = $request->boolean('aktif');

        $mataPelajaran = MataPelajaran::create($data);

        return redirect()
            ->route('mata-pelajaran.show', $mataPelajaran)
            ->with('berhasil', 'Mata pelajaran berhasil ditambahkan.');
    }

    public function show(MataPelajaran $mataPelajaran)
    {
        return view('mata-pelajaran.show', compact('mataPelajaran'));
    }

    public function edit(MataPelajaran $mataPelajaran)
    {
        return view('mata-pelajaran.edit', compact('mataPelajaran'));
    }

    public function update(Request $request, MataPelajaran $mataPelajaran)
    {
        $data = $this->rapikanData($request->validate($this->aturanValidasi($mataPelajaran)));
        $data['aktif'] = $request->boolean('aktif');

        $mataPelajaran->update($data);

        return redirect()
            ->route('mata-pelajaran.show', $mataPelajaran)
            ->with('berhasil', 'Mata pelajaran berhasil diperbarui.');
    }

    public function destroy(MataPelajaran $mataPelajaran)
    {
        $mataPelajaran->update(['aktif' => false]);

        return redirect()
            ->route('mata-pelajaran.index')
            ->with('berhasil', 'Mata pelajaran berhasil dinonaktifkan.');
    }

    private function aturanValidasi(?MataPelajaran $mataPelajaran = null): array
    {
        return [
            'kode' => [
                'nullable',
                'string',
                'max:30',
                Rule::unique('mata_pelajaran', 'kode')->ignore($mataPelajaran),
            ],
            'nama' => [
                'required',
                'string',
                'max:255',
                Rule::unique('mata_pelajaran', 'nama')->ignore($mataPelajaran),
            ],
            'kelompok' => 'nullable|string|max:50',
            'tingkat' => 'nullable|integer|min:7|max:9',
            'kkm' => 'nullable|integer|min:0|max:100',
            'urutan' => 'nullable|integer|min:0|max:999',
            'aktif' => 'nullable|boolean',
            'keterangan' => 'nullable|string',
        ];
    }

    private function rapikanData(array $data): array
    {
        $data['kode'] = filled($data['kode'] ?? null)
            ? mb_strtoupper(trim($data['kode']))
            : null;
        $data['nama'] = trim($data['nama']);
        $data['kelompok'] = filled($data['kelompok'] ?? null)
            ? trim($data['kelompok'])
            : null;
        $data['urutan'] = (int) ($data['urutan'] ?? 0);

        return $data;
    }
}
