<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PastikanKataSandiBukanDefault
{
    public function handle(Request $request, Closure $next): Response
    {
        $pengguna = $request->user();

        if ($pengguna?->harusMenggantiKataSandi()) {
            return redirect()
                ->route('kata-sandi.edit')
                ->with('perlu_ganti_kata_sandi', 'Silakan ganti kata sandi awal sebelum menggunakan NUSA.');
        }

        return $next($request);
    }
}
