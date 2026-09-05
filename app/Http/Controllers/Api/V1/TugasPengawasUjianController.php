<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BuktiRuangUjianCbt;
use App\Models\PesertaUjianCbt;
use App\Models\RuangUjianCbt;
use App\Services\Mobile\TugasPengawasUjianMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TugasPengawasUjianController extends Controller
{
    public function index(Request $request, TugasPengawasUjianMobileService $service): JsonResponse
    {
        return $this->tanpaCache(['data' => $service->daftar($request->user())]);
    }

    public function show(
        Request $request,
        RuangUjianCbt $ruangUjianCbt,
        TugasPengawasUjianMobileService $service,
    ): JsonResponse {
        return $this->tanpaCache(['data' => $service->detail($request->user(), $ruangUjianCbt)]);
    }

    public function status(
        Request $request,
        RuangUjianCbt $ruangUjianCbt,
        TugasPengawasUjianMobileService $service,
    ): JsonResponse {
        $data = $request->validate(['aksi' => ['required', Rule::in(['mulai', 'selesai'])]]);

        return $this->tanpaCache([
            'pesan' => $data['aksi'] === 'mulai' ? 'Pelaksanaan ruang dimulai.' : 'Pelaksanaan ruang diselesaikan.',
            'data' => $service->ubahStatus($request->user(), $ruangUjianCbt, $data['aksi']),
        ]);
    }

    public function catatan(
        Request $request,
        RuangUjianCbt $ruangUjianCbt,
        TugasPengawasUjianMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'berita_acara' => ['nullable', 'string', 'max:5000'],
            'hambatan' => ['nullable', 'string', 'max:5000'],
            'tindak_lanjut' => ['nullable', 'string', 'max:5000'],
            'catatan' => ['nullable', 'string', 'max:5000'],
        ]);

        return $this->tanpaCache([
            'pesan' => 'Catatan ruang berhasil disimpan.',
            'data' => $service->simpanCatatan($request->user(), $ruangUjianCbt, $data),
        ]);
    }

    public function kehadiran(
        Request $request,
        RuangUjianCbt $ruangUjianCbt,
        PesertaUjianCbt $pesertaUjianCbt,
        TugasPengawasUjianMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(PesertaUjianCbt::DAFTAR_STATUS_KEHADIRAN))],
            'catatan' => ['nullable', 'string', 'max:1000'],
        ]);

        return $this->tanpaCache([
            'pesan' => 'Presensi peserta berhasil diperbarui.',
            'data' => $service->ubahKehadiran(
                $request->user(),
                $ruangUjianCbt,
                $pesertaUjianCbt,
                $data['status'],
                $data['catatan'] ?? null,
            ),
        ]);
    }

    public function resetPerangkat(
        Request $request,
        RuangUjianCbt $ruangUjianCbt,
        PesertaUjianCbt $pesertaUjianCbt,
        TugasPengawasUjianMobileService $service,
    ): JsonResponse {
        return $this->tanpaCache([
            'pesan' => 'Ikatan perangkat peserta berhasil direset.',
            'data' => $service->resetPerangkat($request->user(), $ruangUjianCbt, $pesertaUjianCbt),
        ]);
    }

    public function storeBukti(
        Request $request,
        RuangUjianCbt $ruangUjianCbt,
        TugasPengawasUjianMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'jenis' => ['required', Rule::in([
                BuktiRuangUjianCbt::JENIS_DAFTAR_HADIR,
                BuktiRuangUjianCbt::JENIS_BERITA_ACARA,
            ])],
            'berkas' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        return $this->tanpaCache([
            'pesan' => 'Bukti ruang berhasil diunggah.',
            'data' => $service->unggahBukti(
                $request->user(),
                $ruangUjianCbt,
                $data['jenis'],
                $request->file('berkas'),
            ),
        ]);
    }

    public function destroyBukti(
        Request $request,
        RuangUjianCbt $ruangUjianCbt,
        BuktiRuangUjianCbt $buktiRuangUjianCbt,
        TugasPengawasUjianMobileService $service,
    ): JsonResponse {
        return $this->tanpaCache([
            'pesan' => 'Bukti ruang berhasil dihapus.',
            'data' => $service->hapusBukti($request->user(), $ruangUjianCbt, $buktiRuangUjianCbt),
        ]);
    }

    public function kirimBukti(
        Request $request,
        RuangUjianCbt $ruangUjianCbt,
        TugasPengawasUjianMobileService $service,
    ): JsonResponse {
        return $this->tanpaCache([
            'pesan' => 'Bukti ruang dikirim dan menunggu pemeriksaan panitia.',
            'data' => $service->kirimBukti($request->user(), $ruangUjianCbt),
        ]);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
