<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Kelas;
use App\Models\TahunPelajaran;
use App\Services\Mobile\KenaikanKelasMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KenaikanKelasController extends Controller
{
    public function index(Request $request, KenaikanKelasMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'tahun_asal_id' => ['nullable', 'integer', Rule::exists('tahun_pelajaran', 'id')],
            'tahun_tujuan_id' => [
                'nullable',
                'integer',
                Rule::exists('tahun_pelajaran', 'id'),
                'different:tahun_asal_id',
            ],
            'kelas_asal_id' => ['nullable', 'integer', Rule::exists('kelas', 'id')],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($filter)]);
    }

    public function store(Request $request, KenaikanKelasMobileService $service): JsonResponse
    {
        $data = $request->validate([
            'tahun_asal_id' => [
                'required',
                'integer',
                Rule::exists('tahun_pelajaran', 'id'),
                'different:tahun_tujuan_id',
            ],
            'tahun_tujuan_id' => [
                'required',
                'integer',
                Rule::exists('tahun_pelajaran', 'id'),
            ],
            'kelas_asal_id' => ['required', 'integer', Rule::exists('kelas', 'id')],
            'penempatan' => ['required', 'array', 'min:1'],
            'penempatan.*.anggota_kelas_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('anggota_kelas', 'id'),
            ],
            'penempatan.*.kelas_tujuan_id' => [
                'nullable',
                'integer',
                Rule::exists('kelas', 'id'),
            ],
            'penempatan.*.keterangan' => ['nullable', 'string', 'max:500'],
        ]);

        $ringkasan = $service->proses(
            TahunPelajaran::findOrFail($data['tahun_asal_id']),
            TahunPelajaran::findOrFail($data['tahun_tujuan_id']),
            Kelas::findOrFail($data['kelas_asal_id']),
            $data['penempatan'],
        );

        return $this->tanpaCache([
            'pesan' => 'Proses kenaikan kelas selesai.',
            'data' => $ringkasan,
        ]);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
