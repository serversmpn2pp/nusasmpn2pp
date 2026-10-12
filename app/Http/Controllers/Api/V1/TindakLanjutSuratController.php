<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BerkasSuratTu;
use App\Models\BuktiTindakLanjutSuratTu;
use App\Models\SuratTu;
use App\Models\TindakLanjutSuratTu;
use App\Services\Mobile\PersuratanMobileService;
use App\Services\Persuratan\TindakLanjutSuratService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TindakLanjutSuratController extends Controller
{
    public function __construct(private TindakLanjutSuratService $service, private PersuratanMobileService $mobile) {}

    public function index(Request $request): JsonResponse
    {
        return $this->json($this->mobile->daftarTindak($request));
    }

    public function monitoring(Request $request): JsonResponse
    {
        return $this->json($this->mobile->daftarTindak($request, true));
    }

    public function show(Request $request, TindakLanjutSuratTu $tindakLanjut): JsonResponse
    {
        return $this->json($this->mobile->detailTindak($request->user(), $tindakLanjut));
    }

    public function disposisi(Request $request, SuratTu $surat): JsonResponse
    {
        $this->service->disposisi($request, $surat);

        return $this->json($this->mobile->detailSurat($request->user(), $surat->fresh()), 'Disposisi disimpan dan penerima diberi notifikasi.');
    }

    public function laporan(Request $request, TindakLanjutSuratTu $tindakLanjut): JsonResponse
    {
        $this->service->laporan($request, $tindakLanjut);

        return $this->json($this->mobile->detailTindak($request->user(), $tindakLanjut->fresh()), 'Laporan / progres disimpan.', 201);
    }

    public function tindakan(Request $request, TindakLanjutSuratTu $tindakLanjut, string $aksi): JsonResponse
    {
        $this->service->tindakan($request, $tindakLanjut, $aksi);

        return $this->json($this->mobile->detailTindak($request->user(), $tindakLanjut->fresh()), 'Tindak lanjut diperbarui.');
    }

    public function bukti(Request $request, TindakLanjutSuratTu $tindakLanjut, BuktiTindakLanjutSuratTu $bukti)
    {
        return $this->service->unduhBukti($request->user(), $tindakLanjut, $bukti);
    }

    public function berkas(Request $request, TindakLanjutSuratTu $tindakLanjut, BerkasSuratTu $berkas)
    {
        return $this->service->unduhSurat($request->user(), $tindakLanjut, $berkas);
    }

    private function json(array $data, ?string $pesan = null, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data] + ($pesan ? ['pesan' => $pesan] : []), $status)->header('Cache-Control', 'private, no-store, max-age=0');
    }
}
