<?php

namespace App\Http\Controllers;

use App\Models\SesiSupervisi;
use App\Services\Supervisi\LaporanSupervisiService;
use App\Services\Supervisi\RekapSupervisiService;
use App\Support\PenulisExcelSupervisi;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class LaporanSupervisiController extends Controller
{
    public function __construct(private RekapSupervisiService $rekap, private LaporanSupervisiService $laporan) {}

    public function index(Request $r)
    {
        $data = $this->rekap->bangun($r->user(), $r->query());
        $page = (int) ($data['filter']['halaman'] ?? 1);
        $data['daftar'] = (new LengthAwarePaginator($data['semua']->forPage($page, 20)->values(), $data['semua']->count(), 20, $page, ['path' => $r->url(), 'pageName' => 'halaman']))->appends(collect($data['filter'])->except('halaman')->all());
        unset($data['semua']);

        return ($r->routeIs('api.*') ? response()->json(['data' => $data]) : response()->view('supervisi.rekap', $data))->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function cetakRekap(Request $r)
    {
        $data = $this->rekap->bangun($r->user(), $r->query());

        return response()->view('supervisi.cetak-rekap', $data)->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function export(Request $r, PenulisExcelSupervisi $excel)
    {
        $data = $this->rekap->bangun($r->user(), $r->query());
        $path = $excel->buat($data);

        return response()->download($path, 'rekap-supervisi-'.now()->format('Ymd-His').'.xlsx', ['Content-Type' => PenulisExcelSupervisi::MIME, 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'])->deleteFileAfterSend(true);
    }

    public function show(Request $r, SesiSupervisi $sesi)
    {
        return response()->json(['data' => $this->laporan->satu($r->user(), $sesi)])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function cetakSatu(Request $r, SesiSupervisi $sesi)
    {
        return $this->cetak([$this->laporan->satu($r->user(), $sesi)]);
    }

    public function cetakKolektif(Request $r)
    {
        return $this->cetak($this->laporan->kolektif($r->user(), $r->all()));
    }

    private function cetak(array $laporan)
    {
        return response()->view('supervisi.cetak-laporan', ['laporan' => $laporan])->header('Cache-Control', 'private, no-store, max-age=0');
    }
}
