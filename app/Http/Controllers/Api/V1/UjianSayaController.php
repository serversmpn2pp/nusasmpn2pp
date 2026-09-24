<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PesertaUjianCbt;
use App\Services\Cbt\JawabanBerkasUjianCbtService;
use App\Services\Cbt\KeamananUjianService;
use App\Services\Mobile\UjianSayaMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UjianSayaController extends Controller
{
    public function index(Request $request, UjianSayaMobileService $service): JsonResponse
    {
        return $this->tanpaCache(['data' => $service->daftar($request->user())]);
    }

    public function show(
        Request $request,
        PesertaUjianCbt $pesertaUjianCbt,
        UjianSayaMobileService $service,
    ): JsonResponse {
        return $this->tanpaCache(['data' => $service->rincian($request->user(), $pesertaUjianCbt)]);
    }

    public function mulai(
        Request $request,
        PesertaUjianCbt $pesertaUjianCbt,
        UjianSayaMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'token' => ['nullable', 'string', 'max:20'],
            'perangkat' => ['required', 'string', 'max:120'],
        ]);

        return $this->tanpaCache([
            'pesan' => $pesertaUjianCbt->status === 'sedang_mengerjakan'
                ? 'Ujian dilanjutkan.'
                : 'Ujian dimulai.',
            'data' => $service->mulai(
                $request->user(),
                $pesertaUjianCbt,
                $data['token'] ?? null,
                $data['perangkat'],
                $request->ip(),
                $request->userAgent(),
            ),
        ]);
    }

    public function kerjakan(
        Request $request,
        PesertaUjianCbt $pesertaUjianCbt,
        UjianSayaMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'perangkat' => ['required', 'string', 'max:120'],
        ]);

        return $this->tanpaCache([
            'data' => $service->pengerjaan($request->user(), $pesertaUjianCbt, $data['perangkat']),
        ]);
    }

    public function simpanJawaban(
        Request $request,
        PesertaUjianCbt $pesertaUjianCbt,
        UjianSayaMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'soal_ujian_cbt_id' => ['required', 'integer'],
            'jawaban' => ['nullable', 'array', 'max:100'],
            'jawaban.*' => ['nullable', 'string', 'max:10000'],
            'ragu' => ['required', 'boolean'],
            'perangkat' => ['required', 'string', 'max:120'],
        ]);

        return $this->tanpaCache([
            'pesan' => 'Jawaban tersimpan.',
            'data' => $service->simpanJawaban(
                $request->user(),
                $pesertaUjianCbt,
                (int) $data['soal_ujian_cbt_id'],
                $data['jawaban'] ?? null,
                (bool) $data['ragu'],
                $data['perangkat'],
            ),
        ]);
    }

    public function simpanBerkasJawaban(
        Request $request,
        PesertaUjianCbt $pesertaUjianCbt,
        UjianSayaMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'soal_ujian_cbt_id' => ['required', 'integer'],
            'berkas' => [
                'required',
                'file',
                'mimes:'.implode(',', JawabanBerkasUjianCbtService::EKSTENSI),
                'max:'.JawabanBerkasUjianCbtService::MAKSIMAL_KILOBYTE,
            ],
            'ragu' => ['required', 'boolean'],
            'perangkat' => ['required', 'string', 'max:120'],
        ], [
            'berkas.required' => 'Pilih berkas jawaban terlebih dahulu.',
            'berkas.mimes' => 'Format berkas belum didukung. Gunakan PDF, gambar, atau dokumen Office.',
            'berkas.max' => 'Ukuran berkas maksimal 10 MB.',
        ]);

        return $this->tanpaCache([
            'pesan' => 'Berkas jawaban berhasil diunggah.',
            'data' => $service->simpanBerkasJawaban(
                $request->user(),
                $pesertaUjianCbt,
                (int) $data['soal_ujian_cbt_id'],
                $data['berkas'],
                (bool) $data['ragu'],
                $data['perangkat'],
            ),
        ]);
    }

    public function selesai(
        Request $request,
        PesertaUjianCbt $pesertaUjianCbt,
        UjianSayaMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'perangkat' => ['required', 'string', 'max:120'],
        ]);

        return $this->tanpaCache([
            'pesan' => 'Jawaban berhasil dikumpulkan.',
            'data' => $service->selesai($request->user(), $pesertaUjianCbt, $data['perangkat']),
        ]);
    }

    public function aktivitasKeamanan(
        Request $request,
        PesertaUjianCbt $pesertaUjianCbt,
        KeamananUjianService $service,
    ): JsonResponse {
        $data = $request->validate([
            'peristiwa' => ['required', 'in:keluar,kembali,heartbeat'],
            'perangkat' => ['required', 'string', 'max:120'],
            'metadata' => ['nullable', 'array'],
        ]);

        return $this->tanpaCache([
            'data' => $service->catat(
                $request->user(),
                $pesertaUjianCbt,
                $data['peristiwa'],
                $data['perangkat'],
                $request->ip(),
                $data['metadata'] ?? [],
            ),
        ]);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
