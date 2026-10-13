<?php

namespace App\Http\Controllers;

use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Services\Nilai\PublikasiRaporStsService;
use Illuminate\Http\Request;

class PublikasiRaporStsController extends Controller
{
    public function index(Request $request, KegiatanUjianCbt $kegiatan, Kelas $kelas, PublikasiRaporStsService $service)
    {
        $data = $service->konteks($request->user(), $kegiatan, $kelas);
        $response = $request->routeIs('api.*') ? response()->json(['data' => $data])
            : response()->view('rapor-sts.publikasi', compact('data', 'kegiatan', 'kelas'));

        return $response->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function store(Request $request, KegiatanUjianCbt $kegiatan, Kelas $kelas, PublikasiRaporStsService $service)
    {
        $jumlah = $service->publikasikan($request->user(), $kegiatan, $kelas, $request->all());

        return $this->hasil($request, $kegiatan, $kelas, $jumlah.' rapor terpilih telah dipublikasikan. Versi yang sama tidak diterbitkan ulang.');
    }

    public function destroy(Request $request, KegiatanUjianCbt $kegiatan, Kelas $kelas, PublikasiRaporStsService $service)
    {
        $jumlah = $service->batalkan($request->user(), $kegiatan, $kelas, $request->all());

        return $this->hasil($request, $kegiatan, $kelas, 'Publikasi '.$jumlah.' rapor dibatalkan. Salinan yang pernah diunduh tidak dapat ditarik kembali.');
    }

    private function hasil(Request $request, KegiatanUjianCbt $kegiatan, Kelas $kelas, string $pesan)
    {
        if ($request->routeIs('api.*')) {
            return response()->json(['data' => ['pesan' => $pesan]])->header('Cache-Control', 'private, no-store, max-age=0');
        }

        return redirect()->route('rapor-sts.publikasi.index', [$kegiatan, $kelas])->with('berhasil', $pesan);
    }
}
