<?php

namespace App\Http\Middleware;

use App\Models\Pengguna;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class PastikanIdentitasSesi
{
    private const KUNCI_IDENTITAS = 'nusa.identitas_pengguna';

    public function handle(Request $request, Closure $next): Response
    {
        $pengguna = $request->user();

        if (! $pengguna) {
            return $next($request);
        }

        $identitas = [
            'pengguna_id' => (string) $pengguna->getAuthIdentifier(),
            'sidik_jari' => $this->sidikJari($pengguna),
        ];
        $identitasTersimpan = $request->session()->get(self::KUNCI_IDENTITAS);

        $idSama = is_array($identitasTersimpan)
            && ($identitasTersimpan['pengguna_id'] ?? null) === $identitas['pengguna_id'];
        $sidikJariBerubah = $idSama
            && (! is_string($identitasTersimpan['sidik_jari'] ?? null)
                || ! hash_equals($identitasTersimpan['sidik_jari'], $identitas['sidik_jari']));

        if ($sidikJariBerubah) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('login')
                ->with('gagal', 'Identitas sesi berubah. Silakan masuk kembali untuk melindungi akun Anda.')
                ->withHeaders($this->headerTanpaCache());
        }

        if (! $idSama) {
            $request->session()->put(self::KUNCI_IDENTITAS, $identitas);
        }

        $response = $next($request);

        foreach ($this->headerTanpaCache() as $nama => $nilai) {
            $response->headers->set($nama, $nilai);
        }

        return $response;
    }

    private function sidikJari(Pengguna $pengguna): string
    {
        return hash('sha256', implode('|', [
            $pengguna->getAuthIdentifier(),
            $pengguna->username,
            $pengguna->pegawai_id,
            $pengguna->siswa_id,
            $pengguna->akun_sistem ? 'sistem' : 'biasa',
        ]));
    }

    private function headerTanpaCache(): array
    {
        return [
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0, private',
            'Pragma' => 'no-cache',
            'Expires' => '0',
        ];
    }
}
