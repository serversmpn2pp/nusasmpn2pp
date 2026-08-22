<?php

namespace App\Http\Controllers;

use App\Models\KegiatanUjianCbt;
use App\Models\PanitiaUjianCbt;
use App\Models\Pegawai;
use App\Services\Cbt\SinkronkanPeranPanitiaUjian;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PanitiaUjianTerpusatController extends Controller
{
    public function store(Request $request, KegiatanUjianCbt $kegiatanUjianCbt, SinkronkanPeranPanitiaUjian $sinkronkanPeran)
    {
        $data = $request->validate([
            'pegawai_id' => ['required', 'integer', Rule::exists('pegawai', 'id')->where('aktif', true)],
            'jabatan' => ['required', Rule::in(array_keys(PanitiaUjianCbt::DAFTAR_JABATAN))],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);
        $pegawai = Pegawai::findOrFail($data['pegawai_id']);

        $kegiatanUjianCbt->panitiaUjianCbt()->updateOrCreate(
            ['pegawai_id' => $pegawai->id],
            [
                'jabatan' => $data['jabatan'],
                'aktif' => true,
                'catatan' => filled($data['catatan'] ?? null) ? trim($data['catatan']) : null,
                'ditugaskan_oleh_pengguna_id' => $request->user()?->id,
            ],
        );
        $sinkronkanPeran->sinkronkan($pegawai);

        return back()->with('berhasil', 'Panitia ujian berhasil ditambahkan.');
    }

    public function destroy(KegiatanUjianCbt $kegiatanUjianCbt, PanitiaUjianCbt $panitiaUjianCbt, SinkronkanPeranPanitiaUjian $sinkronkanPeran)
    {
        abort_unless((int) $panitiaUjianCbt->kegiatan_ujian_cbt_id === (int) $kegiatanUjianCbt->id, 404);
        $pegawai = $panitiaUjianCbt->pegawai;
        $panitiaUjianCbt->delete();
        $sinkronkanPeran->sinkronkan($pegawai);

        return back()->with('berhasil', 'Penugasan panitia berhasil dihapus.');
    }
}
