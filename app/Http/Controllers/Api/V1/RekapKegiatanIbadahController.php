<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AnggotaKelas;
use App\Services\Mobile\RekapKegiatanIbadahMobileService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RekapKegiatanIbadahController extends Controller
{
    public function index(Request $request, RekapKegiatanIbadahMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'tanggal' => ['nullable', 'date', 'before_or_equal:today'],
            'kegiatan_ibadah_id' => ['nullable', 'integer', 'exists:kegiatan_ibadah,id'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'status' => ['nullable', Rule::in(['semua', 'sudah', 'belum', 'berhalangan', 'tidak_hadir'])],
            'cari' => ['nullable', 'string', 'max:100'],
            'halaman' => ['nullable', 'integer', 'min:1'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($request->user(), $filter)]);
    }

    public function showCorrection(
        Request $request,
        AnggotaKelas $anggotaKelas,
        RekapKegiatanIbadahMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'kegiatan_ibadah_id' => ['required', 'integer', 'exists:kegiatan_ibadah,id'],
        ]);

        return $this->tanpaCache([
            'data' => $service->detailKoreksi(
                $request->user(),
                $anggotaKelas,
                (int) $data['kegiatan_ibadah_id'],
                Carbon::parse($data['tanggal'])->startOfDay(),
            ),
        ]);
    }

    public function updateCorrection(
        Request $request,
        AnggotaKelas $anggotaKelas,
        RekapKegiatanIbadahMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'kegiatan_ibadah_id' => ['required', 'integer', 'exists:kegiatan_ibadah,id'],
            'status_presensi' => ['required', Rule::in(['sudah', 'belum'])],
            'waktu_presensi' => ['nullable', 'required_if:status_presensi,sudah', 'date_format:H:i'],
            'alasan' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        $tanggal = Carbon::parse($data['tanggal'])->startOfDay();
        $pesan = $service->simpanKoreksi(
            $request->user(),
            $anggotaKelas,
            (int) $data['kegiatan_ibadah_id'],
            $tanggal,
            $data['status_presensi'],
            $data['waktu_presensi'] ?? null,
            $data['alasan'],
            $request->ip(),
            $request->userAgent(),
        );

        return $this->tanpaCache([
            'message' => $pesan,
            'data' => $service->detailKoreksi(
                $request->user(),
                $anggotaKelas->fresh(),
                (int) $data['kegiatan_ibadah_id'],
                $tanggal,
            ),
        ]);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
