<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\LokasiBarang;
use App\Models\SatuanBarang;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BarangController extends Controller
{
    public function index(Request $request)
    {
        $kataKunci = trim((string) $request->input('kata_kunci', ''));
        $status = $this->statusValid($request->input('status', 'semua'));
        $tipePengelolaan = $request->input('tipe_pengelolaan', 'semua');
        $kategoriBarangId = $request->input('kategori_barang_id', 'semua');

        if (! array_key_exists($tipePengelolaan, Barang::DAFTAR_TIPE_PENGELOLAAN) && $tipePengelolaan !== 'semua') {
            $tipePengelolaan = 'semua';
        }

        $barang = Barang::query()
            ->with(['kategoriBarang', 'satuanBarang', 'lokasiPenyimpanan'])
            ->withSum('saldoStokBarang', 'jumlah')
            ->when($status === 'aktif', fn ($query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('aktif', false))
            ->when($tipePengelolaan !== 'semua', fn ($query) => $query->where('tipe_pengelolaan', $tipePengelolaan))
            ->when($kategoriBarangId !== 'semua', fn ($query) => $query->where('kategori_barang_id', $kategoriBarangId))
            ->when($kataKunci !== '', function ($query) use ($kataKunci) {
                $query->where(function ($query) use ($kataKunci) {
                    $query->where('nama', 'ilike', '%' . $kataKunci . '%')
                        ->orWhere('kode', 'ilike', '%' . $kataKunci . '%')
                        ->orWhere('deskripsi', 'ilike', '%' . $kataKunci . '%');
                });
            })
            ->orderByDesc('aktif')
            ->orderBy('nama')
            ->paginate(15)
            ->withQueryString();

        return view('barang.index', [
            'barang' => $barang,
            'kataKunci' => $kataKunci,
            'status' => $status,
            'tipePengelolaan' => $tipePengelolaan,
            'kategoriBarangId' => $kategoriBarangId,
            'daftarTipePengelolaan' => Barang::DAFTAR_TIPE_PENGELOLAAN,
            'daftarKategoriBarang' => KategoriBarang::orderBy('nama')->get(),
            'jumlahBarang' => Barang::count(),
            'jumlahAktif' => Barang::where('aktif', true)->count(),
            'jumlahAsetIndividual' => Barang::where('tipe_pengelolaan', 'aset_individual')->count(),
            'jumlahHabisPakai' => Barang::where('tipe_pengelolaan', 'habis_pakai')->count(),
        ]);
    }

    public function create()
    {
        return view('barang.create', $this->pilihanForm());
    }

    public function store(Request $request)
    {
        $request->merge(['kode' => $this->rapikanKode($request->input('kode'))]);
        $data = $this->rapikanData($request->validate($this->aturanValidasi()));
        $data['aktif'] = $request->boolean('aktif');

        $barang = Barang::create($data);

        return redirect()
            ->route('barang.show', $barang)
            ->with('berhasil', 'Barang berhasil ditambahkan.');
    }

    public function show(Barang $barang)
    {
        $barang->load(['kategoriBarang', 'satuanBarang', 'lokasiPenyimpanan'])
            ->loadCount('unitBarang')
            ->loadSum('saldoStokBarang', 'jumlah');

        return view('barang.show', compact('barang'));
    }

    public function edit(Barang $barang)
    {
        return view('barang.edit', array_merge(
            compact('barang'),
            $this->pilihanForm(),
        ));
    }

    public function update(Request $request, Barang $barang)
    {
        $request->merge(['kode' => $this->rapikanKode($request->input('kode'))]);
        $data = $this->rapikanData($request->validate($this->aturanValidasi($barang)));
        $data['aktif'] = $request->boolean('aktif');

        if ($barang->unitBarang()->exists() && $data['tipe_pengelolaan'] !== 'aset_individual') {
            throw ValidationException::withMessages([
                'tipe_pengelolaan' => 'Tipe pengelolaan tidak dapat diubah karena barang ini sudah memiliki unit aset individual.',
            ]);
        }

        $barang->update($data);

        return redirect()
            ->route('barang.show', $barang)
            ->with('berhasil', 'Barang berhasil diperbarui.');
    }

    public function destroy(Barang $barang)
    {
        $barang->update(['aktif' => false]);

        return redirect()
            ->route('barang.index')
            ->with('berhasil', 'Barang berhasil dinonaktifkan.');
    }

    private function pilihanForm(): array
    {
        return [
            'daftarTipePengelolaan' => Barang::DAFTAR_TIPE_PENGELOLAAN,
            'daftarKategoriBarang' => KategoriBarang::where('aktif', true)->orderBy('nama')->get(),
            'daftarSatuanBarang' => SatuanBarang::where('aktif', true)->orderBy('nama')->get(),
            'daftarLokasiBarang' => LokasiBarang::where('aktif', true)->orderBy('nama')->get(),
        ];
    }

    private function aturanValidasi(?Barang $barang = null): array
    {
        return [
            'kode' => ['required', 'string', 'max:50', Rule::unique('barang', 'kode')->ignore($barang)],
            'nama' => ['required', 'string', 'max:150'],
            'kategori_barang_id' => ['required', 'exists:kategori_barang,id'],
            'satuan_barang_id' => ['required', 'exists:satuan_barang,id'],
            'lokasi_penyimpanan_id' => ['nullable', 'exists:lokasi_barang,id'],
            'tipe_pengelolaan' => ['required', Rule::in(array_keys(Barang::DAFTAR_TIPE_PENGELOLAAN))],
            'stok_minimum' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'deskripsi' => ['nullable', 'string'],
            'aktif' => ['nullable', 'boolean'],
        ];
    }

    private function rapikanData(array $data): array
    {
        $data['kode'] = $this->rapikanKode($data['kode']);
        $data['nama'] = trim($data['nama']);
        $data['lokasi_penyimpanan_id'] = filled($data['lokasi_penyimpanan_id'] ?? null)
            ? $data['lokasi_penyimpanan_id']
            : null;
        $data['stok_minimum'] = $data['stok_minimum'] ?? 0;
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
}
