<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\GuruMataPelajaran;
use App\Models\KomponenNilai;
use App\Services\Mobile\InputNilaiMobileService;
use App\Services\Nilai\InputNilaiService;
use App\Services\Nilai\StsManualService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InputNilaiController extends Controller
{
    public function index(Request $request, InputNilaiMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'guru_mata_pelajaran_id' => ['nullable', 'integer', Rule::exists('guru_mata_pelajaran', 'id')],
            'semester' => ['nullable', Rule::in(['ganjil', 'genap'])],
            'komponen_nilai_id' => ['nullable', 'integer', Rule::exists('komponen_nilai', 'id')],
        ]);

        return $this->tanpaCache([
            'data' => $service->tampilkan($request->user(), $filter),
        ]);
    }

    public function store(Request $request, InputNilaiService $service): JsonResponse
    {
        $awal = $request->validate([
            'komponen_nilai_id' => ['required', 'integer', Rule::exists('komponen_nilai', 'id')],
        ]);
        $komponen = $service->ambilKomponenDalamCakupan(
            $request->user(),
            $awal['komponen_nilai_id'],
        );
        $request->merge([
            'nilai' => $service->normalisasiNilaiMasukan($request->input('nilai')),
        ]);
        $data = $request->validate(
            $service->aturanValidasi($komponen),
            $service->pesanValidasi(),
        );
        $sebelumnyaFinalSts = $komponen->sts_manual_difinalisasi_pada !== null;
        $publikasiDibatalkan = $service->simpan(
            $request->user(),
            $komponen,
            $data,
        );

        return $this->tanpaCache([
            'pesan' => $publikasiDibatalkan
                ? 'Nilai berhasil disimpan. Karena ada perubahan, nilai kembali menjadi draf.'
                : 'Nilai berhasil disimpan sebagai draf.',
            'data' => [
                'publikasi_dibatalkan' => $publikasiDibatalkan,
                'finalisasi_sts_dibatalkan' => $sebelumnyaFinalSts && $komponen->fresh()->sts_manual_difinalisasi_pada === null,
            ],
        ]);
    }

    public function stsManual(Request $request, KomponenNilai $komponenNilai, StsManualService $service, InputNilaiService $input): JsonResponse
    {
        $komponen = $input->ambilKomponenDalamCakupan($request->user(), $komponenNilai->id);
        $data = $request->validate(['sidik' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'], 'difinalisasi' => ['required', 'boolean']]);
        $hasil = $service->tetapkan($request->user(), $komponen, $data['sidik'], $request->boolean('difinalisasi'));

        return $this->tanpaCache([
            'pesan' => $request->boolean('difinalisasi') ? 'Nilai STS manual difinalisasi untuk rapor.' : 'Finalisasi STS manual dibatalkan.',
            'data' => $service->respons($hasil),
        ]);
    }

    public function publikasikan(
        Request $request,
        GuruMataPelajaran $guruMataPelajaran,
        string $semester,
        InputNilaiService $service,
    ): JsonResponse {
        $publikasi = $service->publikasikan(
            $request->user(),
            $guruMataPelajaran,
            $semester,
        );

        return $this->tanpaCache([
            'pesan' => 'Nilai berhasil dipublikasikan dan dapat dilihat oleh siswa.',
            'data' => [
                'dipublikasikan' => true,
                'dipublikasikan_pada' => $publikasi->dipublikasikan_pada?->toIso8601String(),
            ],
        ]);
    }

    public function jadikanDraf(
        Request $request,
        GuruMataPelajaran $guruMataPelajaran,
        string $semester,
        InputNilaiService $service,
    ): JsonResponse {
        $service->jadikanDraf(
            $request->user(),
            $guruMataPelajaran,
            $semester,
        );

        return $this->tanpaCache([
            'pesan' => 'Publikasi dibatalkan. Nilai kembali menjadi draf.',
        ]);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
