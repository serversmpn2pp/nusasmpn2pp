<?php

namespace App\Http\Controllers;

use App\Models\KategoriBarang;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KategoriBarangController extends Controller
{
    public function index(Request $request)
    {
        $kataKunci = trim((string) $request->input('kata_kunci', ''));
        $status = $this->statusValid($request->input('status', 'semua'));

        $kategoriBarang = KategoriBarang::query()
            ->withCount('barang')
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
            ->paginate(12)
            ->withQueryString();

        return view('inventaris-master.index', array_merge($this->konfigurasiTampilan(), [
            'kategoriBarang' => $kategoriBarang,
            'items' => $kategoriBarang,
            'kataKunci' => $kataKunci,
            'status' => $status,
            'jumlahTotal' => KategoriBarang::count(),
            'jumlahAktif' => KategoriBarang::where('aktif', true)->count(),
            'jumlahNonaktif' => KategoriBarang::where('aktif', false)->count(),
        ]));
    }

    public function create()
    {
        return view('inventaris-master.create', $this->konfigurasiTampilan());
    }

    public function store(Request $request)
    {
        $request->merge(['kode' => $this->rapikanKode($request->input('kode'))]);
        $data = $this->rapikanData($request->validate($this->aturanValidasi()));
        $data['aktif'] = $request->boolean('aktif');

        $kategoriBarang = KategoriBarang::create($data);

        return redirect()
            ->route('kategori-barang.show', $kategoriBarang)
            ->with('berhasil', 'Kategori barang berhasil ditambahkan.');
    }

    public function show(KategoriBarang $kategoriBarang)
    {
        $kategoriBarang->loadCount('barang');

        return view('inventaris-master.show', array_merge($this->konfigurasiTampilan(), [
            'item' => $kategoriBarang,
            'jumlahTerhubung' => $kategoriBarang->barang_count,
        ]));
    }

    public function edit(KategoriBarang $kategoriBarang)
    {
        return view('inventaris-master.edit', array_merge($this->konfigurasiTampilan(), [
            'item' => $kategoriBarang,
        ]));
    }

    public function update(Request $request, KategoriBarang $kategoriBarang)
    {
        $request->merge(['kode' => $this->rapikanKode($request->input('kode'))]);
        $data = $this->rapikanData($request->validate($this->aturanValidasi($kategoriBarang)));
        $data['aktif'] = $request->boolean('aktif');
        $kategoriBarang->update($data);

        return redirect()
            ->route('kategori-barang.show', $kategoriBarang)
            ->with('berhasil', 'Kategori barang berhasil diperbarui.');
    }

    public function destroy(KategoriBarang $kategoriBarang)
    {
        $kategoriBarang->update(['aktif' => false]);

        return redirect()
            ->route('kategori-barang.index')
            ->with('berhasil', 'Kategori barang berhasil dinonaktifkan.');
    }

    private function aturanValidasi(?KategoriBarang $kategoriBarang = null): array
    {
        return [
            'nama' => ['required', 'string', 'max:120', Rule::unique('kategori_barang', 'nama')->ignore($kategoriBarang)],
            'kode' => ['required', 'string', 'max:40', Rule::unique('kategori_barang', 'kode')->ignore($kategoriBarang)],
            'deskripsi' => ['nullable', 'string'],
            'aktif' => ['nullable', 'boolean'],
        ];
    }

    private function rapikanData(array $data): array
    {
        $data['nama'] = trim($data['nama']);
        $data['kode'] = $this->rapikanKode($data['kode']);
        $data['deskripsi'] = filled($data['deskripsi'] ?? null) ? trim($data['deskripsi']) : null;

        return $data;
    }

    private function rapikanKode(mixed $kode): string
    {
        return str((string) $kode)->trim()->upper()->replaceMatches('/[^A-Z0-9]+/', '_')->trim('_')->toString();
    }

    private function statusValid(mixed $status): string
    {
        return in_array($status, ['semua', 'aktif', 'nonaktif'], true) ? $status : 'semua';
    }

    private function konfigurasiTampilan(): array
    {
        return [
            'routePrefix' => 'kategori-barang',
            'judul' => 'Kategori barang',
            'judulSingular' => 'kategori barang',
            'inisial' => 'KB',
            'labelJumlahTerhubung' => 'Jenis barang',
            'teksPenggunaan' => 'Kategori membantu pengelompokan barang agar pencarian, pelaporan, dan pemeriksaan inventaris lebih mudah dilakukan.',
            'labelNama' => 'Nama kategori',
            'placeholderNama' => 'Contoh: Elektronik',
            'labelKode' => 'Kode kategori',
            'placeholderKode' => 'Contoh: ELEKTRONIK',
        ];
    }
}
