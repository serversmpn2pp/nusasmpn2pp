<?php

namespace App\Http\Controllers;

use App\Models\SoalUjianCbt;
use App\Models\UjianCbt;
use App\Services\Cbt\AnalisisSoalCbtService;
use Illuminate\Http\Request;

class AnalisisSoalCbtController extends Controller
{
    public function index(Request $request, UjianCbt $ujianCbt, AnalisisSoalCbtService $analisis)
    {
        $data = $request->validate([
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
        ]);
        $kelasId = $data['kelas_id'] ?? null;
        $ujianCbt->load(['mataPelajaran', 'kelasUjianCbt.kelas']);

        return view('ujian-cbt.hasil.analisis-soal', [
            'ujianCbt' => $ujianCbt,
            'kelasPeserta' => $ujianCbt->kelasUjianCbt->sortBy(fn ($item) => $item->kelas?->nama),
            'kelasId' => $kelasId,
            'analisis' => $analisis->untukUjian($ujianCbt, $kelasId),
        ]);
    }

    public function show(Request $request, UjianCbt $ujianCbt, SoalUjianCbt $soalUjianCbt, AnalisisSoalCbtService $analisis)
    {
        abort_unless((int) $soalUjianCbt->ujian_cbt_id === (int) $ujianCbt->id, 404);
        $data = $request->validate([
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
        ]);
        $kelasId = $data['kelas_id'] ?? null;
        $ujianCbt->load(['mataPelajaran', 'kelasUjianCbt.kelas']);
        $hasil = $analisis->untukUjian($ujianCbt, $kelasId);
        $item = $hasil['soal']->first(fn ($baris) => $baris['soal']->id === $soalUjianCbt->id);
        abort_unless($item && ($item['rincian_pilihan']['tersedia'] || $item['rincian_pemetaan']['tersedia']), 404);

        return view('ujian-cbt.hasil.rincian-soal', [
            'ujianCbt' => $ujianCbt,
            'kelasId' => $kelasId,
            'namaKelas' => $kelasId ? $ujianCbt->kelasUjianCbt->firstWhere('kelas_id', $kelasId)?->kelas?->nama : null,
            'item' => $item,
        ]);
    }
}
