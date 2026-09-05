<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\UjianCbt;
use App\Services\Mobile\AsesmenKelasMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AsesmenKelasController extends Controller
{
    public function index(Request $request, AsesmenKelasMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'kata_kunci' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['semua', ...array_keys(UjianCbt::DAFTAR_STATUS)])],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:30'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($request->user(), $filter)]);
    }

    public function show(Request $request, UjianCbt $ujianCbt, AsesmenKelasMobileService $service): JsonResponse
    {
        return $this->tanpaCache(['data' => $service->rincian($request->user(), $ujianCbt)]);
    }

    public function store(Request $request, AsesmenKelasMobileService $service): JsonResponse
    {
        $asesmen = $service->tambah($request->user(), $request->validate($this->aturanValidasi()));

        return $this->tanpaCache([
            'pesan' => 'Asesmen kelas berhasil dibuat. Pilih soal agar asesmen siap digunakan.',
            'data' => $service->rincian($request->user(), $asesmen),
        ])->setStatusCode(201);
    }

    public function update(
        Request $request,
        UjianCbt $ujianCbt,
        AsesmenKelasMobileService $service,
    ): JsonResponse {
        $service->ubah($request->user(), $ujianCbt, $request->validate($this->aturanValidasi()));

        return $this->tanpaCache([
            'pesan' => 'Pengaturan asesmen kelas berhasil diperbarui.',
            'data' => $service->rincian($request->user(), $ujianCbt->fresh()),
        ]);
    }

    public function destroy(Request $request, UjianCbt $ujianCbt, AsesmenKelasMobileService $service): JsonResponse
    {
        $service->nonaktifkan($request->user(), $ujianCbt);

        return $this->tanpaCache(['pesan' => 'Asesmen kelas berhasil dinonaktifkan.']);
    }

    public function questions(Request $request, UjianCbt $ujianCbt, AsesmenKelasMobileService $service): JsonResponse
    {
        return $this->tanpaCache(['data' => $service->daftarSoal($request->user(), $ujianCbt)]);
    }

    public function updateQuestions(
        Request $request,
        UjianCbt $ujianCbt,
        AsesmenKelasMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'soal' => ['nullable', 'array', 'max:200'],
            'soal.*.id' => ['required', 'integer', Rule::exists('soal_cbt', 'id')],
            'soal.*.bobot' => ['required', 'numeric', 'min:0.25', 'max:100'],
        ]);
        $service->simpanSoal($request->user(), $ujianCbt, $data['soal'] ?? []);

        return $this->tanpaCache([
            'pesan' => 'Pilihan soal asesmen berhasil disimpan.',
            'data' => $service->daftarSoal($request->user(), $ujianCbt->fresh()),
        ]);
    }

    private function aturanValidasi(): array
    {
        return [
            'kelompok_pengajaran' => ['required', 'string', 'max:120'],
            'nama' => ['required', 'string', 'max:180'],
            'semester' => ['required', Rule::in(['ganjil', 'genap'])],
            'tanggal_mulai' => ['required', 'date'],
            'tanggal_selesai' => ['required', 'date', 'after_or_equal:tanggal_mulai'],
            'durasi_menit' => ['required', 'integer', 'min:10', 'max:300'],
            'jumlah_soal' => ['required', 'integer', 'min:1', 'max:120'],
            'status' => ['required', Rule::in(['draft', 'terjadwal', 'berlangsung', 'selesai'])],
            'acak_soal' => ['required', 'boolean'],
            'acak_jawaban' => ['required', 'boolean'],
            'batasi_satu_perangkat' => ['required', 'boolean'],
            'deteksi_pindah_tab' => ['required', 'boolean'],
            'wajib_fullscreen' => ['sometimes', 'boolean'],
            'blokir_tangkapan_layar' => ['sometimes', 'boolean'],
            'toleransi_pindah_aplikasi_detik' => ['sometimes', 'integer', 'min:1', 'max:60'],
            'batas_pindah_aplikasi' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'tindakan_pindah_aplikasi' => ['sometimes', Rule::in(['catat', 'tahan'])],
            'tampilkan_hasil' => ['required', 'boolean'],
            'petunjuk' => ['nullable', 'string', 'max:3000'],
            'kelas_peserta' => ['required', 'array', 'min:1'],
            'kelas_peserta.*.kelas_id' => ['required', 'integer', Rule::exists('kelas', 'id')],
            'kelas_peserta.*.komponen_nilai_id' => ['required', 'string', 'max:30'],
        ];
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
