<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Mobile\StsMobileService;
use App\Services\Nilai\PublikasiRaporStsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublikasiRaporStsMobileController extends Controller
{
    public function __invoke(Request $request, StsMobileService $sts, PublikasiRaporStsService $publikasi): JsonResponse
    {
        $filter = $request->validate([
            'kegiatan_id' => ['nullable', 'integer', 'min:1'],
            'kelas_id' => ['nullable', 'integer', 'min:1'],
        ]);
        // Use the same authorized choices as the administrative STS report.
        // This read never publishes or changes a student's report.
        $pilihan = $sts->pilihan($request->user(), $filter);
        $kegiatan = $pilihan['kegiatan'];
        $kelas = $pilihan['kelas'];

        return response()->json(['data' => [
            'filter' => ['kegiatan_id' => $kegiatan?->id, 'kelas_id' => $kelas?->id],
            'opsi' => $pilihan['opsi'],
            'konteks' => $kegiatan && $kelas
                ? $publikasi->konteks($request->user(), $kegiatan, $kelas) : null,
        ]])->header('Cache-Control', 'private, no-store, max-age=0');
    }
}
