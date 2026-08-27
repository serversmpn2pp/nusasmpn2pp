<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GuruMataPelajaran;
use App\Services\Mobile\SurveiPembelajaranMobileService;
use App\Services\Survei\PengisianSurveiPembelajaranService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SurveiPembelajaranController extends Controller
{
    public function show(
        Request $request,
        GuruMataPelajaran $guruMataPelajaran,
        string $semester,
        PengisianSurveiPembelajaranService $survei,
        SurveiPembelajaranMobileService $mobile,
    ): JsonResponse {
        $konteks = $survei->siapkan($request->user(), $guruMataPelajaran, $semester);

        return $this->tanpaCache([
            'data' => $mobile->susun($konteks),
        ]);
    }

    public function store(
        Request $request,
        GuruMataPelajaran $guruMataPelajaran,
        string $semester,
        PengisianSurveiPembelajaranService $survei,
    ): JsonResponse {
        $konteks = $survei->siapkan($request->user(), $guruMataPelajaran, $semester);

        if ($konteks['sudahDiisi']) {
            return $this->tanpaCache([
                'pesan' => 'Survei pembelajaran ini sudah Anda isi. Nilai sudah dapat dilihat.',
                'data' => ['sudah_diisi' => true, 'baru_dibuat' => false],
            ]);
        }

        $data = $request->validate(
            $survei->aturanValidasi($konteks['daftarPertanyaan']),
            $survei->pesanValidasi(),
        );
        $hasil = $survei->simpan($konteks, $data);

        return $this->tanpaCache([
            'pesan' => 'Terima kasih. Survei berhasil dikirim dan nilai mata pelajaran sudah terbuka.',
            'data' => [
                'sudah_diisi' => true,
                'baru_dibuat' => $hasil->wasRecentlyCreated,
            ],
        ]);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
