<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BerkasSuratTu;
use App\Models\SuratTu;
use App\Services\Mobile\PersuratanMobileService;
use App\Services\Persuratan\PersuratanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PersuratanController extends Controller
{
    public function __construct(private PersuratanService $service, private PersuratanMobileService $mobile) {}

    public function index(Request $request): JsonResponse
    {
        return $this->json($this->mobile->daftarSurat($request));
    }

    public function pengesahan(Request $request): JsonResponse
    {
        $this->service->pastikanPegawai($request->user());
        abort_unless($request->user()->memilikiIzin('surat.sahkan'), 403);
        $request->merge(['pemeriksaan_saya' => true]);

        return $this->index($request);
    }

    public function referensi(Request $request): JsonResponse
    {
        return $this->json($this->mobile->referensi($request->user()));
    }

    public function show(Request $request, SuratTu $surat): JsonResponse
    {
        return $this->json($this->mobile->detailSurat($request->user(), $surat));
    }

    public function store(Request $request): JsonResponse
    {
        $surat = $this->service->simpan($request);

        return $this->json($this->mobile->detailSurat($request->user(), $surat), 'Surat berhasil disimpan.', 201);
    }

    public function update(Request $request, SuratTu $surat): JsonResponse
    {
        $surat = $this->service->simpan($request, $surat);

        return $this->json($this->mobile->detailSurat($request->user(), $surat), 'Perubahan surat disimpan.');
    }

    public function tindakan(Request $request, SuratTu $surat, string $aksi): JsonResponse
    {
        $surat = $this->service->tindakan($request, $surat, $aksi);

        return $this->json($this->mobile->detailSurat($request->user(), $surat), 'Status surat diperbarui.');
    }

    public function berkas(Request $request, SuratTu $surat, BerkasSuratTu $berkas)
    {
        return $this->service->unduh($request->user(), $surat, $berkas);
    }

    public function saya(Request $request): JsonResponse
    {
        return $this->json($this->mobile->daftarSurat($request, true));
    }

    public function detailSaya(Request $request, SuratTu $surat): JsonResponse
    {
        return $this->json($this->mobile->detailSurat($request->user(), $surat, true));
    }

    public function baca(Request $request, SuratTu $surat): JsonResponse
    {
        $this->service->baca($request->user(), $surat);

        return $this->json($this->mobile->detailSurat($request->user(), $surat->fresh(), true), 'Surat ditandai sudah dibaca.');
    }

    public function berkasSaya(Request $request, SuratTu $surat, BerkasSuratTu $berkas)
    {
        return $this->service->unduh($request->user(), $surat, $berkas, true);
    }

    private function json(array $data, ?string $pesan = null, int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data] + ($pesan ? ['pesan' => $pesan] : []), $status)->header('Cache-Control', 'private, no-store, max-age=0');
    }
}
