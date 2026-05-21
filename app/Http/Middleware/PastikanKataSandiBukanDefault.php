<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class PastikanKataSandiBukanDefault
{
    public function handle(Request $request, Closure $next): Response
    {
        $pengguna = $request->user();
        $kataSandiDefault = config('nusa.kata_sandi_default_pegawai');

        if (
            $pengguna?->akunPegawai()
            && $kataSandiDefault
            && Hash::check($kataSandiDefault, $pengguna->kata_sandi)
        ) {
            return redirect()
                ->route('kata-sandi.edit')
                ->with('perlu_ganti_kata_sandi', 'Silakan ganti kata sandi default sebelum menggunakan NUSA.');
        }

        return $next($request);
    }
}
