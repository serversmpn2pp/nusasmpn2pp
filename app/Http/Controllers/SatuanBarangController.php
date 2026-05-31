<?php

namespace App\Http\Controllers;

use App\Models\SatuanBarang;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SatuanBarangController extends Controller
{
    public function index(Request $request)
    {
        $kataKunci = trim((string) $request->input('kata_kunci', ''));
        $status = $this->statusValid($request->input('status', 'semua'));

        $satuanBarang = SatuanBarang::query()
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
            'satuanBarang' => $satuanBarang,
            'items' => $satuanBarang,
            'kataKunci' => $kataKunci,
            'status' => $status,
            'jumlahTotal' => SatuanBarang::count(),
            'jumlahAktif' => SatuanBarang::where('aktif', true)->count(),
            'jumlahNonaktif' => SatuanBarang::where('aktif', false)->count(),
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

        $satuanBarang = SatuanBarang::create($data);

        return redirect()
            ->route('satuan-barang.show', $satuanBarang)
            ->with('berhasil', 'Satuan barang berhasil ditambahkan.');
    }

    public function show(SatuanBarang $satuanBarang)
    {
        $satuanBarang->loadCount('barang');

        return view('inventaris-master.show', array_merge($this->konfigurasiTampilan(), [
            'item' => $satuanBarang,
            'jumlahTerhubung' => $satuanBarang->barang_count,
        ]));
    }

    public function edit(SatuanBarang $satuanBarang)
    {
        return view('inventaris-master.edit', array_merge($this->konfigurasiTampilan(), [
            'item' => $satuanBarang,
        ]));
    }

    public function update(Request $request, SatuanBarang $satuanBarang)
    {
        $request->merge(['kode' => $this->rapikanKode($request->input('kode'))]);
        $data = $this->rapikanData($request->validate($this->aturanValidasi($satuanBarang)));
        $data['aktif'] = $request->boolean('aktif');
        $satuanBarang->update($data);

        return redirect()
            ->route('satuan-barang.show', $satuanBarang)
            ->with('berhasil', 'Satuan barang berhasil diperbarui.');
    }

    public function destroy(SatuanBarang $satuanBarang)
    {
        $satuanBarang->update(['aktif' => false]);

        return redirect()
            ->route('satuan-barang.index')
            ->with('berhasil', 'Satuan barang berhasil dinonaktifkan.');
    }

    private function aturanValidasi(?SatuanBarang $satuanBarang = null): array
    {
        return [
            'nama' => ['required', 'string', 'max:80', Rule::unique('satuan_barang', 'nama')->ignore($satuanBarang)],
            'kode' => ['required', 'string', 'max:30', Rule::unique('satuan_barang', 'kode')->ignore($satuanBarang)],
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
            'routePrefix' => 'satuan-barang',
            'judul' => 'Satuan barang',
            'judulSingular' => 'satuan barang',
            'inisial' => 'SB',
            'labelJumlahTerhubung' => 'Jenis barang',
            'teksPenggunaan' => 'Satuan digunakan saat stok barang dicatat, dikeluarkan, dipinjamkan, dan dikembalikan.',
            'labelNama' => 'Nama satuan',
            'placeholderNama' => 'Contoh: Unit',
            'labelKode' => 'Kode satuan',
            'placeholderKode' => 'Contoh: UNIT',
        ];
    }
}
