<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Mobile\PengaturanInventarisMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PengaturanInventarisController extends Controller
{
    public function show(PengaturanInventarisMobileService $service): JsonResponse
    {
        return $this->tanpaCache(['data' => $service->ambil()]);
    }

    public function update(Request $request, PengaturanInventarisMobileService $service): JsonResponse
    {
        $data = $request->validate([
            'awalan_nomor_aset' => ['required', 'string', 'max:80', 'regex:/^\d{2}(?:\.\d{2})*$/'],
            'akhiran_nomor_aset' => ['required', 'string', 'max:20', 'regex:/^\d{2}$/'],
            'nama_pemilik' => ['required', 'string', 'max:160'],
            'jumlah_digit_id_internal' => ['required', 'integer', 'min:4', 'max:10'],
        ], [
            'awalan_nomor_aset.regex' => 'Awalan nomor aset harus berupa kelompok dua angka yang dipisahkan titik, misalnya 12.03.15.08.10.',
            'akhiran_nomor_aset.regex' => 'Akhiran nomor aset harus terdiri dari dua angka, misalnya 08.',
        ]);

        return $this->tanpaCache([
            'pesan' => 'Pengaturan identitas inventaris berhasil disimpan.',
            'data' => $service->simpan($data, (int) $request->user()->id),
        ]);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
