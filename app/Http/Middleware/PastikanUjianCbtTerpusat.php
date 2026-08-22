<?php

namespace App\Http\Middleware;

use App\Models\UjianCbt;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PastikanUjianCbtTerpusat
{
    public function handle(Request $request, Closure $next): Response
    {
        $ujianCbt = $request->route('ujianCbt');

        if ($ujianCbt instanceof UjianCbt) {
            abort_unless($ujianCbt->ujianTerpusat(), 404);
        }

        return $next($request);
    }
}
