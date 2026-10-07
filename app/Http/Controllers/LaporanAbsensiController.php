<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKelas;
use App\Services\Absensi\LaporanPresensiSiswaService;
use App\Support\PenulisExcelLaporanAbsensi;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LaporanAbsensiController extends Controller
{
    public function __construct(private readonly LaporanPresensiSiswaService $laporan) {}

    public function index(Request $request)
    {
        return view('laporan-absensi.index', $this->laporan->bangun($request));
    }

    public function show(Request $request, AnggotaKelas $anggotaKelas)
    {
        $data = $request->validate(['status_rincian' => ['nullable', Rule::in(['semua', 'hadir', 'sakit', 'izin', 'alfa', 'terlambat', 'belum_scan'])]]);
        $detail = $this->laporan->rincian($request, $anggotaKelas);
        $statusRincian = $data['status_rincian'] ?? 'semua';
        $semuaRincian = $detail['rincian'];
        $rincian = $semuaRincian->filter(fn ($baris) => match ($statusRincian) {
            'semua' => true, 'terlambat' => $baris['menit_terlambat'] > 0, default => $baris['status'] === $statusRincian,
        })->sortByDesc('tanggal')->values();

        return response()->view('laporan-absensi.show', [
            ...$detail, 'rincian' => $rincian, 'statusRincian' => $statusRincian,
        ])->header('Cache-Control', 'private, no-store');
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
