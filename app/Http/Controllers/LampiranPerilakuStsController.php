<?php

namespace App\Http\Controllers;

use App\Models\KegiatanUjianCbt;
use App\Models\Kelas;
use App\Services\Nilai\LampiranPerilakuStsService;
use Illuminate\Http\Request;

class LampiranPerilakuStsController extends Controller
{
    public function index(Request $request, LampiranPerilakuStsService $service)
    {
        $data = $request->validate(['kegiatan_id' => ['nullable', 'integer'], 'kelas_id' => ['nullable', 'integer']]);
        $daftarKegiatan = KegiatanUjianCbt::whereHas('jenisUjianCbt', fn ($q) => $q->where('kode', 'STS'))
            ->whereIn('tahun_pelajaran_id', $service->kelasDalamCakupan($request->user())->select('tahun_pelajaran_id'))
            ->with('tahunPelajaran')->orderByDesc('tanggal_mulai')->get();
        $kegiatan = isset($data['kegiatan_id']) ? $daftarKegiatan->firstWhere('id', $data['kegiatan_id']) : $daftarKegiatan->first();
        abort_if(isset($data['kegiatan_id']) && ! $kegiatan, 404);
        $daftarKelas = $kegiatan ? $service->kelasDalamCakupan($request->user())->where('tahun_pelajaran_id', $kegiatan->tahun_pelajaran_id)
            ->orderBy('tingkat')->orderBy('nama')->get() : collect();
        $kelas = isset($data['kelas_id']) ? $daftarKelas->firstWhere('id', $data['kelas_id']) : $daftarKelas->first();
        abort_if(isset($data['kelas_id']) && ! $kelas, 404);
        $konteks = $kelas && $kegiatan ? $service->konteks($kegiatan, $kelas) : null;

        return response()->view('rapor-sts.perilaku', compact('daftarKegiatan', 'daftarKelas', 'kegiatan', 'kelas', 'konteks'))
            ->header('Cache-Control', 'private, no-store');
    }

    public function simpan(Request $request, KegiatanUjianCbt $kegiatan, Kelas $kelas, LampiranPerilakuStsService $service)
    {
        $service->pastikanCakupan($request->user(), $kegiatan, $kelas);
        $data = $request->validate([
            'anggota_id' => ['required', 'integer'], 'versi' => ['required', 'integer', 'min:0'],
            'sidik_sumber' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'guru_bk_id' => ['required', 'integer'], 'wakil_kesiswaan_id' => ['required', 'integer'],
            'baris' => ['nullable', 'array', 'max:1000'],
            'baris.*.kejadian' => ['required', 'string', 'max:200', 'regex:/\S/'],
            'baris.*.tindakan' => ['required', 'string', 'max:200', 'regex:/\S/'],
            'catatan' => ['nullable', 'string', 'max:600'],
            'diperiksa' => ['required', 'accepted'],
        ]);
        $service->simpan($request->user(), $kegiatan, $kelas, $data);

        return redirect()->route('lampiran-perilaku-sts.index', ['kegiatan_id' => $kegiatan->id, 'kelas_id' => $kelas->id])
            ->with('berhasil', 'Lampiran perilaku siswa telah diperiksa dan disimpan.')->with('perilaku_anggota', $data['anggota_id']);
    }

    public function kolektif(Request $request, KegiatanUjianCbt $kegiatan, Kelas $kelas, LampiranPerilakuStsService $service)
    {
        $service->pastikanCakupan($request->user(), $kegiatan, $kelas);
        // JSON avoids PHP's max_input_vars truncating a class with many incident rows.
        $request->validate(['siswa_json' => ['required', 'string', 'json', 'max:2097152']]);
        $request->merge(['siswa' => json_decode($request->input('siswa_json'), true)]);
        $data = $request->validate([
            'anggota_ids' => ['required', 'array', 'min:1', 'max:500'],
            'anggota_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'guru_bk_id' => ['required', 'integer'], 'wakil_kesiswaan_id' => ['required', 'integer'],
            'diperiksa' => ['required', 'accepted'],
            'siswa' => ['required', 'array', 'min:1', 'max:500'],
            'siswa.*' => ['required', 'array'],
            'siswa.*.versi' => ['required', 'integer', 'min:0'],
            'siswa.*.sidik_sumber' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'siswa.*.baris' => ['nullable', 'array', 'max:1000'],
            'siswa.*.baris.*.kejadian' => ['required', 'string', 'max:200', 'regex:/\S/'],
            'siswa.*.baris.*.tindakan' => ['required', 'string', 'max:200', 'regex:/\S/'],
            'siswa.*.catatan' => ['nullable', 'string', 'max:600'],
        ]);
        $service->simpanKolektif($request->user(), $kegiatan, $kelas, $data);

        return redirect()->route('lampiran-perilaku-sts.index', ['kegiatan_id' => $kegiatan->id, 'kelas_id' => $kelas->id])
            ->with('berhasil', count($data['anggota_ids']).' lampiran perilaku siswa telah diperiksa dan disimpan secara kolektif.');
    }
}
