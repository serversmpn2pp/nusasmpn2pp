<?php

namespace App\Http\Controllers;

use App\Models\UjianCbt;
use App\Services\Cbt\FinalisasiHasilUjianTerpusatService;
use Illuminate\Http\Request;

class FinalisasiHasilUjianTerpusatController extends Controller
{
    public function finalisasi(
        Request $request,
        UjianCbt $ujianCbt,
        FinalisasiHasilUjianTerpusatService $service,
    ) {
        [$kegiatan, $jadwal] = $this->konteks($ujianCbt);
        $hasil = $service->finalisasi($request->user(), $kegiatan, $jadwal);

        return back()->with('berhasil', $hasil['pesan']);
    }

    public function batalkanFinalisasi(
        Request $request,
        UjianCbt $ujianCbt,
        FinalisasiHasilUjianTerpusatService $service,
    ) {
        [$kegiatan, $jadwal] = $this->konteks($ujianCbt);
        $hasil = $service->batalkanFinalisasi($request->user(), $kegiatan, $jadwal);

        return back()->with('berhasil', $hasil['pesan']);
    }

    public function publikasikan(
        Request $request,
        UjianCbt $ujianCbt,
        FinalisasiHasilUjianTerpusatService $service,
    ) {
        [$kegiatan, $jadwal] = $this->konteks($ujianCbt);
        $hasil = $service->publikasikan($request->user(), $kegiatan, $jadwal);

        return back()->with('berhasil', $hasil['pesan']);
    }

    public function batalkanPublikasi(
        Request $request,
        UjianCbt $ujianCbt,
        FinalisasiHasilUjianTerpusatService $service,
    ) {
        [$kegiatan, $jadwal] = $this->konteks($ujianCbt);
        $hasil = $service->batalkanPublikasi($request->user(), $kegiatan, $jadwal);

        return back()->with('berhasil', $hasil['pesan']);
    }

    private function konteks(UjianCbt $ujianCbt): array
    {
        abort_unless($ujianCbt->ujianTerpusat(), 404);
        $jadwal = $ujianCbt->jadwalUjianCbt()->with('kegiatanUjianCbt')->firstOrFail();
        abort_unless($jadwal->kegiatanUjianCbt, 404);

        return [$jadwal->kegiatanUjianCbt, $jadwal];
    }
}
