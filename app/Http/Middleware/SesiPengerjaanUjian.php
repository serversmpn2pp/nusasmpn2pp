<?php

namespace App\Http\Middleware;

use App\Models\PesertaUjianCbt;
use App\Services\Cbt\SesiPengerjaanUjianService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SesiPengerjaanUjian
{
    public function __construct(private readonly SesiPengerjaanUjianService $sesi) {}

    public function handle(Request $request, Closure $next)
    {
        if ($request->routeIs('cbt.logout')) {
            return $next($request); // Logout does not release the active exam lease.
        }
        $mobile = $request->routeIs('api.v1.*');
        $id = $mobile ? $request->route('pesertaUjianCbt')?->id : $request->session()->get('cbt_peserta_ujian_id');
        if (! $id) {
            return $next($request);
        }
        abort_unless($request->user()?->akunSiswa(), 403);
        $identitas = $this->sesi->identitas($request, $mobile);
        $saluran = $mobile ? 'mobile' : 'web';
        $label = $request->input('perangkat');
        $perangkat = $mobile ? (is_string($label) ? trim($label) : '') : 'Web';
        $bolehMengikat = $request->routeIs('api.v1.ujian-saya.mulai', 'api.v1.ujian-saya.kerjakan', 'cbt.ujian.mulai', 'cbt.ujian.kerjakan');

        return DB::transaction(function () use ($request, $next, $id, $identitas, $saluran, $perangkat, $bolehMengikat) {
            $peserta = PesertaUjianCbt::query()->with('ujianCbt')
                ->whereHas('anggotaKelas', fn ($query) => $query->where('siswa_id', $request->user()->siswa_id))
                ->lockForUpdate()->findOrFail($id);
            $this->sesi->pastikan($peserta, $identitas, $saluran, $perangkat, $bolehMengikat, false);
            $response = $next($request);
            if ($response->getStatusCode() < 400 && $bolehMengikat) {
                $this->sesi->pastikan($peserta->fresh(['ujianCbt']), $identitas, $saluran, $perangkat, true);
            }

            return $response;
        });
    }
}
