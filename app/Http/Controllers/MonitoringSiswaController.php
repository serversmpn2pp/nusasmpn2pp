<?php

namespace App\Http\Controllers;

use App\Services\Monitoring\MonitoringSiswaService;
use Illuminate\Support\Facades\Cache;

class MonitoringSiswaController extends Controller
{
    public function index()
    {
        return view('monitoring-siswa.index');
    }

    public function data(MonitoringSiswaService $service)
    {
        // Share a short-lived snapshot across TV screens; authorization is checked before cache access.
        $data = Cache::remember('monitor-tv:v1:'.now()->format('Ymd').':'.intdiv(now()->timestamp, 5), 5,
            fn () => $service->bangun());

        return response()->json($data);
    }
}
