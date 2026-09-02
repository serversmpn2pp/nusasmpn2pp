<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PenguranganPoinSiswa;
use App\Services\Mobile\PenguranganPoinSiswaMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PenguranganPoinSiswaController extends Controller
{
    public function index(Request $request, PenguranganPoinSiswaMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'kata_kunci' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['semua', ...array_keys(PenguranganPoinSiswa::DAFTAR_STATUS)])],
            'tahun_pelajaran_id' => ['nullable', 'integer', Rule::exists('tahun_pelajaran', 'id')],
            'kelas_id' => ['nullable', 'integer', Rule::exists('kelas', 'id')],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:30'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($request->user(), $filter)]);
    }

    public function store(Request $request, PenguranganPoinSiswaMobileService $service): JsonResponse
    {
        $data = $request->validate([
            'siswa_id' => ['required', 'integer', Rule::exists('siswa', 'id')->where('aktif', true)],
            'tanggal_kegiatan' => ['required', 'date'],
            'jenis_kegiatan' => ['required', 'string', 'max:160'],
            'deskripsi' => ['nullable', 'string', 'max:5000'],
            'poin_pengurangan' => ['required', 'integer', Rule::in(PenguranganPoinSiswaMobileService::DAFTAR_POIN)],
            'bukti' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:4096'],
        ]);
        $pengurangan = $service->buat($request->user(), $data, $request->file('bukti'));

        return $this->tanpaCache([
            'message' => 'Penghargaan berhasil diajukan dan menunggu persetujuan.',
            'data' => $service->ringkas($pengurangan, $request->user()),
        ], 201);
    }

    public function decide(
        Request $request,
        PenguranganPoinSiswa $penguranganPoinSiswa,
        PenguranganPoinSiswaMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'keputusan' => ['required', Rule::in(['disetujui', 'ditolak'])],
            'catatan_keputusan' => ['nullable', 'string', 'max:5000'],
        ]);
        $hasil = $service->putuskan(
            $request->user(),
            $penguranganPoinSiswa,
            $data['keputusan'],
            $data['catatan_keputusan'] ?? null,
        );

        return $this->tanpaCache([
            'message' => $data['keputusan'] === 'disetujui'
                ? "Pengurangan disetujui. {$hasil['diterapkan']} poin diterapkan tanpa membuat saldo negatif."
                : 'Pengajuan pengurangan poin ditolak.',
            'data' => $service->ringkas($hasil['pengurangan'], $request->user()),
        ]);
    }

    public function evidence(
        Request $request,
        PenguranganPoinSiswa $penguranganPoinSiswa,
        PenguranganPoinSiswaMobileService $service,
    ): StreamedResponse {
        abort_unless($service->bolehMelihat($request->user()), 403);
        abort_unless($penguranganPoinSiswa->bukti, 404);
        abort_unless(Storage::disk('public')->exists($penguranganPoinSiswa->bukti), 404);
        $ekstensi = pathinfo($penguranganPoinSiswa->bukti, PATHINFO_EXTENSION);

        return Storage::disk('public')->download(
            $penguranganPoinSiswa->bukti,
            'Bukti penghargaan siswa.'.$ekstensi,
            ['Cache-Control' => 'no-store, no-cache, must-revalidate'],
        );
    }

    private function tanpaCache(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
