<?php

namespace App\Http\Middleware;

use App\Services\Supervisi\AksesSupervisi;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PastikanAksesSupervisi
{
    public function __construct(private AksesSupervisi $akses) {}

    public function handle(Request $request, Closure $next): Response
    {
        $this->akses->staff($request->user());

        return $next($request);
    }
}
