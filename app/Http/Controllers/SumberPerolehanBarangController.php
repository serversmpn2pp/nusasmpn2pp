<?php

namespace App\Http\Controllers;

use App\Models\SumberPerolehanBarang;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SumberPerolehanBarangController extends Controller
{
    public function index(Request $request)
    {
        $kataKunci = trim((string) $request->input('kata_kunci', ''));
        $status = $this->statusValid($request->input('status', 'semua'));

        $items = SumberPerolehanBarang::query()
            ->withCount(['unitBarang as barang_count'])
            ->when($status === 'aktif', fn ($query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('aktif', false))
            ->when($kataKunci !== '', function ($query) use ($kataKunci) {
                $query->where(function ($query) use ($kataKunci) {
                    $query->where('nama', 'ilike', '%'.$kataKunci.'%')
                        ->orWhere('kode', 'ilike', '%'.$kataKunci.'%')
                        ->orWhere('deskripsi', 'ilike', '%'.$kataKunci.'%');
                });
            })
            ->orderByDesc('aktif')
            ->orderBy('nama')
            ->paginate(12)
            ->withQueryString();

        return view('inventaris-master.index', array_merge($this->konfigurasiTampilan(), [
            'items' => $items,
            'kataKunci' => $kataKunci,
            'status' => $status,
            'jumlahTotal' => SumberPerolehanBarang::count(),
            'jumlahAktif' => SumberPerolehanBarang::where('aktif', true)->count(),
            'jumlahNonaktif' => SumberPerolehanBarang::where('aktif', false)->count(),
        ]));
    }

    public function create()
    {
        return view('inventaris-master.create', $this->konfigurasiTampilan());
    }

    public function store(Request $request)
    {
        $data = $this->rapikanData($request->validate($this->aturanValidasi()));
        $data['aktif'] = $request->boolean('aktif');

        $sumber = SumberPerolehanBarang::create($data);

        return redirect()
            ->route('sumber-perolehan-barang.show', $sumber)
            ->with('berhasil', 'Sumber perolehan berhasil ditambahkan.');
    }

    public function show(SumberPerolehanBarang $sumberPerolehanBarang)
    {
        $sumberPerolehanBarang->loadCount(['unitBarang as barang_count']);

        return view('inventaris-master.show', array_merge($this->konfigurasiTampilan(), [
            'item' => $sumberPerolehanBarang,
            'jumlahTerhubung' => $sumberPerolehanBarang->barang_count,
        ]));
    }

    public function edit(SumberPerolehanBarang $sumberPerolehanBarang)
    {
        return view('inventaris-master.edit', array_merge($this->konfigurasiTampilan(), [
            'item' => $sumberPerolehanBarang,
        ]));
    }

    public function update(Request $request, SumberPerolehanBarang $sumberPerolehanBarang)
    {
        $data = $this->rapikanData($request->validate($this->aturanValidasi($sumberPerolehanBarang)));
        $data['aktif'] = $request->boolean('aktif');
        $sumberPerolehanBarang->update($data);

        return redirect()
            ->route('sumber-perolehan-barang.show', $sumberPerolehanBarang)
            ->with('berhasil', 'Sumber perolehan berhasil diperbarui.');
    }

    public function destroy(SumberPerolehanBarang $sumberPerolehanBarang)
    {
        $sumberPerolehanBarang->update(['aktif' => false]);

        return redirect()
            ->route('sumber-perolehan-barang.index')
            ->with('berhasil', 'Sumber perolehan berhasil dinonaktifkan.');
    }

    private function aturanValidasi(?SumberPerolehanBarang $sumber = null): array
    {
        return [
            'nama' => ['required', 'string', 'max:120', Rule::unique('sumber_perolehan_barang', 'nama')->ignore($sumber)],
            'kode' => ['required', 'string', 'max:30', Rule::unique('sumber_perolehan_barang', 'kode')->ignore($sumber)],
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
        $data['deskripsi'] = filled($data['deskripsi'] ?? null) ? trim($data['deskripsi']) : null;

        return $data;
    }

    private function statusValid(mixed $status): string
    {
        return in_array($status, ['semua', 'aktif', 'nonaktif'], true) ? $status : 'semua';
    }

    private function konfigurasiTampilan(): array
    {
        return [
            'routePrefix' => 'sumber-perolehan-barang',
            'judul' => 'Sumber perolehan',
            'judulSingular' => 'sumber perolehan',
            'inisial' => 'SP',
            'labelJumlahTerhubung' => 'Unit aset',
            'teksPenggunaan' => 'Sumber perolehan digunakan pada penerimaan dan label aset, misalnya BOS, DAK, hibah, atau APBD.',
            'labelNama' => 'Nama sumber',
            'placeholderNama' => 'Contoh: DAK',
            'labelKode' => 'Kode sumber',
            'placeholderKode' => 'Contoh: DAK',
        ];
    }
}
