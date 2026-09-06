<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Barang;
use App\Services\Mobile\BarangMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class BarangController extends Controller
{
    public function index(Request $request, BarangMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'cari' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['semua', 'aktif', 'nonaktif'])],
            'jenis_barang' => ['nullable', Rule::in(['semua', ...array_keys(Barang::DAFTAR_JENIS_BARANG)])],
            'kategori_barang_id' => ['nullable', 'integer', 'exists:kategori_barang,id'],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return $this->tanpaCache([
            'data' => $service->daftar(
                $filter,
                $request->user()->memilikiIzin('barang.kelola'),
            ),
        ]);
    }

    public function show(Request $request, Barang $barang, BarangMobileService $service): JsonResponse
    {
        return $this->tanpaCache([
            'data' => [
                'barang' => $service->detail($barang),
                'pilihan' => $service->pilihanForm(),
                'hak_akses' => [
                    'dapat_kelola' => $request->user()->memilikiIzin('barang.kelola'),
                ],
            ],
        ]);
    }

    public function store(Request $request, BarangMobileService $service): JsonResponse
    {
        $jenis = (string) $request->input('jenis_barang');
        $request->merge(['kode' => $service->rapikanKodeBaku($request->input('kode'))]);
        $barang = $service->tambah($request->validate(
            $this->aturanValidasi(null, $jenis, $request->input('kode')),
            $this->pesanValidasi(),
        ));

        return $this->tanpaCache([
            'pesan' => 'Barang berhasil ditambahkan.',
            'data' => $service->detail($barang),
        ])->setStatusCode(201);
    }

    public function update(
        Request $request,
        Barang $barang,
        BarangMobileService $service,
    ): JsonResponse {
        $jenis = (string) $request->input('jenis_barang');
        $request->merge(['kode' => $service->rapikanKodeBaku($request->input('kode'))]);
        $service->ubah($barang, $request->validate(
            $this->aturanValidasi($barang, $jenis, $request->input('kode')),
            $this->pesanValidasi(),
        ));

        return $this->tanpaCache([
            'pesan' => 'Barang berhasil diperbarui.',
            'data' => $service->detail($barang->fresh()),
        ]);
    }

    public function destroy(Barang $barang, BarangMobileService $service): JsonResponse
    {
        $service->nonaktifkan($barang);

        return $this->tanpaCache([
            'pesan' => 'Barang berhasil dinonaktifkan. Seluruh unit, stok, dan riwayat tetap tersimpan.',
        ]);
    }

    private function aturanValidasi(?Barang $barang, string $jenis, mixed $kode): array
    {
        $aturanKode = [
            Rule::requiredIf($jenis === 'tidak_habis_pakai'),
            'nullable',
            'string',
            'max:50',
            Rule::unique('barang', 'kode')->ignore($barang),
        ];
        $kodeLamaTetap = $barang
            && $jenis === 'tidak_habis_pakai'
            && $kode === $barang->kodeKlasifikasi();
        if ($jenis === 'tidak_habis_pakai' && ! $kodeLamaTetap) {
            $aturanKode[] = 'regex:/^\d{2}(?:\.\d{2}){4}$/';
        }

        return [
            'kode' => $aturanKode,
            'nama' => ['required', 'string', 'max:150'],
            'kategori_barang_id' => ['required', 'integer', 'exists:kategori_barang,id'],
            'satuan_barang_id' => ['required', 'integer', 'exists:satuan_barang,id'],
            'lokasi_penyimpanan_id' => ['nullable', 'integer', 'exists:lokasi_barang,id'],
            'jenis_barang' => ['required', Rule::in(array_keys(Barang::DAFTAR_JENIS_BARANG))],
            'stok_minimum' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
            'deskripsi' => ['nullable', 'string', 'max:5000'],
            'aktif' => ['required', 'boolean'],
        ];
    }

    private function pesanValidasi(): array
    {
        return [
            'kode.required' => 'Kode barang wajib diisi untuk barang tidak habis pakai.',
            'kode.regex' => 'Kode barang harus terdiri dari lima kelompok dua angka, misalnya 02.06.01.05.40. Nomor unit tidak perlu diketik.',
        ];
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
