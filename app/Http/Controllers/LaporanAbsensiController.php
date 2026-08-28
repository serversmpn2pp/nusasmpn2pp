<?php

namespace App\Http\Controllers;

use App\Services\Absensi\LaporanPresensiSiswaService;
use App\Support\PenulisExcelLaporanAbsensi;
use Illuminate\Http\Request;

class LaporanAbsensiController extends Controller
{
    public function __construct(private readonly LaporanPresensiSiswaService $laporan) {}

    public function index(Request $request)
    {
        return view('laporan-absensi.index', $this->laporan->bangun($request));
    }

    public function exportExcel(Request $request, PenulisExcelLaporanAbsensi $penulis)
    {
        $laporan = $this->laporan->bangun($request);
        $lokasiBerkas = $penulis->buat($laporan);

        return response()
            ->download($lokasiBerkas, $this->laporan->namaBerkas($laporan), [
                'Content-Type' => PenulisExcelLaporanAbsensi::MIME,
            ])
            ->deleteFileAfterSend(true);
    }
}
