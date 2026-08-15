<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PastikanAkunPegawai
{
    public function handle(Request $request, Closure $next): Response
    {
        $pengguna = $request->user();

        abort_unless($pengguna?->akunPegawai() || $pengguna?->administrator(), 403);

        return $next($request);
    }
}
