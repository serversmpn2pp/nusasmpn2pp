<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\JadwalUjianCbt;
use App\Services\Mobile\PaketSoalMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PaketSoalController extends Controller
{
    public function index(Request $request, PaketSoalMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'kata_kunci' => ['nullable', 'string', 'max:100'],
            'kegiatan_id' => ['nullable', 'integer', Rule::exists('kegiatan_ujian_cbt', 'id')],
            'status' => ['nullable', Rule::in(['semua', 'belum_disusun', 'draft', 'siap'])],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:30'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($request->user(), $filter)]);
    }

    public function show(Request $request, JadwalUjianCbt $jadwalUjianCbt, PaketSoalMobileService $service): JsonResponse
    {
        return $this->tanpaCache(['data' => $service->rincian($request->user(), $jadwalUjianCbt)]);
    }

    public function update(Request $request, JadwalUjianCbt $jadwalUjianCbt, PaketSoalMobileService $service): JsonResponse
    {
        $data = $request->validate([
            'aksi' => ['required', Rule::in(['draf', 'simpan', 'terbitkan'])],
            'soal' => ['nullable', 'array', 'max:200'],
            'soal.*.id' => ['required', 'integer', Rule::exists('soal_cbt', 'id')],
            'soal.*.bobot' => ['required', 'numeric', 'min:0.25', 'max:100'],
            'acak_soal' => ['required', 'boolean'],
            'acak_jawaban' => ['required', 'boolean'],
        ]);

        $paket = $service->simpan($request->user(), $jadwalUjianCbt, $data);

        return $this->tanpaCache([
            'pesan' => $data['aksi'] === 'terbitkan'
                ? 'Paket soal berhasil diterbitkan dan siap digunakan.'
                : 'Perubahan paket soal berhasil disimpan.',
            'data' => $paket,
        ]);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
