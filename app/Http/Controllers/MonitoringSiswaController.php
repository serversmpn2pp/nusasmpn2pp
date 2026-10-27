<?php

namespace App\Http\Controllers;

use App\Models\AnggotaKelas;
use App\Models\TahunPelajaran;
use App\Services\Absensi\AturanAlfaOtomatisSiswaService;
use App\Services\Monitoring\MonitoringSiswaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class MonitoringSiswaController extends Controller
{
    public function index()
    {
        return view('monitoring-siswa.index');
    }

    public function data(MonitoringSiswaService $service)
    {
        return response()->json(collect($this->snapshot($service))->except('rincian')->all());
    }

    private function snapshot(MonitoringSiswaService $service): array
    {
        // Share a short-lived snapshot across TV screens; authorization is checked before cache access.
        return Cache::remember('monitor-tv:v2:'.now()->format('Ymd').':'.intdiv(now()->timestamp, 5), 5,
            fn () => $service->bangun(true));
    }

    public function rincian(Request $request, MonitoringSiswaService $service)
    {
        $filter = $request->validate([
            'jenis' => ['required', Rule::in(['dipantau', 'hadir', 'izin', 'sakit', 'alfa', 'belum', 'pengecualian', 'terlambat', 'laporan', 'ibadah', 'poin', 'poin_tambah', 'poin_kurang'])],
            'q' => ['nullable', 'string', 'max:100'], 'kelas_id' => ['nullable', 'integer', 'min:1'],
            'kegiatan_id' => ['nullable', 'integer', 'min:1'],
            'status_ibadah' => ['nullable', Rule::in(['sudah', 'belum'])], 'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        return response()->json($service->rincian($this->snapshot($service), $filter));
    }

    public function foto(int $siswa, AturanAlfaOtomatisSiswaService $aturan)
    {
        $tahun = TahunPelajaran::where('aktif', true)->orderByDesc('tanggal_mulai')->first();
        $query = AnggotaKelas::with('siswa:id,foto')->where('tahun_pelajaran_id', $tahun?->id ?? 0)
            ->where('siswa_id', $siswa)->where('status_keanggotaan', 'aktif')
            ->whereHas('siswa', fn ($q) => $q->where('aktif', true))
            ->whereHas('kelas', fn ($q) => $q->where('aktif', true));
        $aturan->batasiKeanggotaan($query, now()->toDateString());
        $foto = $query->first()?->siswa?->foto;
        abort_unless($foto && ! str_contains($foto, '..') && ! str_contains($foto, '\\') && ! str_starts_with($foto, '/')
            && in_array(strtolower(pathinfo($foto, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp', 'gif', 'avif'], true), 404);
        $disk = Storage::disk('public');
        abort_unless($disk->exists($foto), 404);
        $mime = $disk->mimeType($foto);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif'], true), 404);

        return response()->file($disk->path($foto), ['Content-Type' => $mime]);
    }
}
