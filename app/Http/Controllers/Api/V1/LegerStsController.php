<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Mobile\StsMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class LegerStsController extends Controller
{
    public function index(Request $request, StsMobileService $service): JsonResponse
    {
        $filter = $request->validate($this->aturanFilter());

        return $this->json($service->leger($request->user(), $filter));
    }

    public function penghargaan(Request $request, StsMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            ...$this->aturanFilter(), 'kategori' => ['nullable', Rule::in(['keseluruhan', 'mapel'])],
            'mapel_id' => ['nullable', 'integer', 'min:1'], 'batas' => ['nullable', Rule::in([1, 3, 10])],
        ]);

        return $this->json($service->penghargaan($request->user(), $filter));
    }

    public function cetak(Request $request, StsMobileService $service): Response
    {
        $filter = $request->validate([
            ...$this->aturanFilter(), 'mode' => ['required', Rule::in(['kelas', 'tingkat'])],
            'kegiatan_id' => ['required', 'integer', 'min:1'],
            'kelas_id' => [Rule::requiredIf($request->input('mode') === 'kelas'), 'nullable', 'integer', 'min:1'],
            'tingkat' => [Rule::requiredIf($request->input('mode') === 'tingkat'), 'nullable', 'integer', 'min:1', 'max:12'],
        ]);
        $k = $service->bangunLeger($request->user(), $filter);
        abort_unless($k['laporan'], 404);

        return response()->view('leger-sts.cetak', [
            'mode' => $k['mode'], 'kegiatan' => $k['kegiatan'], 'tingkat' => $k['tingkat'],
            'kelas' => $k['mode'] === 'kelas' ? $k['kelas'] : null, 'leger' => $k['laporan'],
        ])->header('Cache-Control', 'private, no-store');
    }

    private function aturanFilter(): array
    {
        return ['mode' => ['nullable', Rule::in(['kelas', 'tingkat'])], 'kegiatan_id' => ['nullable', 'integer', 'min:1'],
            'kelas_id' => ['nullable', 'integer', 'min:1'], 'tingkat' => ['nullable', 'integer', 'min:1', 'max:12']];
    }

    private function json(array $data): JsonResponse
    {
        return response()->json(['data' => $data])->header('Cache-Control', 'private, no-store');
    }
}
