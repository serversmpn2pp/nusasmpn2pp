<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Mobile\PenempatanSiswaMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PenempatanSiswaController extends Controller
{
    public function index(Request $request, PenempatanSiswaMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'cari' => ['nullable', 'string', 'max:100'],
        ]);

        return $this->tanpaCache([
            'data' => $service->daftar($request->user(), $filter),
        ]);
    }

    public function store(Request $request, PenempatanSiswaMobileService $service): JsonResponse
    {
        $data = $request->validate([
            'kelas_id' => ['required', 'integer', Rule::exists('kelas', 'id')],
            'siswa_ids' => ['required', 'array', 'min:1', 'max:150'],
            'siswa_ids.*' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('siswa', 'id')->where('aktif', true),
            ],
            'tanggal_masuk' => ['nullable', 'date'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ], [
            'siswa_ids.required' => 'Pilih minimal satu siswa untuk dimasukkan ke kelas.',
            'siswa_ids.min' => 'Pilih minimal satu siswa untuk dimasukkan ke kelas.',
        ]);
        $jumlah = $service->tempatkan($request->user(), $data);

        return $this->tanpaCache([
            'pesan' => $jumlah.' siswa berhasil ditempatkan ke kelas.',
            'data' => ['jumlah_ditempatkan' => $jumlah],
        ])->setStatusCode(201);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
