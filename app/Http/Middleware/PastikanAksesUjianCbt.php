<?php

namespace App\Http\Middleware;

use App\Models\UjianCbt;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PastikanAksesUjianCbt
{
    public function handle(Request $request, Closure $next): Response
    {
        $ujianCbt = $request->route('ujianCbt');
        $pengguna = $request->user();

        if ($ujianCbt instanceof UjianCbt) {
            abort_unless($ujianCbt->dapatDiaksesOperasionalOleh($pengguna), 403);
        }

        return $next($request);
    }
}
