<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PastikanKataSandiApiBukanDefault
{
    public function handle(Request $request, Closure $next): Response
    {
        $pengguna = $request->user()?->fresh();

        if ($pengguna?->harusMenggantiKataSandi()) {
            return new JsonResponse([
                'message' => 'Ganti kata sandi awal sebelum menggunakan fitur NUSA.',
                'wajib_ganti_kata_sandi' => true,
            ], Response::HTTP_PRECONDITION_REQUIRED);
        }

        return $next($request);
    }
}
