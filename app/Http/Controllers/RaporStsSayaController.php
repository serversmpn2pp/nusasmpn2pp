<?php

namespace App\Http\Controllers;

use App\Services\Nilai\PublikasiRaporStsService;
use Illuminate\Http\Request;

class RaporStsSayaController extends Controller
{
    public function index(Request $request, PublikasiRaporStsService $service)
    {
        $data = $service->daftarUntukPenerima($request->user());
        $response = $request->routeIs('api.*') ? response()->json(['data' => $data])
            : response()->view('rapor-sts.saya', compact('data'));

        return $response->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function show(Request $request, int $id, PublikasiRaporStsService $service)
    {
        $publikasi = $service->baca($request->user(), $id);
        if ($request->routeIs('api.*')) {
            return response()->json(['data' => ['id' => $publikasi->id, 'versi' => $publikasi->versi,
                'dipublikasikan_pada' => $publikasi->dipublikasikan_pada->toIso8601String(), 'rapor' => $publikasi->salinan]])
                ->header('Cache-Control', 'private, no-store, max-age=0');
        }

        return $this->cetak($request, $id, $service);
    }

    public function cetak(Request $request, int $id, PublikasiRaporStsService $service)
    {
        return response()->view('rapor-sts.cetak', $service->dataCetak($service->baca($request->user(), $id)))
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }
}
