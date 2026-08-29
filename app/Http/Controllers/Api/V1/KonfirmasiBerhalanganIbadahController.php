<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\KonfirmasiBerhalanganIbadah;
use App\Models\PeriodeBerhalanganIbadah;
use App\Services\Ibadah\ProsesKonfirmasiBerhalanganIbadah;
use App\Services\Mobile\KonfirmasiBerhalanganIbadahMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class KonfirmasiBerhalanganIbadahController extends Controller
{
    public function index(Request $request, KonfirmasiBerhalanganIbadahMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'kelas_id' => ['nullable', 'integer'],
            'cari' => ['nullable', 'string', 'max:100'],
            'halaman' => ['nullable', 'integer', 'min:1'],
        ]);

        return $this->tanpaCache([
            'data' => $service->daftar($request->user(), $filter),
        ]);
    }

    public function show(
        Request $request,
        PeriodeBerhalanganIbadah $periodeBerhalanganIbadah,
        KonfirmasiBerhalanganIbadahMobileService $service,
    ): JsonResponse {
        return $this->tanpaCache([
            'data' => $service->detail($request->user(), $periodeBerhalanganIbadah),
        ]);
    }

    public function update(
        Request $request,
        PeriodeBerhalanganIbadah $periodeBerhalanganIbadah,
        KonfirmasiBerhalanganIbadahMobileService $service,
        ProsesKonfirmasiBerhalanganIbadah $proses,
    ): JsonResponse {
        $data = $request->validate([
            'hasil' => ['required', Rule::in(array_keys(KonfirmasiBerhalanganIbadah::DAFTAR_HASIL))],
            'jeda_konfirmasi_hari' => [
                'nullable',
                'required_if:hasil,'.KonfirmasiBerhalanganIbadah::HASIL_MASIH_BERHALANGAN,
                'integer',
                'min:1',
                'max:14',
            ],
            'catatan_privat' => ['nullable', 'string', 'max:500'],
        ], [
            'jeda_konfirmasi_hari.required_if' => 'Pilih waktu pengingat berikutnya.',
        ]);

        $service->pastikanDapatMengaksesPeriode($request->user(), $periodeBerhalanganIbadah);
        $proses->proses(
            periode: $periodeBerhalanganIbadah,
            petugas: $request->user(),
            hasil: $data['hasil'],
            jedaKonfirmasiHari: isset($data['jeda_konfirmasi_hari']) ? (int) $data['jeda_konfirmasi_hari'] : null,
            catatanPrivat: $data['catatan_privat'] ?? null,
        );

        $pesan = $data['hasil'] === KonfirmasiBerhalanganIbadah::HASIL_MASIH_BERHALANGAN
            ? 'Konfirmasi privat tersimpan. Periode tetap dipantau sampai pengingat berikutnya.'
            : 'Konfirmasi privat tersimpan dan periode telah ditutup.';

        return $this->tanpaCache([
            'message' => $pesan,
            'data' => $service->detail($request->user(), $periodeBerhalanganIbadah->fresh()),
        ]);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
