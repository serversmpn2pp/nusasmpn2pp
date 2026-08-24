<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PastikanAkunApiAktif
{
    public function handle(Request $request, Closure $next): Response
    {
        $pengguna = $request->user();
        $penggunaTerbaru = $pengguna?->fresh();

        if (! $penggunaTerbaru?->aktif) {
            $pengguna?->currentAccessToken()?->delete();

            return new JsonResponse([
                'message' => 'Akun Anda sedang tidak aktif.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
