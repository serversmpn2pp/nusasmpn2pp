<?php

namespace App\Http\Controllers;

use App\Models\JadwalUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\SoalCbt;
use App\Models\UjianCbt;

class PusatCbtController extends Controller
{
    public function index()
    {
        return view('pusat-cbt.index', [
            'jumlahSoalSiap' => SoalCbt::query()->where('aktif', true)->where('status', 'siap')->count(),
            'jumlahKegiatanTerpusat' => KegiatanUjianCbt::query()->where('status', '!=', 'nonaktif')->count(),
            'jumlahAsesmenKelas' => UjianCbt::query()->where('alur', 'kelas')->where('status', '!=', 'nonaktif')->count(),
            'jumlahPaketTerpusatSiap' => JadwalUjianCbt::query()
                ->whereHas('ujianCbt', fn ($query) => $query->whereIn('status', ['terjadwal', 'berlangsung', 'selesai']))
                ->count(),
        ]);
    }
}
