<?php

namespace App\Http\Controllers;

use App\Models\Barang;
use App\Models\KategoriBarang;
use App\Models\LokasiBarang;
use App\Models\SatuanBarang;
use App\Services\Inventaris\GeneratorIdentitasInventaris;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BarangController extends Controller
{
    public function __construct(private GeneratorIdentitasInventaris $generatorIdentitas) {}

    public function index(Request $request)
    {
        $kataKunci = trim((string) $request->input('kata_kunci', ''));
        $status = $this->statusValid($request->input('status', 'semua'));
        $jenisBarang = $request->input('jenis_barang', 'semua');
        $kategoriBarangId = $request->input('kategori_barang_id', 'semua');

        if (! array_key_exists($jenisBarang, Barang::DAFTAR_JENIS_BARANG) && $jenisBarang !== 'semua') {
            $jenisBarang = 'semua';
        }

        $barang = Barang::query()
            ->with(['kategoriBarang', 'satuanBarang', 'lokasiPenyimpanan'])
            ->withSum('saldoStokBarang', 'jumlah')
            ->when($status === 'aktif', fn ($query) => $query->where('aktif', true))
            ->when($status === 'nonaktif', fn ($query) => $query->where('aktif', false))
            ->when($jenisBarang !== 'semua', fn ($query) => $query->where('jenis_barang', $jenisBarang))
            ->when($kategoriBarangId !== 'semua', fn ($query) => $query->where('kategori_barang_id', $kategoriBarangId))
            ->when($kataKunci !== '', function ($query) use ($kataKunci) {
                $query->where(function ($query) use ($kataKunci) {
                    $query->where('nama', 'ilike', '%'.$kataKunci.'%')
                        ->orWhere('kode', 'ilike', '%'.$kataKunci.'%')
                        ->orWhere('deskripsi', 'ilike', '%'.$kataKunci.'%');
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
            'jenisBarang' => $jenisBarang,
            'kategoriBarangId' => $kategoriBarangId,
            'daftarJenisBarang' => Barang::DAFTAR_JENIS_BARANG,
            'daftarKategoriBarang' => KategoriBarang::orderBy('nama')->get(),
            'jumlahBarang' => Barang::count(),
            'jumlahAktif' => Barang::where('aktif', true)->count(),
            'jumlahTidakHabisPakai' => Barang::where('jenis_barang', 'tidak_habis_pakai')->count(),
            'jumlahHabisPakai' => Barang::where('jenis_barang', 'habis_pakai')->count(),
        ]);
    }

    public function create()
    {
        return view('barang.create', $this->pilihanForm());
    }

    public function store(Request $request)
    {
        $jenisBarang = (string) $request->input('jenis_barang');
        $request->merge(['kode' => $this->rapikanKodeBaku($request->input('kode'))]);
        $data = $this->rapikanData($request->validate(
            $this->aturanValidasi(null, $jenisBarang),
            $this->pesanValidasi(),
        ));
        $data['aktif'] = $request->boolean('aktif');

        $barang = DB::transaction(function () use ($data) {
            $data['kode'] = $data['jenis_barang'] === 'habis_pakai'
                ? $this->generatorIdentitas->buatKodeBarangHabisPakai()
                : $data['kode'];
            $data['tipe_pengelolaan'] = $data['jenis_barang'] === 'habis_pakai'
                ? 'habis_pakai'
                : 'aset_individual';

            if ($data['jenis_barang'] === 'tidak_habis_pakai') {
                $data['stok_minimum'] = 0;
            }

            return Barang::create($data);
        });

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
        $jenisBarang = (string) $request->input('jenis_barang');
        $request->merge(['kode' => $this->rapikanKodeBaku($request->input('kode'))]);
        $data = $this->rapikanData($request->validate(
            $this->aturanValidasi($barang, $jenisBarang),
            $this->pesanValidasi(),
        ));
        $data['aktif'] = $request->boolean('aktif');

        $jenisBerubah = $data['jenis_barang'] !== $barang->jenis_barang;
        $sudahDipakai = $barang->unitBarang()->exists()
            || $barang->saldoStokBarang()->exists()
            || $barang->mutasiStokBarang()->exists()
            || $barang->detailPeminjamanBarang()->exists();

        if ($jenisBerubah && $sudahDipakai) {
            throw ValidationException::withMessages([
                'jenis_barang' => 'Jenis barang tidak dapat diubah karena sudah memiliki unit, stok, atau riwayat transaksi.',
            ]);
        }

        DB::transaction(function () use ($barang, $data, $jenisBerubah) {
            if ($data['jenis_barang'] === 'habis_pakai') {
                $data['kode'] = $jenisBerubah
                    ? $this->generatorIdentitas->buatKodeBarangHabisPakai()
                    : $barang->kode;
                $data['tipe_pengelolaan'] = 'habis_pakai';
            } else {
                $data['tipe_pengelolaan'] = ! $jenisBerubah && $barang->tipe_pengelolaan === 'stok_dikembalikan'
                    ? 'stok_dikembalikan'
                    : 'aset_individual';
                $data['stok_minimum'] = 0;
            }

            $barang->update($data);
        });

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
            'daftarJenisBarang' => Barang::DAFTAR_JENIS_BARANG,
            'daftarKategoriBarang' => KategoriBarang::where('aktif', true)->orderBy('nama')->get(),
            'daftarSatuanBarang' => SatuanBarang::where('aktif', true)->orderBy('nama')->get(),
            'daftarLokasiBarang' => LokasiBarang::where('aktif', true)->orderBy('nama')->get(),
        ];
    }

    private function aturanValidasi(?Barang $barang, string $jenisBarang): array
    {
        $aturanKode = [
            Rule::requiredIf($jenisBarang === 'tidak_habis_pakai'),
            'nullable',
            'string',
            'max:50',
            Rule::unique('barang', 'kode')->ignore($barang),
        ];

        $kodeLamaTetap = $barang
            && $jenisBarang === 'tidak_habis_pakai'
            && $this->rapikanKodeBaku(request()->input('kode')) === $barang->kode;

        if ($jenisBarang === 'tidak_habis_pakai' && ! $kodeLamaTetap) {
            $aturanKode[] = 'regex:/^\d{2}(?:\.\d{2}){4}$/';
        }

        return [
            'kode' => $aturanKode,
            'nama' => ['required', 'string', 'max:150'],
            'kategori_barang_id' => ['required', 'exists:kategori_barang,id'],
            'satuan_barang_id' => ['required', 'exists:satuan_barang,id'],
            'lokasi_penyimpanan_id' => ['nullable', 'exists:lokasi_barang,id'],
            'jenis_barang' => ['required', Rule::in(array_keys(Barang::DAFTAR_JENIS_BARANG))],
            'stok_minimum' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'deskripsi' => ['nullable', 'string'],
            'aktif' => ['nullable', 'boolean'],
        ];
    }

    private function rapikanData(array $data): array
    {
        $data['kode'] = $this->rapikanKodeBaku($data['kode'] ?? null);
        $data['nama'] = trim($data['nama']);
        $data['lokasi_penyimpanan_id'] = filled($data['lokasi_penyimpanan_id'] ?? null)
            ? $data['lokasi_penyimpanan_id']
            : null;
        $data['stok_minimum'] = $data['stok_minimum'] ?? 0;
        $data['deskripsi'] = filled($data['deskripsi'] ?? null) ? trim($data['deskripsi']) : null;

        return $data;
    }

    private function pesanValidasi(): array
    {
        return [
            'kode.required' => 'Kode barang wajib diisi untuk barang tidak habis pakai.',
            'kode.regex' => 'Kode barang harus terdiri dari lima kelompok dua angka, misalnya 02.06.01.05.40. Nomor unit tidak perlu diketik.',
        ];
    }

    private function rapikanKodeBaku(mixed $kode): ?string
    {
        $kode = trim((string) $kode);

        $angka = preg_replace('/\D/', '', $kode);

        if (strlen($angka) === 10) {
            $kode = implode('.', str_split($angka, 2));
        }

        return $kode !== '' ? $kode : null;
    }

    private function statusValid(mixed $status): string
    {
        return in_array($status, ['semua', 'aktif', 'nonaktif'], true) ? $status : 'semua';
    }
}
