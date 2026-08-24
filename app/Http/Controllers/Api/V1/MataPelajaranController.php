<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\MataPelajaran;
use App\Services\Mobile\MataPelajaranMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MataPelajaranController extends Controller
{
    public function index(Request $request, MataPelajaranMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'cari' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['semua', 'aktif', 'nonaktif'])],
            'tingkat' => ['nullable', Rule::in(['semua', '7', '8', '9'])],
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:50'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($request->user(), $filter)]);
    }

    public function referensi(MataPelajaranMobileService $service): JsonResponse
    {
        return $this->tanpaCache(['data' => $service->referensi()]);
    }

    public function store(Request $request, MataPelajaranMobileService $service): JsonResponse
    {
        $data = $request->validate($this->aturanValidasi($request));
        $mataPelajaran = $service->tambah($data);

        return $this->tanpaCache([
            'pesan' => 'Mata pelajaran berhasil ditambahkan.',
            'data' => ['id' => (int) $mataPelajaran->id],
        ])->setStatusCode(201);
    }

    public function update(
        Request $request,
        MataPelajaran $mataPelajaran,
        MataPelajaranMobileService $service,
    ): JsonResponse {
        $data = $request->validate($this->aturanValidasi($request, $mataPelajaran));
        $service->ubah($mataPelajaran, $data);

        return $this->tanpaCache(['pesan' => 'Mata pelajaran berhasil diperbarui.']);
    }

    private function aturanValidasi(Request $request, ?MataPelajaran $mataPelajaran = null): array
    {
        $mataPelajaran?->load([
            'pengaturanTingkat' => fn ($query) => $query
                ->where('tahun_pelajaran_id', $request->integer('tahun_pelajaran_id')),
        ]);
        $menggunakanPredikat = MataPelajaran::kelompokMenggunakanPredikat(
            $request->input('kelompok'),
        );
        $aturan = [
            'tahun_pelajaran_id' => ['required', 'integer', 'exists:tahun_pelajaran,id'],
            'nama' => [
                'required',
                'string',
                'max:255',
                Rule::unique('mata_pelajaran', 'nama')->ignore($mataPelajaran),
            ],
            'kelompok' => ['nullable', 'string', 'max:50'],
            'urutan' => ['nullable', 'integer', 'min:0', 'max:999'],
            'aktif' => ['required', 'boolean'],
            'keterangan' => ['nullable', 'string', 'max:2000'],
            'pengaturan' => ['required', 'array'],
        ];

        foreach ([7, 8, 9] as $tingkat) {
            $pengaturan = $mataPelajaran?->pengaturanTingkat->firstWhere('tingkat', $tingkat);
            $aktif = $request->boolean("pengaturan.{$tingkat}.aktif");
            $aturan["pengaturan.{$tingkat}.aktif"] = ['required', 'boolean'];
            $aturan["pengaturan.{$tingkat}.kode"] = [
                Rule::requiredIf($aktif),
                'nullable',
                'string',
                'max:30',
                Rule::unique('pengaturan_mata_pelajaran', 'kode')
                    ->where('tahun_pelajaran_id', $request->integer('tahun_pelajaran_id'))
                    ->ignore($pengaturan),
            ];
            $aturan["pengaturan.{$tingkat}.kkm"] = [
                Rule::requiredIf(! $menggunakanPredikat && $aktif),
                'nullable',
                'integer',
                'min:0',
                'max:100',
            ];
        }

        return $aturan;
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
