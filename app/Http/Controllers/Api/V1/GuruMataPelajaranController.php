<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GuruMataPelajaran;
use App\Services\Mobile\GuruMataPelajaranMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GuruMataPelajaranController extends Controller
{
    public function index(Request $request, GuruMataPelajaranMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'cari' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['semua', 'aktif', 'nonaktif'])],
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($request->user(), $filter)]);
    }

    public function referensi(GuruMataPelajaranMobileService $service): JsonResponse
    {
        return $this->tanpaCache(['data' => $service->referensi()]);
    }

    public function store(Request $request, GuruMataPelajaranMobileService $service): JsonResponse
    {
        $data = $request->validate([
            'tahun_pelajaran_id' => ['required', 'integer', 'exists:tahun_pelajaran,id'],
            'kelas_ids' => ['required', 'array', 'min:1'],
            'kelas_ids.*' => ['required', 'integer', 'distinct', 'exists:kelas,id'],
            'mata_pelajaran_id' => ['required', 'integer', 'exists:mata_pelajaran,id'],
            'pegawai_id' => ['required', 'integer', 'exists:pegawai,id'],
            'jenis_penugasan' => ['required', Rule::in(['pengampu', 'pendamping', 'koordinator'])],
            'aktif' => ['required', 'boolean'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);
        $hasil = $service->tambah($data);

        return $this->tanpaCache([
            'pesan' => 'Penugasan guru mata pelajaran berhasil disimpan.',
            'data' => $hasil,
        ])->setStatusCode(201);
    }

    public function update(
        Request $request,
        GuruMataPelajaran $guruMataPelajaran,
        GuruMataPelajaranMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'tahun_pelajaran_id' => ['required', 'integer', 'exists:tahun_pelajaran,id'],
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'mata_pelajaran_id' => ['required', 'integer', 'exists:mata_pelajaran,id'],
            'pegawai_id' => [
                'required',
                'integer',
                'exists:pegawai,id',
                Rule::unique('guru_mata_pelajaran', 'pegawai_id')
                    ->where('tahun_pelajaran_id', $request->integer('tahun_pelajaran_id'))
                    ->where('kelas_id', $request->integer('kelas_id'))
                    ->where('mata_pelajaran_id', $request->integer('mata_pelajaran_id'))
                    ->ignore($guruMataPelajaran),
            ],
            'jenis_penugasan' => ['required', Rule::in(['pengampu', 'pendamping', 'koordinator'])],
            'aktif' => ['required', 'boolean'],
            'keterangan' => ['nullable', 'string', 'max:1000'],
        ]);
        $service->ubah($guruMataPelajaran, $data);

        return $this->tanpaCache(['pesan' => 'Penugasan guru mata pelajaran berhasil diperbarui.']);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
