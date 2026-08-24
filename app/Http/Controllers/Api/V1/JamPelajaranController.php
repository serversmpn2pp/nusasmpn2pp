<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\JamPelajaran;
use App\Services\Mobile\JamPelajaranMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class JamPelajaranController extends Controller
{
    public function index(Request $request, JamPelajaranMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'hari' => ['nullable', Rule::in(['semua', ...array_keys(JamPelajaran::DAFTAR_HARI)])],
            'status' => ['nullable', Rule::in(['semua', 'aktif', 'nonaktif'])],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($filter)]);
    }

    public function store(Request $request, JamPelajaranMobileService $service): JsonResponse
    {
        $data = $request->validate([
            'hari' => ['required', 'array', 'min:1'],
            'hari.*' => ['required', 'distinct', Rule::in(array_keys(JamPelajaran::DAFTAR_HARI))],
            'posisi_sisip' => [
                'required',
                Rule::in(['awal', 'akhir', ...collect(range(1, 19))->map(fn ($nomor) => "setelah:{$nomor}")->all()]),
            ],
            'label' => ['nullable', 'string', 'max:100'],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i'],
            'jenis' => ['required', Rule::in(array_keys(JamPelajaran::DAFTAR_JENIS))],
            'aktif' => ['required', 'boolean'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);
        $hasil = $service->tambah($data);

        return $this->tanpaCache([
            'pesan' => "{$hasil['jumlah_baru']} slot jam berhasil ditambahkan.",
            'data' => $hasil,
        ])->setStatusCode(201);
    }

    public function update(
        Request $request,
        JamPelajaran $jamPelajaran,
        JamPelajaranMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:100'],
            'jam_mulai' => ['required', 'date_format:H:i'],
            'jam_selesai' => ['required', 'date_format:H:i'],
            'jenis' => ['required', Rule::in(array_keys(JamPelajaran::DAFTAR_JENIS))],
            'aktif' => ['required', 'boolean'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);
        $service->ubah($jamPelajaran, $data);

        return $this->tanpaCache(['pesan' => 'Jam pelajaran berhasil diperbarui.']);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
