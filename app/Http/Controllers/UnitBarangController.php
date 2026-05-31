<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\LokasiBarang;
use App\Models\UnitBarang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UnitBarangController extends Controller
{
    public function index(Request $request)
    {
        $kataKunci = trim((string) $request->input('kata_kunci', ''));
        $status = $this->pilihanValid($request->input('status', 'semua'), ['semua', 'aktif', 'nonaktif']);
        $kondisi = $this->pilihanValid($request->input('kondisi', 'semua'), array_merge(['semua'], array_keys(UnitBarang::DAFTAR_KONDISI)));
        $statusUnit = $this->pilihanValid($request->input('status_unit', 'semua'), array_merge(['semua'], array_keys(UnitBarang::DAFTAR_STATUS)));
        $barangId = $request->input('barang_id', 'semua');
        $lokasiBarangId = $request->input('lokasi_barang_id', 'semua');

        $unitBarang = UnitBarang::query()
            ->with(['barang.kategoriBarang', 'lokasiBarang'])
            ->when($status === 'aktif', fn ($query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('aktif', false))
            ->when($kondisi !== 'semua', fn ($query) => $query->where('kondisi', $kondisi))
            ->when($statusUnit !== 'semua', fn ($query) => $query->where('status_unit', $statusUnit))
            ->when($barangId !== 'semua', fn ($query) => $query->where('barang_id', $barangId))
            ->when($lokasiBarangId !== 'semua', fn ($query) => $query->where('lokasi_barang_id', $lokasiBarangId))
            ->when($kataKunci !== '', function ($query) use ($kataKunci) {
                $query->where(function ($query) use ($kataKunci) {
                    $query->where('kode_inventaris', 'ilike', '%' . $kataKunci . '%')
                        ->orWhere('nomor_seri', 'ilike', '%' . $kataKunci . '%')
                        ->orWhereHas('barang', fn ($query) => $query->where('nama', 'ilike', '%' . $kataKunci . '%'));
                });
            })
            ->orderByDesc('aktif')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        return view('unit-barang.index', [
            'unitBarang' => $unitBarang,
            'kataKunci' => $kataKunci,
            'status' => $status,
            'kondisi' => $kondisi,
            'statusUnit' => $statusUnit,
            'barangId' => $barangId,
            'lokasiBarangId' => $lokasiBarangId,
            'daftarKondisi' => UnitBarang::DAFTAR_KONDISI,
            'daftarStatusUnit' => UnitBarang::DAFTAR_STATUS,
            'daftarBarang' => $this->daftarBarang(),
            'daftarLokasi' => LokasiBarang::orderBy('nama')->get(),
            'jumlahUnit' => UnitBarang::count(),
            'jumlahAktif' => UnitBarang::where('aktif', true)->count(),
            'jumlahTersedia' => UnitBarang::where('aktif', true)->where('status_unit', 'tersedia')->count(),
            'jumlahPerluPerhatian' => UnitBarang::where('aktif', true)
                ->whereIn('status_unit', ['dalam_perbaikan', 'hilang'])
                ->count(),
        ]);
    }

    public function create(Request $request)
    {
        return view('unit-barang.create', array_merge(
            $this->pilihanForm(),
            ['barangTerpilihId' => $request->integer('barang_id') ?: null],
        ));
    }

    public function store(Request $request)
    {
        $data = $this->rapikanData(
            $request->validate($this->aturanValidasi(true)),
            $request->boolean('aktif'),
        );
        $jumlahUnit = (int) $data['jumlah_unit'];
        unset($data['jumlah_unit']);

        if ($jumlahUnit > 1 && filled($data['nomor_seri'])) {
            throw ValidationException::withMessages([
                'nomor_seri' => 'Nomor seri hanya dapat langsung diisi jika menambahkan satu unit. Tambahkan nomor seri masing-masing unit melalui halaman edit.',
            ]);
        }

        $unitPertama = DB::transaction(function () use ($data, $jumlahUnit) {
            $barang = Barang::query()->lockForUpdate()->findOrFail($data['barang_id']);
            $this->pastikanBarangAsetIndividual($barang);
            $nomorTerakhir = (int) UnitBarang::where('barang_id', $barang->id)->max('nomor_unit');
            $unitPertama = null;
            $data['lokasi_barang_id'] ??= $barang->lokasi_penyimpanan_id;

            for ($urutan = 1; $urutan <= $jumlahUnit; $urutan++) {
                $nomorUnit = $nomorTerakhir + $urutan;
                $unit = UnitBarang::create(array_merge($data, [
                    'nomor_unit' => $nomorUnit,
                    'kode_inventaris' => $this->buatKodeInventaris($barang, $nomorUnit),
                ]));
                $unitPertama ??= $unit;
            }

            return $unitPertama;
        });

        $pesan = $jumlahUnit === 1
            ? 'Unit aset berhasil ditambahkan.'
            : $jumlahUnit . ' unit aset berhasil ditambahkan.';

        return redirect()
            ->route('unit-barang.show', $unitPertama)
            ->with('berhasil', $pesan);
    }

    public function show(UnitBarang $unitBarang)
    {
        $unitBarang->load(['barang.kategoriBarang', 'barang.satuanBarang', 'lokasiBarang']);

        return view('unit-barang.show', compact('unitBarang'));
    }

    public function edit(UnitBarang $unitBarang)
    {
        return view('unit-barang.edit', array_merge(
            compact('unitBarang'),
            $this->pilihanForm(),
        ));
    }

    public function update(Request $request, UnitBarang $unitBarang)
    {
        $data = $this->rapikanData(
            $request->validate($this->aturanValidasi()),
            $request->boolean('aktif'),
        );
        unset($data['jumlah_unit']);
        $unitBarang->update($data);

        return redirect()
            ->route('unit-barang.show', $unitBarang)
            ->with('berhasil', 'Unit aset berhasil diperbarui.');
    }

    public function destroy(UnitBarang $unitBarang)
    {
        $unitBarang->update(['aktif' => false]);

        return redirect()
            ->route('unit-barang.index')
            ->with('berhasil', 'Unit aset berhasil dinonaktifkan.');
    }

    private function pilihanForm(): array
    {
        return [
            'daftarBarang' => $this->daftarBarang(aktifSaja: true),
            'daftarLokasi' => LokasiBarang::where('aktif', true)->orderBy('nama')->get(),
            'daftarKondisi' => UnitBarang::DAFTAR_KONDISI,
            'daftarStatusUnit' => UnitBarang::DAFTAR_STATUS,
        ];
    }

    private function daftarBarang(bool $aktifSaja = false)
    {
        return Barang::query()
            ->where('tipe_pengelolaan', 'aset_individual')
            ->when($aktifSaja, fn ($query) => $query->where('aktif', true))
            ->orderBy('nama')
            ->get();
    }

    private function aturanValidasi(bool $tambah = false): array
    {
        return [
            'barang_id' => [$tambah ? 'required' : 'sometimes', 'integer', 'exists:barang,id'],
            'jumlah_unit' => [$tambah ? 'required' : 'sometimes', 'integer', 'min:1', 'max:100'],
            'lokasi_barang_id' => ['nullable', 'integer', 'exists:lokasi_barang,id'],
            'nomor_seri' => ['nullable', 'string', 'max:120'],
            'kondisi' => ['required', Rule::in(array_keys(UnitBarang::DAFTAR_KONDISI))],
            'status_unit' => ['required', Rule::in(array_keys(UnitBarang::DAFTAR_STATUS))],
            'tanggal_perolehan' => ['nullable', 'date'],
            'sumber_perolehan' => ['nullable', 'string', 'max:120'],
            'harga_perolehan' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'keterangan' => ['nullable', 'string'],
            'aktif' => ['nullable', 'boolean'],
        ];
    }

    private function rapikanData(array $data, bool $aktif): array
    {
        $data['lokasi_barang_id'] = filled($data['lokasi_barang_id'] ?? null) ? $data['lokasi_barang_id'] : null;
        $data['nomor_seri'] = filled($data['nomor_seri'] ?? null) ? trim($data['nomor_seri']) : null;
        $data['sumber_perolehan'] = filled($data['sumber_perolehan'] ?? null) ? trim($data['sumber_perolehan']) : null;
        $data['harga_perolehan'] = filled($data['harga_perolehan'] ?? null) ? $data['harga_perolehan'] : null;
        $data['keterangan'] = filled($data['keterangan'] ?? null) ? trim($data['keterangan']) : null;
        $data['aktif'] = $aktif;

        return $data;
    }

    private function buatKodeInventaris(Barang $barang, int $nomorUnit): string
    {
        return 'INV-' . str($barang->kode)->replace('_', '-')->limit(48, '')->toString() . '-' . str_pad((string) $nomorUnit, 6, '0', STR_PAD_LEFT);
    }

    private function pastikanBarangAsetIndividual(Barang $barang): void
    {
        if ($barang->tipe_pengelolaan !== 'aset_individual') {
            throw ValidationException::withMessages([
                'barang_id' => 'Unit inventaris hanya dapat dibuat untuk barang dengan tipe aset individual.',
            ]);
        }
    }

    private function pilihanValid(mixed $nilai, array $daftar): string
    {
        return in_array($nilai, $daftar, true) ? $nilai : 'semua';
    }
}
