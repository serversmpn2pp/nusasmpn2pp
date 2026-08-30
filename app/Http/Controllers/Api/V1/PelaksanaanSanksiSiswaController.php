<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\BuktiPelaksanaanSanksi;
use App\Models\SanksiPoinSiswa;
use App\Services\Mobile\PelaksanaanSanksiSiswaMobileService;
use App\Services\Pembinaan\AksesSanksiPoinService;
use App\Services\Pembinaan\SimpanBuktiPelaksanaanSanksiService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PelaksanaanSanksiSiswaController extends Controller
{
    public function index(Request $request, PelaksanaanSanksiSiswaMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'kata_kunci' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', Rule::in(['aktif', 'semua', ...array_keys(SanksiPoinSiswa::DAFTAR_STATUS)])],
            'tahun_pelajaran_id' => ['nullable', 'integer', Rule::exists('tahun_pelajaran', 'id')],
            'kelas_id' => ['nullable', 'integer', Rule::exists('kelas', 'id')],
            'halaman' => ['nullable', 'integer', 'min:1'],
            'per_halaman' => ['nullable', 'integer', 'min:5', 'max:30'],
        ]);

        return $this->tanpaCache(['data' => $service->daftar($request->user(), $filter)]);
    }

    public function show(
        Request $request,
        SanksiPoinSiswa $sanksiPoinSiswa,
        PelaksanaanSanksiSiswaMobileService $service,
    ): JsonResponse {
        return $this->tanpaCache(['data' => $service->rincian($request->user(), $sanksiPoinSiswa)]);
    }

    public function update(
        Request $request,
        SanksiPoinSiswa $sanksiPoinSiswa,
        PelaksanaanSanksiSiswaMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(SanksiPoinSiswa::DAFTAR_STATUS))],
            'petugas_pegawai_id' => ['nullable', 'integer', Rule::exists('pegawai', 'id')->where('aktif', true)],
            'batas_pelaksanaan' => ['nullable', 'date'],
            'catatan' => ['nullable', 'string', 'max:5000'],
            'hasil_pelaksanaan' => ['nullable', 'string', 'max:10000'],
        ]);
        $service->perbarui($request->user(), $sanksiPoinSiswa, $data);

        return $this->tanpaCache([
            'message' => 'Pelaksanaan sanksi berhasil diperbarui.',
            'data' => $service->rincian($request->user(), $sanksiPoinSiswa->fresh()),
        ]);
    }

    public function storeEvidence(
        Request $request,
        SanksiPoinSiswa $sanksiPoinSiswa,
        AksesSanksiPoinService $akses,
        SimpanBuktiPelaksanaanSanksiService $penyimpanan,
        PelaksanaanSanksiSiswaMobileService $service,
    ): JsonResponse {
        abort_unless($akses->bolehKelola($request->user(), $sanksiPoinSiswa), 403);
        $data = $request->validate([
            'bukti_sanksi' => ['required', 'array', 'min:1', 'max:5'],
            'bukti_sanksi.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
            'keterangan_bukti' => ['nullable', 'string', 'max:500'],
        ]);
        $jumlah = $penyimpanan->simpanBanyak(
            $sanksiPoinSiswa,
            $request->file('bukti_sanksi', []),
            $data['keterangan_bukti'] ?? null,
            $request->user()?->id,
        );

        return $this->tanpaCache([
            'message' => $jumlah.' bukti pelaksanaan berhasil ditambahkan.',
            'data' => $service->rincian($request->user(), $sanksiPoinSiswa->fresh()),
        ], 201);
    }

    public function evidence(
        Request $request,
        BuktiPelaksanaanSanksi $buktiPelaksanaanSanksi,
        AksesSanksiPoinService $akses,
    ): StreamedResponse {
        abort_unless($akses->bolehLihat($request->user(), $buktiPelaksanaanSanksi->sanksiPoinSiswa), 403);
        abort_unless(Storage::disk('local')->exists($buktiPelaksanaanSanksi->lokasi_file), 404);

        return Storage::disk('local')->download(
            $buktiPelaksanaanSanksi->lokasi_file,
            $buktiPelaksanaanSanksi->nama_file_asli,
            ['Cache-Control' => 'no-store, no-cache, must-revalidate'],
        );
    }

    public function destroyEvidence(
        Request $request,
        BuktiPelaksanaanSanksi $buktiPelaksanaanSanksi,
        AksesSanksiPoinService $akses,
        SimpanBuktiPelaksanaanSanksiService $penyimpanan,
        PelaksanaanSanksiSiswaMobileService $service,
    ): JsonResponse {
        $sanksi = $buktiPelaksanaanSanksi->sanksiPoinSiswa;
        abort_unless($akses->bolehKelola($request->user(), $sanksi), 403);
        $penyimpanan->hapus($buktiPelaksanaanSanksi, $request->user()?->id);

        return $this->tanpaCache([
            'message' => 'Bukti pelaksanaan berhasil dihapus.',
            'data' => $service->rincian($request->user(), $sanksi->fresh()),
        ]);
    }

    private function tanpaCache(array $data, int $status = 200): JsonResponse
    {
        return response()->json($data, $status)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
