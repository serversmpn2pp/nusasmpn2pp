<?php

namespace App\Http\Controllers;

use App\Models\DokumenPendukungKemitraan;
use App\Models\KerjaSamaHumas;
use App\Services\Humas\DokumenPendukungKemitraanService;
use App\Services\Mobile\HumasMobileService;
use Illuminate\Http\Request;

class PendukungKemitraanController extends Controller
{
    public function __construct(private readonly DokumenPendukungKemitraanService $service) {}

    public function show(Request $r, KerjaSamaHumas $mou)
    {
        $this->service->akses($r->user());
        $p = $r->validate(['halaman' => ['nullable', 'integer', 'min:1'], 'per_halaman' => ['nullable', 'integer', 'min:1', 'max:50'], 'kata_kunci' => ['nullable', 'string', 'max:120']]);
        $daftar = DokumenPendukungKemitraan::where('kerja_sama_humas_id', $mou->id)->with('berkas.dokumen')->orderByDesc('id')->paginate($r->routeIs('api.v1.*') ? ($p['per_halaman'] ?? 20) : 15, ['*'], 'halaman', $p['halaman'] ?? 1)->withQueryString();
        if ($r->routeIs('api.v1.*')) {
            return $this->json(app(HumasMobileService::class)->paginasi($daftar, fn ($l) => $this->data($l))
                + ['mou' => $mou->only(['id', 'judul', 'versi']), 'jenis' => DokumenPendukungKemitraan::JENIS,
                    'dapat_kelola' => $r->user()->memilikiIzin('kemitraan_humas.kelola') && $mou->status !== 'diakhiri']);
        }

        return response()->view('kemitraan-humas.pendukung', ['mou' => $mou, 'daftar' => $daftar,
            'pilihan' => $r->user()->memilikiIzin('kemitraan_humas.kelola') ? $this->service->pilihan($r) : collect()], 200, ['Cache-Control' => 'private, no-store']);
    }

    public function pilihan(Request $r, KerjaSamaHumas $mou)
    {
        return $this->json(['items' => $this->service->pilihan($r)->map(fn ($d) => ['id' => $d->id, 'judul' => $d->judul, 'id_versi' => $d->riwayat->first()->id, 'versi' => $d->riwayat->first()->versi])->all()]);
    }

    public function store(Request $r, KerjaSamaHumas $mou)
    {
        $this->service->ubah($r, $mou);

        return $this->selesai($r, $mou);
    }

    public function destroy(Request $r, KerjaSamaHumas $mou, DokumenPendukungKemitraan $lampiran)
    {
        $this->service->ubah($r, $mou, $lampiran);

        return $this->selesai($r, $mou);
    }

    private function data(DokumenPendukungKemitraan $l): array
    {
        return ['id' => $l->id, 'id_dokumen' => $l->berkas->dokumen_humas_id, 'id_versi' => $l->riwayat_dokumen_humas_id,
            'judul' => $l->berkas->dokumen->judul, 'versi_berkas' => $l->berkas->versi, 'jenis' => $l->jenis, 'nama_berkas' => $l->berkas->nama_file_asli,
            'catatan' => $l->catatan, 'dilepas_pada' => $l->dilepas_pada?->toIso8601String(),
            'url_unduh' => route('api.v1.humas.dokumen.riwayat.unduh', [$l->berkas->dokumen_humas_id, $l->riwayat_dokumen_humas_id])];
    }

    private function selesai(Request $r, KerjaSamaHumas $mou)
    {
        return $r->routeIs('api.v1.*') ? $this->json(['id' => $mou->id, 'versi' => $mou->fresh()->versi])
            : redirect()->route('kemitraan-humas.pendukung', $mou)->with('berhasil', 'Dokumen pendukung diperbarui. Berkas asli tetap tersimpan.');
    }

    private function json(array $data)
    {
        return response()->json(['data' => $data])->header('Cache-Control', 'private, no-store');
    }
}
