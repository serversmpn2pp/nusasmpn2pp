<?php

namespace App\Http\Controllers;

use App\Models\UjianCbt;
use App\Services\Cbt\CakupanHasilUjianCbtService;
use App\Services\Cbt\FinalisasiHasilUjianTerpusatService;
use App\Services\Cbt\RekapHasilUjianCbtService;
use App\Services\Cbt\SelesaikanPengerjaanKedaluwarsaCbtService;
use App\Support\PenulisExcelHasilUjianCbt;
use Illuminate\Http\Request;

class ExportHasilUjianCbtController extends Controller
{
    public function __invoke(
        Request $request,
        UjianCbt $ujianCbt,
        CakupanHasilUjianCbtService $cakupanHasil,
        RekapHasilUjianCbtService $rekapHasil,
        PenulisExcelHasilUjianCbt $penulisExcel,
        FinalisasiHasilUjianTerpusatService $finalisasiHasil,
        SelesaikanPengerjaanKedaluwarsaCbtService $penyelesaianKedaluwarsa,
    ) {
        $data = $request->validate([
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
        ]);
        $kelasId = (int) $data['kelas_id'];

        if ($ujianCbt->ujianTerpusat()) {
            $penyelesaianKedaluwarsa->selesaikanUjian($ujianCbt);
            $finalisasiHasil->sinkronkanAlfaOtomatis($request->user(), $ujianCbt);
        }

        $kelasDapatDiekspor = $cakupanHasil->kelasDapatDiekspor($request->user(), $ujianCbt);
        $kelasUjian = $kelasDapatDiekspor->first(
            fn ($item) => (int) $item->kelas_id === $kelasId,
        );
        abort_unless($kelasUjian, 403);

        $laporan = $rekapHasil->bangun(
            $ujianCbt,
            collect([$kelasUjian]),
            $kelasId,
            null,
            'semua',
        );
        $laporan['kelasUjian'] = $kelasUjian;
        $lokasiBerkas = $penulisExcel->buat($laporan);
        $namaBerkas = collect([
            'hasil-cbt',
            $ujianCbt->mataPelajaran?->nama,
            $kelasUjian->kelas?->nama,
            $ujianCbt->kode,
        ])->filter()->map(fn ($bagian) => str($bagian)->replace('.', '-')->slug()->toString())->implode('-').'.xlsx';

        return response()
            ->download($lokasiBerkas, $namaBerkas, ['Content-Type' => PenulisExcelHasilUjianCbt::MIME])
            ->deleteFileAfterSend(true);
    }
}
