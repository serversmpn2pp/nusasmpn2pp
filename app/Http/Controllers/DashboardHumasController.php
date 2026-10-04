<?php

namespace App\Http\Controllers;

use App\Services\Humas\DashboardHumasService;
use App\Services\Humas\PeriodeDashboardHumasService;
use Illuminate\Http\Request;

class DashboardHumasController extends Controller
{
    public function index(Request $request, DashboardHumasService $dashboard)
    {
        return $this->halaman($request, $dashboard, 'index');
    }

    public function cetak(Request $request, DashboardHumasService $dashboard)
    {
        return $this->halaman($request, $dashboard, 'cetak');
    }

    private function halaman(Request $request, DashboardHumasService $dashboard, string $view)
    {
        $akun = $request->user();
        abort_unless($akun->aktif && ! $akun->akunSiswa() && ! $akun->akunOrangTua(), 403);
        $periode = app(PeriodeDashboardHumasService::class)->periode($request);

        return response()->view('dashboard-humas.'.$view, $periode + $dashboard->ringkasan(
            $akun, $periode['mulai'], $periode['selesai'], $periode['filter']['tahun_pelajaran_id'],
        ))->header('Cache-Control', 'private, no-store, max-age=0');
    }
}
