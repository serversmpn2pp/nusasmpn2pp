<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PenerimaanBarang;
use App\Models\UnitBarang;
use App\Services\Inventaris\BatalkanPenerimaanBarang;
use App\Services\Inventaris\ProsesPenerimaanBarang;
use App\Services\Mobile\PenerimaanBarangMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PenerimaanBarangController extends Controller
{
    public function index(Request $request, PenerimaanBarangMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'cari' => ['nullable', 'string', 'max:160'],
            'sumber_perolehan_barang_id' => ['nullable', 'integer', 'exists:sumber_perolehan_barang,id'],
            'tanggal_mulai' => ['nullable', 'date_format:Y-m-d'],
            'tanggal_selesai' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return $this->tanpaCache([
            'data' => $service->daftar($filter, $request->user()->memilikiIzin('barang.kelola')),
        ]);
    }

    public function show(
        Request $request,
        PenerimaanBarang $penerimaanBarang,
        PenerimaanBarangMobileService $service,
    ): JsonResponse {
        return $this->tanpaCache([
            'data' => [
                'penerimaan' => $service->detail($penerimaanBarang),
                'hak_akses' => [
                    'dapat_kelola' => $request->user()->memilikiIzin('barang.kelola'),
                    'dapat_dibatalkan' => $request->user()->memilikiIzin('barang.kelola')
                        && ! $penerimaanBarang->sudahDibatalkan(),
                ],
            ],
        ]);
    }

    public function store(
        Request $request,
        ProsesPenerimaanBarang $proses,
        PenerimaanBarangMobileService $service,
    ): JsonResponse {
        $data = $this->rapikanData($request->validate($this->aturanPenerimaan(), $this->pesanValidasi()));
        $penerimaan = $proses->catat($data, $request->user()?->id);

        return $this->tanpaCache([
            'pesan' => 'Barang datang berhasil dicatat dan inventaris telah diperbarui.',
            'data' => $service->detail($penerimaan),
        ])->setStatusCode(201);
    }

    public function batalkan(
        Request $request,
        PenerimaanBarang $penerimaanBarang,
        BatalkanPenerimaanBarang $pembatalan,
        PenerimaanBarangMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'alasan_pembatalan' => ['required', 'string', 'min:10', 'max:1000'],
            'konfirmasi_pembatalan' => ['accepted'],
        ], [
            'alasan_pembatalan.required' => 'Alasan pembatalan wajib diisi.',
            'alasan_pembatalan.min' => 'Alasan pembatalan minimal 10 karakter.',
            'konfirmasi_pembatalan.accepted' => 'Centang konfirmasi setelah memastikan penerimaan yang dipilih benar.',
        ]);

        $penerimaan = $pembatalan->batalkan(
            $penerimaanBarang,
            trim($data['alasan_pembatalan']),
            $request->user()?->id,
        );

        return $this->tanpaCache([
            'pesan' => 'Penerimaan berhasil dibatalkan. Stok dan unit aset telah dikoreksi.',
            'data' => $service->detail($penerimaan),
        ]);
    }

    private function aturanPenerimaan(): array
    {
        return [
            'token_penyimpanan' => ['required', 'uuid'],
            'tanggal_penerimaan' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'sumber_perolehan_barang_id' => [
                'required', 'integer',
                Rule::exists('sumber_perolehan_barang', 'id')->where('aktif', true),
            ],
            'cara_perolehan' => ['required', Rule::in(array_keys(PenerimaanBarang::DAFTAR_CARA_PEROLEHAN))],
            'nomor_dokumen' => ['nullable', 'string', 'max:120'],
            'asal_barang' => ['nullable', 'string', 'max:160'],
            'catatan' => ['nullable', 'string'],
            'rincian' => ['required', 'array', 'min:1', 'max:50'],
            'rincian.*.barang_id' => ['required', 'integer', 'exists:barang,id'],
            'rincian.*.lokasi_barang_id' => ['required', 'integer', 'exists:lokasi_barang,id'],
            'rincian.*.jumlah' => ['required', 'numeric', 'gt:0', 'max:9999999999.99'],
            'rincian.*.harga_satuan' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'rincian.*.merek' => ['nullable', 'string', 'max:120'],
            'rincian.*.tipe' => ['nullable', 'string', 'max:120'],
            'rincian.*.kondisi' => ['nullable', Rule::in(array_keys(UnitBarang::DAFTAR_KONDISI))],
            'rincian.*.keterangan' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function pesanValidasi(): array
    {
        return [
            'rincian.required' => 'Tambahkan minimal satu barang yang diterima.',
            'rincian.min' => 'Tambahkan minimal satu barang yang diterima.',
            'rincian.*.barang_id.required' => 'Barang pada setiap baris wajib dipilih.',
            'rincian.*.lokasi_barang_id.required' => 'Lokasi penyimpanan pada setiap baris wajib dipilih.',
            'rincian.*.jumlah.gt' => 'Jumlah barang harus lebih dari nol.',
        ];
    }

    private function rapikanData(array $data): array
    {
        foreach (['nomor_dokumen', 'asal_barang', 'catatan'] as $kolom) {
            $data[$kolom] = filled($data[$kolom] ?? null) ? trim($data[$kolom]) : null;
        }
        $data['rincian'] = collect($data['rincian'])->map(function (array $rincian) {
            foreach (['merek', 'tipe', 'keterangan'] as $kolom) {
                $rincian[$kolom] = filled($rincian[$kolom] ?? null) ? trim($rincian[$kolom]) : null;
            }
            $rincian['harga_satuan'] = filled($rincian['harga_satuan'] ?? null)
                ? $rincian['harga_satuan']
                : null;

            return $rincian;
        })->values()->all();

        return $data;
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
