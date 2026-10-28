<?php

namespace App\Http\Controllers;

use App\Services\Monitoring\MonitoringUjianTvService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MonitoringUjianTvController extends Controller
{
    public function index()
    {
        return view('monitoring-ujian-tv.index');
    }

    public function data(Request $request, MonitoringUjianTvService $service)
    {
        $data = $request->validate(['jadwal_id' => ['nullable', 'integer', 'min:1'], 'sesi_id' => ['nullable', 'integer', 'min:1']]);
        $kegiatanId = (int) $request->attributes->get('monitor_tv')->kegiatan_ujian_cbt_id;
        // Validate ownership before looking up any shared snapshot.
        [$kegiatan, $jadwal, $sesi, $pilihan] = $service->pilih($kegiatanId, $data['jadwal_id'] ?? null, $data['sesi_id'] ?? null);
        $key = 'monitor-ujian:v2:'.$kegiatanId.':'.($jadwal?->id ?? 0).':'.($sesi?->id ?? 0).':'.intdiv(now()->timestamp, 5);
        $snapshot = Cache::remember($key, 5, fn () => $service->bangun($kegiatan, $jadwal, $sesi));

        return response()->json([...$snapshot, ...$pilihan]);
    }
}
