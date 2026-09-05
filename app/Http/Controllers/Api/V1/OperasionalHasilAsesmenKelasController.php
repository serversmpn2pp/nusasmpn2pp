<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UjianCbt;
use App\Services\Cbt\TerapkanNilaiCbtService;
use App\Services\Mobile\KoreksiUraianAsesmenKelasMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OperasionalHasilAsesmenKelasController extends Controller
{
    public function koreksiUraian(
        Request $request,
        UjianCbt $ujianCbt,
        KoreksiUraianAsesmenKelasMobileService $service,
    ): JsonResponse {
        $filter = $request->validate([
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'status' => ['nullable', Rule::in(['semua', 'belum_dikoreksi', 'sudah_dikoreksi'])],
        ]);

        return $this->tanpaCache([
            'data' => $service->daftar($request->user(), $ujianCbt, $filter),
        ]);
    }

    public function simpanKoreksiUraian(
        Request $request,
        UjianCbt $ujianCbt,
        KoreksiUraianAsesmenKelasMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'skor' => ['required', 'array', 'min:1'],
            'skor.*.jawaban_id' => ['required', 'integer', 'distinct'],
            'skor.*.nilai' => ['nullable', 'numeric', 'min:0'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'status' => ['nullable', Rule::in(['semua', 'belum_dikoreksi', 'sudah_dikoreksi'])],
        ]);
        $jumlah = $service->simpan($request->user(), $ujianCbt, $data['skor']);

        return $this->tanpaCache([
            'pesan' => "{$jumlah} koreksi jawaban berhasil disimpan.",
            'data' => $service->daftar($request->user(), $ujianCbt, $data),
        ]);
    }

    public function terapkanNilai(
        Request $request,
        UjianCbt $ujianCbt,
        TerapkanNilaiCbtService $service,
    ): JsonResponse {
        abort_unless(
            $ujianCbt->asesmenKelas() && $ujianCbt->dapatDikelolaOleh($request->user()),
            403,
        );
        $hasil = $service->terapkan($ujianCbt, $request->user()?->id);

        return $this->tanpaCache([
            'pesan' => $hasil['pesan'],
            'data' => $hasil['ringkasan'],
        ]);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
