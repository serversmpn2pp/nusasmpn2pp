<?php

namespace App\Http\Middleware;

use App\Models\PesertaUjianCbt;
use App\Services\Cbt\BatasWaktuPesertaUjianCbtService;
use App\Services\Cbt\KeamananUjianService;
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

        return DB::transaction(function () use ($request, $next, $id, $identitas, $saluran, $perangkat, $bolehMengikat, $mobile) {
            $peserta = PesertaUjianCbt::query()->with('ujianCbt')
                ->whereHas('anggotaKelas', fn ($query) => $query->where('siswa_id', $request->user()->siswa_id))
                ->lockForUpdate()->findOrFail($id);
            $this->sesi->pastikan($peserta, $identitas, $saluran, $perangkat, $bolehMengikat, false);
            if ($mobile && ! $request->routeIs('api.v1.ujian-saya.aktivitas-keamanan')) {
                $keamanan = app(KeamananUjianService::class);
                $keamanan->periksaKesinambungan($peserta, $perangkat, $request->ip(), $bolehMengikat || $request->routeIs('api.v1.ujian-saya.selesai'));
                if ($peserta->status === 'terblokir' && $request->routeIs('api.v1.ujian-saya.izin-berkas.mulai')) {
                    // Commit an expired-permit hold before the grant service can reject state.
                    return response()->json(['data' => [
                        'protocol' => 1, 'diterima' => false, 'mode' => 'ditahan', 'izin' => null,
                        'keamanan' => $keamanan->ringkas($peserta),
                    ]])->header('Cache-Control', 'no-store, no-cache, must-revalidate');
                }
                $menulis = $request->routeIs('api.v1.ujian-saya.jawaban.update', 'api.v1.ujian-saya.jawaban-berkas.store', 'cbt.ujian.jawaban', 'cbt.ujian.simpan');
                if ($menulis && app(BatasWaktuPesertaUjianCbtService::class)->sisaDetik($peserta) > 0
                    && ($peserta->status === 'terblokir' || $keamanan->adaKeluarBelumSelesai($peserta) || $keamanan->izinBerkasAktif($peserta))) {
                    // Respons, bukan exception: keputusan peninjauan tidak boleh ter-rollback.
                    return response()->json([
                        'message' => 'Sesi ujian perlu direkonsiliasi. Kembali ke halaman ujian atau hubungi pengawas.',
                        'errors' => ['ujian' => ['Sesi ujian perlu direkonsiliasi.']],
                        'data' => ['mode' => $peserta->status === 'terblokir' ? 'ditahan' : 'pengerjaan', 'keamanan' => $keamanan->ringkas($peserta)],
                    ], 422);
                }
            }
            $response = $next($request);
            if ($response->getStatusCode() < 400 && $bolehMengikat) {
                $this->sesi->pastikan($peserta->fresh(['ujianCbt']), $identitas, $saluran, $perangkat, true);
            }

            return $response;
        });
    }
}
