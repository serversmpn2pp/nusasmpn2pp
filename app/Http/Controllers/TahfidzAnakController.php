<?php

namespace App\Http\Controllers;

use App\Models\AnggotaTahfidz;
use App\Models\KelulusanTahfidz;
use App\Models\OrangTuaWali;
use App\Models\PublikasiRaporTahfidz;
use App\Services\Tahfidz\CapaianTahfidzService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TahfidzAnakController extends Controller
{
    public function index(Request $request, CapaianTahfidzService $capaian)
    {
        $orangTua = $this->orangTua($request);
        $filter = $request->validate(['siswa' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(['sudah', 'belum'])]]);
        $anak = $orangTua->siswa()->orderBy('nama_lengkap')->get(['siswa.id', 'siswa.nama_lengkap']);
        $siswa = isset($filter['siswa']) ? $anak->firstWhere('id', (int) $filter['siswa'])
            : ($anak->firstWhere('id', $orangTua->siswa_acuan_username_id) ?? $anak->first());
        abort_if(isset($filter['siswa']) && ! $siswa, 404);

        $ikutProgram = $siswa && AnggotaTahfidz::where('siswa_id', $siswa->id)->exists();
        $data = [];
        if ($ikutProgram) {
            $status = $filter['status'] ?? '';
            $data = $capaian->perkembanganAnak($siswa->id);
            $data['baris'] = $data['baris']->filter(fn ($item) => $status === '' || ($item['terbaru'] !== null) === ($status === 'sudah'));
            $data['status'] = $status;
            $data['rapor'] = PublikasiRaporTahfidz::where('siswa_id', $siswa->id)->whereNotNull('dipublikasikan_pada')->latest('dipublikasikan_pada')->get();
            $data['sertifikat'] = KelulusanTahfidz::where('siswa_id', $siswa->id)->whereNotNull('diterbitkan_pada')
                ->whereNotNull('dibagikan_pada')->with('wisuda')->first();
        }

        return response()->view('tahfidz.anak', ['anak' => $anak, 'siswa' => $siswa, 'ikutProgram' => $ikutProgram, ...$data])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function rapor(Request $request, int $id)
    {
        $anak = $this->orangTua($request)->siswa()->pluck('siswa.id');
        $rapor = PublikasiRaporTahfidz::whereIn('siswa_id', $anak)->whereNotNull('dipublikasikan_pada')->findOrFail($id);

        return response()->view('tahfidz.rapor-cetak', ['dokumen' => $rapor->salinan,
            'kembali' => route('tahfidz-anak.index', ['siswa' => $rapor->siswa_id]).'#tf-dokumen-anak',
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function sertifikat(Request $request, int $id)
    {
        $anak = $this->orangTua($request)->siswa()->pluck('siswa.id');
        $lulus = KelulusanTahfidz::whereIn('siswa_id', $anak)->whereNotNull('diterbitkan_pada')
            ->whereNotNull('dibagikan_pada')->with('wisuda')->findOrFail($id);

        return response()->view('tahfidz.sertifikat-cetak', ['lulus' => $lulus,
            'kembali' => route('tahfidz-anak.index', ['siswa' => $lulus->siswa_id]).'#tf-dokumen-anak',
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    private function orangTua(Request $request): OrangTuaWali
    {
        $akun = $request->user();
        abort_unless($akun?->aktif && $akun->akunOrangTua(), 403);

        return $akun->orangTuaWali()->firstOrFail();
    }
}
