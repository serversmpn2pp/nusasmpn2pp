<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Services\Mobile\StsMobileService;
use App\Services\Nilai\LampiranPerilakuStsService;
use App\Services\Nilai\MapelRaporStsService;
use App\Services\Nilai\PengelolaanRaporStsService;
use App\Services\Nilai\RaporStsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;

class RaporStsController extends Controller
{
    public function index(Request $request, StsMobileService $service): JsonResponse
    {
        $filter = $request->validate([
            'kegiatan_id' => ['nullable', 'integer', 'min:1'], 'kelas_id' => ['nullable', 'integer', 'min:1'],
            'anggota_id' => ['nullable', 'integer', 'min:1'],
        ]);

        return $this->json($service->rapor($request->user(), $filter));
    }

    public function pengaturan(Request $request, KegiatanUjianCbt $kegiatan, Kelas $kelas, PengelolaanRaporStsService $service): JsonResponse
    {
        $service->pengaturan($request->user(), $kegiatan, $kelas, $request->all());

        return $this->json(['pesan' => 'Periode rapor disimpan. Muat ulang dan periksa kehadiran sebelum mencetak.']);
    }

    public function kehadiran(Request $request, KegiatanUjianCbt $kegiatan, Kelas $kelas, PengelolaanRaporStsService $service): JsonResponse
    {
        $service->kehadiran($request->user(), $kegiatan, $kelas, $request->all());

        return $this->json(['pesan' => 'Pemeriksaan kehadiran rapor disimpan. Presensi harian siswa tidak diubah.']);
    }

    public function pengecualian(Request $request, KegiatanUjianCbt $kegiatan, Kelas $kelas, PengelolaanRaporStsService $service): JsonResponse
    {
        $service->pengecualian($request->user(), $kegiatan, $kelas, $request->all());

        return $this->json(['pesan' => 'Keterangan tidak mengikuti STS disimpan.']);
    }

    public function mapel(Request $request, KegiatanUjianCbt $kegiatan, Kelas $kelas, RaporStsService $rapor, MapelRaporStsService $service): JsonResponse
    {
        abort_unless(MapelRaporStsService::dapatMengatur($request->user()), 403);
        $rapor->pastikanCakupan($request->user(), $kegiatan, $kelas);
        $data = $request->validate([
            'versi_mapel' => ['required', 'integer', 'min:0'],
            'sidik_mapel' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'mapel_ids' => ['required', 'array', 'min:1', 'max:100'],
            'mapel_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'alasan_mapel' => ['required', 'string', 'max:500', 'regex:/\S/'],
        ]);
        $service->simpan($request->user(), $kegiatan, (int) $kelas->tingkat, $data);

        return $this->json(['pesan' => 'Pilihan mapel STS disimpan untuk seluruh kelas pada tingkat ini.']);
    }

    public function cetak(Request $request, KegiatanUjianCbt $kegiatan, Kelas $kelas, RaporStsService $service): Response
    {
        $service->pastikanCakupan($request->user(), $kegiatan, $kelas);
        $data = $request->validate(['anggota_id' => ['nullable', 'integer', 'min:1'], 'pratinjau' => ['nullable', 'boolean'], 'perilaku' => ['nullable', 'boolean']]);
        $laporan = $service->bangun($kegiatan, $kelas);
        if (isset($data['anggota_id'])) {
            $laporan['baris'] = $laporan['baris']->where('anggota.id', (int) $data['anggota_id'])->values();
        }
        abort_if($laporan['baris']->isEmpty(), 404);
        $pratinjau = (bool) ($data['pratinjau'] ?? false);
        if (! $pratinjau && ! $laporan['baris']->every(fn ($b) => $b['siap'])) {
            throw ValidationException::withMessages(['cetak' => 'Simpan periode rapor, tetapkan wali kelas, dan periksa kehadiran. Periode harus sudah berakhir. Nilai lengkap tidak diwajibkan untuk mencetak.']);
        }
        $lampiran = null;
        if ($data['perilaku'] ?? false) {
            $lampiran = app(LampiranPerilakuStsService::class)->konteks($kegiatan, $kelas);
            $lampiran['baris'] = $lampiran['baris']->whereIn('anggota.id', $laporan['baris']->pluck('anggota.id'))->keyBy('anggota.id');
            if (! $lampiran['baris']->every(fn ($b) => $b['siap'])) {
                throw ValidationException::withMessages(['cetak' => 'Lampiran perilaku belum diperiksa atau datanya berubah. Guru BK perlu memeriksa kembali sebelum rapor gabungan dapat dibuka.']);
            }
        }

        return response()->view('rapor-sts.cetak', [...$laporan, 'pratinjau' => $pratinjau, 'lampiran' => $lampiran])
            ->header('Cache-Control', 'private, no-store');
    }

    private function json(array $data): JsonResponse
    {
        return response()->json(['data' => $data])->header('Cache-Control', 'private, no-store');
    }
}
