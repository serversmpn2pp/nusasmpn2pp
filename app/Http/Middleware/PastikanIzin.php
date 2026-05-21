<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PastikanIzin
{
    public function handle(Request $request, Closure $next, string ...$kodeIzin): Response
    {
        abort_unless($request->user()?->memilikiIzin($kodeIzin), 403);

        return $next($request);
    }
}
