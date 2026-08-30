<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AturanSanksiPoin;
use App\Services\Mobile\AturanSanksiPoinMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AturanSanksiPoinController extends Controller
{
    public function index(Request $request, AturanSanksiPoinMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'cari' => ['nullable', 'string', 'max:150'],
            'status' => ['nullable', Rule::in(['semua', 'aktif', 'nonaktif'])],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($filter)]);
    }

    public function store(Request $request, AturanSanksiPoinMobileService $service): JsonResponse
    {
        $aturan = $service->tambah($request->validate($this->aturanValidasi()));
        $aturan->loadCount('sanksiPoinSiswa');

        return $this->tanpaCache([
            'pesan' => 'Aturan sanksi poin berhasil ditambahkan.',
            'data' => $service->ringkas($aturan),
        ])->setStatusCode(201);
    }

    public function update(
        Request $request,
        AturanSanksiPoin $aturanSanksiPoin,
        AturanSanksiPoinMobileService $service,
    ): JsonResponse {
        $service->ubah(
            $aturanSanksiPoin,
            $request->validate($this->aturanValidasi($aturanSanksiPoin)),
        );

        return $this->tanpaCache([
            'pesan' => 'Aturan sanksi poin berhasil diperbarui. Sanksi yang sudah terpicu tetap tersimpan.',
        ]);
    }

    public function destroy(
        AturanSanksiPoin $aturanSanksiPoin,
        AturanSanksiPoinMobileService $service,
    ): JsonResponse {
        $service->nonaktifkan($aturanSanksiPoin);

        return $this->tanpaCache([
            'pesan' => 'Aturan sanksi poin dinonaktifkan tanpa menghapus sanksi yang sudah terpicu.',
        ]);
    }

    private function aturanValidasi(?AturanSanksiPoin $aturan = null): array
    {
        return [
            'batas_poin' => [
                'required',
                'integer',
                'min:1',
                'max:10000',
                Rule::unique('aturan_sanksi_poin', 'batas_poin')->ignore($aturan),
            ],
            'nama' => ['required', 'string', 'max:120'],
            'deskripsi' => ['required', 'string'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'aktif' => ['required', 'boolean'],
        ];
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
