<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Peran;
use App\Services\Mobile\PeranMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PeranController extends Controller
{
    public function index(Request $request, PeranMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'cari' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['semua', 'aktif', 'nonaktif'])],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($request->user(), $filter)]);
    }

    public function referensi(Request $request, PeranMobileService $service): JsonResponse
    {
        return $this->tanpaCache(['data' => $service->referensi($request->user())]);
    }

    public function show(
        Request $request,
        Peran $peran,
        PeranMobileService $service,
    ): JsonResponse {
        return $this->tanpaCache(['data' => $service->detail($request->user(), $peran)]);
    }

    public function store(Request $request, PeranMobileService $service): JsonResponse
    {
        $peran = $service->buat($this->validasi($request));

        return $this->tanpaCache([
            'pesan' => 'Peran baru berhasil ditambahkan.',
            'data' => ['id' => (int) $peran->id],
        ])->setStatusCode(201);
    }

    public function update(
        Request $request,
        Peran $peran,
        PeranMobileService $service,
    ): JsonResponse {
        $service->ubah($peran, $this->validasi($request));

        return $this->tanpaCache(['pesan' => 'Peran berhasil diperbarui.']);
    }

    public function destroy(Peran $peran, PeranMobileService $service): JsonResponse
    {
        $service->nonaktifkan($peran);

        return $this->tanpaCache(['pesan' => 'Peran berhasil dinonaktifkan.']);
    }

    private function validasi(Request $request): array
    {
        return $request->validate([
            'nama' => ['required', 'string', 'max:120'],
            'kode' => ['nullable', 'string', 'max:120', 'regex:/^[a-z0-9_\-]+$/'],
            'deskripsi' => ['nullable', 'string'],
            'aktif' => ['nullable', 'boolean'],
            'izin_ids' => ['nullable', 'array'],
            'izin_ids.*' => [
                'integer',
                Rule::exists('izin', 'id')->where('aktif', true),
            ],
        ], [
            'kode.regex' => 'Kode hanya boleh berisi huruf kecil, angka, garis bawah, atau tanda hubung.',
        ]);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
