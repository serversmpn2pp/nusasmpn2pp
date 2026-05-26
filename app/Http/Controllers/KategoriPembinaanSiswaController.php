<?php

namespace App\Http\Controllers;

use App\Models\KategoriPembinaanSiswa;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KategoriPembinaanSiswaController extends Controller
{
    public function index(Request $request)
    {
        $kataKunci = trim((string) $request->input('kata_kunci', ''));
        $status = $request->input('status', 'semua');

        if (! in_array($status, ['semua', 'aktif', 'nonaktif'], true)) {
            $status = 'semua';
        }

        $kategoriPembinaanSiswa = KategoriPembinaanSiswa::query()
            ->when($status === 'aktif', fn ($query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('aktif', false))
            ->when($kataKunci !== '', function ($query) use ($kataKunci) {
                $query->where(function ($query) use ($kataKunci) {
                    $query->where('nama', 'ilike', '%' . $kataKunci . '%')
                        ->orWhere('kode', 'ilike', '%' . $kataKunci . '%')
                        ->orWhere('deskripsi', 'ilike', '%' . $kataKunci . '%');
                });
            })
            ->orderByDesc('aktif')
            ->orderBy('nama')
            ->paginate(10)
            ->withQueryString();

        $jumlahKategori = KategoriPembinaanSiswa::count();
        $jumlahAktif = KategoriPembinaanSiswa::where('aktif', true)->count();
        $jumlahNonaktif = KategoriPembinaanSiswa::where('aktif', false)->count();

        return view('kategori-pembinaan-siswa.index', compact(
            'kategoriPembinaanSiswa',
            'kataKunci',
            'status',
            'jumlahKategori',
            'jumlahAktif',
            'jumlahNonaktif',
        ));
    }

    public function create()
    {
        return view('kategori-pembinaan-siswa.create');
    }

    public function store(Request $request)
    {
        $data = $this->rapikanData($request->validate($this->aturanValidasi()));
        $data['aktif'] = $request->boolean('aktif');

        $kategoriPembinaanSiswa = KategoriPembinaanSiswa::create($data);

        return redirect()
            ->route('kategori-pembinaan-siswa.show', $kategoriPembinaanSiswa)
            ->with('berhasil', 'Kategori pembinaan siswa berhasil ditambahkan.');
    }

    public function show(KategoriPembinaanSiswa $kategoriPembinaanSiswa)
    {
        return view('kategori-pembinaan-siswa.show', compact('kategoriPembinaanSiswa'));
    }

    public function edit(KategoriPembinaanSiswa $kategoriPembinaanSiswa)
    {
        return view('kategori-pembinaan-siswa.edit', compact('kategoriPembinaanSiswa'));
    }

    public function update(Request $request, KategoriPembinaanSiswa $kategoriPembinaanSiswa)
    {
        $data = $this->rapikanData($request->validate($this->aturanValidasi($kategoriPembinaanSiswa)));
        $data['aktif'] = $request->boolean('aktif');

        $kategoriPembinaanSiswa->update($data);

        return redirect()
            ->route('kategori-pembinaan-siswa.show', $kategoriPembinaanSiswa)
            ->with('berhasil', 'Kategori pembinaan siswa berhasil diperbarui.');
    }

    public function destroy(KategoriPembinaanSiswa $kategoriPembinaanSiswa)
    {
        $kategoriPembinaanSiswa->update(['aktif' => false]);

        return redirect()
            ->route('kategori-pembinaan-siswa.index')
            ->with('berhasil', 'Kategori pembinaan siswa berhasil dinonaktifkan.');
    }

    private function aturanValidasi(?KategoriPembinaanSiswa $kategoriPembinaanSiswa = null): array
    {
        return [
            'nama' => [
                'required',
                'string',
                'max:120',
                Rule::unique('kategori_pembinaan_siswa', 'nama')->ignore($kategoriPembinaanSiswa),
            ],
            'kode' => [
                'required',
                'string',
                'max:40',
                Rule::unique('kategori_pembinaan_siswa', 'kode')->ignore($kategoriPembinaanSiswa),
            ],
            'deskripsi' => ['nullable', 'string'],
            'aktif' => ['nullable', 'boolean'],
        ];
    }

    private function rapikanData(array $data): array
    {
        $data['nama'] = trim($data['nama']);
        $data['kode'] = str((string) $data['kode'])
            ->trim()
            ->upper()
            ->replaceMatches('/[^A-Z0-9]+/', '_')
            ->trim('_')
            ->toString();
        $data['deskripsi'] = filled($data['deskripsi'] ?? null)
            ? trim($data['deskripsi'])
            : null;

        return $data;
    }
}
