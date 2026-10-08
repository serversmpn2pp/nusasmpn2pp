<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class KebijakanPrivasiController extends Controller
{
    public function __invoke(): Response
    {
        // Hanya naskah statis; jangan membaca akun, sesi, atau data sekolah.
        $isi = Str::markdown(File::get(resource_path('legal/kebijakan-privasi-nusa.md')), [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);

        // Cegah penyamaran email oleh Cloudflare tanpa mengizinkan skrip di halaman ini.
        return response()->view('legal.kebijakan-privasi', ['isi' => $isi])
            ->header('Cache-Control', 'no-store, no-transform')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('Content-Security-Policy', "default-src 'none'; img-src 'self'; style-src 'unsafe-inline'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'");
    }
}
