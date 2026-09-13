<?php

namespace App\Http\Controllers;

use App\Models\JadwalUjianCbt;
use App\Models\KegiatanUjianCbt;
use App\Models\PengawasRuangUjianTerpusat;
use App\Models\SoalCbt;
use App\Models\UjianCbt;

class PusatCbtController extends Controller
{
    public function index()
    {
        $pengguna = auth()->user();

        return view('pusat-cbt.index', [
            'jumlahSoalSiap' => SoalCbt::query()->where('aktif', true)->where('status', 'siap')->count(),
            'jumlahKegiatanTerpusat' => KegiatanUjianCbt::query()->where('status', '!=', 'nonaktif')->count(),
            'jumlahAsesmenKelas' => UjianCbt::query()->where('alur', 'kelas')->where('status', '!=', 'nonaktif')->count(),
            'jumlahPaketTerpusatSiap' => JadwalUjianCbt::query()
                ->whereHas('ujianCbt', fn ($query) => $query->whereIn('status', ['terjadwal', 'berlangsung', 'selesai']))
                ->count(),
            'dapatMengawasiUjian' => $pengguna?->pegawai_id
                ? PengawasRuangUjianTerpusat::query()
                    ->where(function ($query) use ($pengguna) {
                        $query->where('pengawas_utama_pegawai_id', $pengguna->pegawai_id)
                            ->orWhere('pengawas_pendamping_pegawai_id', $pengguna->pegawai_id);
                    })
                    ->exists()
                : false,
        ]);
    }
}
