<?php

namespace App\Http\Controllers;

use App\Models\PerangkatMonitoring;
use App\Models\ResponAcaraBukuTamu;
use App\Models\Siswa;
use App\Services\Monitoring\MonitoringPameranService;
use App\Services\Monitoring\MonitoringSiswaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MonitoringPameranController extends Controller
{
    public function index(Request $request, MonitoringPameranService $service)
    {
        $layar = $request->attributes->get('monitor_tv');

        return view('monitoring-siswa.index', ['pameran' => true, 'layar' => $layar, 'acaraPameran' => $layar->acaraBukuTamu,
            'tanggalPameran' => $service->tanggal($layar), 'batasTanggalPameran' => $service->batasTanggal(), 'monitorCsrf' => $request->attributes->get('monitor_csrf')]);
    }

    public function data(Request $request, MonitoringPameranService $service)
    {
        return response()->json($service->aktivitas($request->attributes->get('monitor_tv')));
    }

    public function pengunjung(Request $request, MonitoringPameranService $service)
    {
        return response()->json($service->pengunjung($request->attributes->get('monitor_tv')));
    }

    public function rincianPengunjung(Request $request, MonitoringPameranService $service)
    {
        $filter = $request->validate([
            'jenis' => ['required', Rule::in(['total', 'hari_ini', 'puas', 'nusa', 'harian', 'kepuasan', 'minat', 'terbaru'])],
            'q' => ['nullable', 'string', 'max:100'],
            'kategori' => ['nullable', Rule::in(array_keys(ResponAcaraBukuTamu::KATEGORI))],
            'tanggal' => ['nullable', 'date_format:Y-m-d'],
            'nilai' => ['nullable', 'integer', 'between:1,5'],
            'minat' => ['nullable', 'string', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        return response()->json($service->rincianPengunjung($request->attributes->get('monitor_tv'), $filter));
    }

    public function tanggal(Request $request, MonitoringPameranService $service)
    {
        $data = $request->validate(['tanggal' => ['required', ...$service->aturanTanggal()]]);
        $layar = $request->attributes->get('monitor_tv');
        DB::transaction(function () use ($layar, $data) {
            $row = PerangkatMonitoring::whereKey($layar->id)->lockForUpdate()->firstOrFail();
            abort_unless($row->mengizinkan('pameran'), 401);
            $row->update(['pameran_tanggal' => $data['tanggal']]);
        });

        return response()->json(['tanggal_data' => $data['tanggal']]);
    }

    public function rincian(Request $request, MonitoringPameranService $pameran, MonitoringSiswaService $siswa)
    {
        $filter = $request->validate([
            'jenis' => ['required', Rule::in(['dipantau', 'hadir', 'izin', 'sakit', 'alfa', 'belum', 'pengecualian', 'terlambat', 'laporan', 'ibadah', 'poin', 'poin_tambah', 'poin_kurang'])],
            'q' => ['nullable', 'string', 'max:100'], 'kelas_id' => ['nullable', 'integer', 'min:1'],
            'kegiatan_id' => ['nullable', 'integer', 'min:1'], 'status_ibadah' => ['nullable', Rule::in(['sudah', 'belum'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        return response()->json($siswa->rincian($pameran->aktivitas($request->attributes->get('monitor_tv'), true), $filter));
    }

    public function foto(Request $request, int $siswa, MonitoringPameranService $service)
    {
        abort_unless($service->fotoDiizinkan($request->attributes->get('monitor_tv'), $siswa), 404);
        $foto = Siswa::whereKey($siswa)->value('foto');
        abort_unless($foto && ! str_contains($foto, '..') && ! str_contains($foto, '\\') && ! str_starts_with($foto, '/')
            && in_array(strtolower(pathinfo($foto, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'], true), 404);
        $disk = Storage::disk('public');
        abort_unless($disk->exists($foto), 404);
        $mime = $disk->mimeType($foto);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'], true), 404);

        return response()->file($disk->path($foto), ['Content-Type' => $mime]);
    }
}
