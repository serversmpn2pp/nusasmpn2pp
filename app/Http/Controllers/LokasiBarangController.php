<?php

namespace App\Http\Controllers;

use App\Models\LokasiBarang;
use App\Models\Pegawai;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LokasiBarangController extends Controller
{
    public function index(Request $request)
    {
        $kataKunci = trim((string) $request->input('kata_kunci', ''));
        $status = $this->statusValid($request->input('status', 'semua'));
        $jenis = $request->input('jenis', 'semua');

        if (! array_key_exists($jenis, LokasiBarang::DAFTAR_JENIS) && $jenis !== 'semua') {
            $jenis = 'semua';
        }

        $lokasiBarang = LokasiBarang::query()
            ->with('penanggungJawab')
            ->withCount('barangSebagaiPenyimpanan')
            ->when($status === 'aktif', fn ($query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('aktif', false))
            ->when($jenis !== 'semua', fn ($query) => $query->where('jenis', $jenis))
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

        return view('lokasi-barang.index', [
            'lokasiBarang' => $lokasiBarang,
            'kataKunci' => $kataKunci,
            'status' => $status,
            'jenis' => $jenis,
            'daftarJenis' => LokasiBarang::DAFTAR_JENIS,
            'jumlahLokasi' => LokasiBarang::count(),
            'jumlahAktif' => LokasiBarang::where('aktif', true)->count(),
            'jumlahDenganPenanggungJawab' => LokasiBarang::whereNotNull('penanggung_jawab_pegawai_id')->count(),
        ]);
    }

    public function create()
    {
        return view('lokasi-barang.create', $this->pilihanForm());
    }

    public function store(Request $request)
    {
        $request->merge(['kode' => $this->rapikanKode($request->input('kode'))]);
        $data = $this->rapikanData($request->validate($this->aturanValidasi()));
        $data['aktif'] = $request->boolean('aktif');

        $lokasiBarang = LokasiBarang::create($data);

        return redirect()
            ->route('lokasi-barang.show', $lokasiBarang)
            ->with('berhasil', 'Lokasi barang berhasil ditambahkan.');
    }

    public function show(LokasiBarang $lokasiBarang)
    {
        $lokasiBarang->load('penanggungJawab')->loadCount('barangSebagaiPenyimpanan');

        return view('lokasi-barang.show', compact('lokasiBarang'));
    }

    public function edit(LokasiBarang $lokasiBarang)
    {
        return view('lokasi-barang.edit', array_merge(
            compact('lokasiBarang'),
            $this->pilihanForm(),
        ));
    }

    public function update(Request $request, LokasiBarang $lokasiBarang)
    {
        $request->merge(['kode' => $this->rapikanKode($request->input('kode'))]);
        $data = $this->rapikanData($request->validate($this->aturanValidasi($lokasiBarang)));
        $data['aktif'] = $request->boolean('aktif');
        $lokasiBarang->update($data);

        return redirect()
            ->route('lokasi-barang.show', $lokasiBarang)
            ->with('berhasil', 'Lokasi barang berhasil diperbarui.');
    }

    public function destroy(LokasiBarang $lokasiBarang)
    {
        $lokasiBarang->update(['aktif' => false]);

        return redirect()
            ->route('lokasi-barang.index')
            ->with('berhasil', 'Lokasi barang berhasil dinonaktifkan.');
    }

    private function pilihanForm(): array
    {
        return [
            'daftarJenis' => LokasiBarang::DAFTAR_JENIS,
            'daftarPegawai' => Pegawai::where('aktif', true)->orderBy('nama_lengkap')->get(),
        ];
    }

    private function aturanValidasi(?LokasiBarang $lokasiBarang = null): array
    {
        return [
            'nama' => ['required', 'string', 'max:120', Rule::unique('lokasi_barang', 'nama')->ignore($lokasiBarang)],
            'kode' => ['required', 'string', 'max:40', Rule::unique('lokasi_barang', 'kode')->ignore($lokasiBarang)],
            'jenis' => ['required', Rule::in(array_keys(LokasiBarang::DAFTAR_JENIS))],
            'penanggung_jawab_pegawai_id' => ['nullable', 'exists:pegawai,id'],
            'deskripsi' => ['nullable', 'string'],
            'aktif' => ['nullable', 'boolean'],
        ];
    }

    private function rapikanData(array $data): array
    {
        $data['nama'] = trim($data['nama']);
        $data['kode'] = $this->rapikanKode($data['kode']);
        $data['penanggung_jawab_pegawai_id'] = filled($data['penanggung_jawab_pegawai_id'] ?? null)
            ? $data['penanggung_jawab_pegawai_id']
            : null;
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
