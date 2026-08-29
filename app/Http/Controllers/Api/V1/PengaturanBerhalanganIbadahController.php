<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\PenugasanPendampingIbadahSiswi;
use App\Models\TahunPelajaran;
use App\Services\Mobile\PengaturanBerhalanganIbadahMobileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PengaturanBerhalanganIbadahController extends Controller
{
    public function index(PengaturanBerhalanganIbadahMobileService $service): JsonResponse
    {
        return $this->tanpaCache(['data' => $service->data()]);
    }

    public function update(
        Request $request,
        PengaturanBerhalanganIbadahMobileService $service,
    ): JsonResponse {
        $data = $request->validate([
            'batas_hari_konfirmasi' => ['required', 'integer', 'min:1', 'max:30'],
            'aktif' => ['required', 'boolean'],
        ]);
        $service->simpanPengaturan($data, (int) $request->user()->id);

        return $this->tanpaCache(['pesan' => 'Pengaturan berhalangan berhasil disimpan.']);
    }

    public function storePendamping(
        Request $request,
        PengaturanBerhalanganIbadahMobileService $service,
    ): JsonResponse {
        $tahunPelajaran = TahunPelajaran::query()
            ->where('aktif', true)
            ->orderByDesc('tanggal_mulai')
            ->first();
        $data = $request->validate([
            'pegawai_id' => [
                'required',
                'integer',
                Rule::exists('pegawai', 'id')->where(fn ($query) => $query
                    ->where('aktif', true)
                    ->where('jenis_kelamin', 'P')),
            ],
            'semua_kelas' => ['required', 'boolean'],
            'kelas_ids' => ['nullable', 'array'],
            'kelas_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('kelas', 'id')->where(fn ($query) => $query
                    ->when($tahunPelajaran, fn ($query) => $query->where('tahun_pelajaran_id', $tahunPelajaran->id))
                    ->where('aktif', true)),
            ],
        ], [
            'pegawai_id.exists' => 'Pendamping harus merupakan guru perempuan atau Guru PL perempuan yang masih aktif.',
        ]);
        $service->simpanPendamping($data, (int) $request->user()->id);

        return $this->tanpaCache(['pesan' => 'Pendamping ibadah siswi berhasil disimpan.'])
            ->setStatusCode(201);
    }

    public function destroyPendamping(
        Request $request,
        PenugasanPendampingIbadahSiswi $penugasanPendampingIbadahSiswi,
        PengaturanBerhalanganIbadahMobileService $service,
    ): JsonResponse {
        $service->nonaktifkanPendamping(
            $penugasanPendampingIbadahSiswi,
            (int) $request->user()->id,
        );

        return $this->tanpaCache([
            'pesan' => 'Pendamping telah dinonaktifkan. Riwayat penugasannya tetap tersimpan.',
        ]);
    }

    private function tanpaCache(array $data): JsonResponse
    {
        return response()->json($data)
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate');
    }
}
