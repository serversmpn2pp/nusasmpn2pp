<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PerangkatAjar;
use App\Services\Mobile\PerangkatAjarSayaMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PerangkatAjarSayaController extends Controller
{
    public function index(Request $request, PerangkatAjarSayaMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'tahun_pelajaran_id' => ['nullable', 'integer', Rule::exists('tahun_pelajaran', 'id')],
            'semester' => ['nullable', 'integer', Rule::in([1, 2])],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($request->user(), $filter)]);
    }

    public function show(
        Request $request,
        PerangkatAjar $perangkatAjar,
        PerangkatAjarSayaMobileService $service,
    ): JsonResponse {
        return $this->tanpaCache([
            'data' => $service->rincian($request->user(), $perangkatAjar),
        ]);
    }

    public function store(Request $request, PerangkatAjarSayaMobileService $service): JsonResponse
    {
        $data = $request->validate($this->aturanUnggah(true), $this->pesanValidasi());
        $file = $data['file_pdf'];
        unset($data['file_pdf']);
        $perangkatAjar = $service->tambah($request->user(), $data, $file);

        return $this->tanpaCache([
            'pesan' => 'Perangkat ajar berhasil diunggah dan menunggu pemeriksaan.',
            'data' => ['id' => (int) $perangkatAjar->id],
        ])->setStatusCode(201);
    }

    public function update(
        Request $request,
        PerangkatAjar $perangkatAjar,
        PerangkatAjarSayaMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'tingkat' => ['required', 'integer', Rule::in([7, 8, 9])],
            'judul' => ['required', 'string', 'max:180'],
            'catatan_guru' => ['nullable', 'string'],
            'file_pdf' => ['nullable', 'file', 'mimes:pdf', 'max:'.PerangkatAjarSayaMobileService::BATAS_PDF_KILOBYTE],
        ], $this->pesanValidasi());
        $file = $data['file_pdf'] ?? null;
        unset($data['file_pdf']);
        $service->ubah($request->user(), $perangkatAjar, $data, $file);

        return $this->tanpaCache([
            'pesan' => $file
                ? 'Revisi PDF berhasil diunggah dan kembali menunggu pemeriksaan.'
                : 'Informasi perangkat ajar berhasil diperbarui.',
        ]);
    }

    private function aturanUnggah(bool $fileWajib): array
    {
        return [
            'tahun_pelajaran_id' => ['required', 'integer', Rule::exists('tahun_pelajaran', 'id')],
            'semester' => ['required', 'integer', Rule::in([1, 2])],
            'mata_pelajaran_id' => ['required', 'integer', Rule::exists('mata_pelajaran', 'id')],
            'tingkat' => ['required', 'integer', Rule::in([7, 8, 9])],
            'jenis_perangkat_ajar_id' => ['required', 'integer', Rule::exists('jenis_perangkat_ajar', 'id')],
            'judul' => ['required', 'string', 'max:180'],
            'catatan_guru' => ['nullable', 'string'],
            'file_pdf' => [$fileWajib ? 'required' : 'nullable', 'file', 'mimes:pdf', 'max:'.PerangkatAjarSayaMobileService::BATAS_PDF_KILOBYTE],
        ];
    }

    private function pesanValidasi(): array
    {
        return [
            'file_pdf.required' => 'File PDF wajib dipilih.',
            'file_pdf.file' => 'Berkas yang dipilih tidak dapat dibaca sebagai file.',
            'file_pdf.mimes' => 'Perangkat ajar harus berupa file PDF.',
            'file_pdf.max' => 'Ukuran PDF melebihi batas 10 MB.',
            'file_pdf.uploaded' => 'PDF gagal diunggah. Periksa ukuran file dan batas unggah server.',
        ];
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
