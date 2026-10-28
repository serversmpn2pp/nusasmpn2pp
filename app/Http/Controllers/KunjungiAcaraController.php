<?php

namespace App\Http\Controllers;

use App\Models\AcaraBukuTamu;
use App\Services\Humas\AcaraBukuTamuService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class KunjungiAcaraController extends Controller
{
    private const HEADERS = ['Cache-Control' => 'private, no-store', 'X-Frame-Options' => 'DENY',
        'Referrer-Policy' => 'same-origin', 'X-Content-Type-Options' => 'nosniff', 'X-Robots-Tag' => 'noindex, nofollow, noarchive'];

    private function acara(string $kode): AcaraBukuTamu
    {
        return AcaraBukuTamu::where('kode_publik', $kode)->where('status', '!=', 'draf')->firstOrFail();
    }

    public function show(string $kode, AcaraBukuTamuService $service)
    {
        $acara = $this->acara($kode);

        return response()->view('acara-buku-tamu.publik', ['acara' => $acara, 'tiket' => $service->tiket($acara), 'sukses' => false], 200, self::HEADERS);
    }

    public function store(Request $r, string $kode, AcaraBukuTamuService $service)
    {
        $acara = $this->acara($kode);
        try {
            $baru = $service->terima($r, $acara);
        } catch (HttpExceptionInterface $e) {
            if ($e->getStatusCode() !== 409 || $r->expectsJson()) {
                throw $e;
            }

            return redirect()->route('kunjungi-acara.show', $kode)->withErrors(['formulir' => $e->getMessage()]);
        }
        if ($r->expectsJson()) {
            return response()->json(['pesan' => 'Terima kasih. Kunjungan Anda sudah tercatat.'], $baru ? 201 : 200, self::HEADERS);
        }
        $r->session()->forget('_old_input');

        return redirect()->route('kunjungi-acara.selesai', $kode, 303)->with('acara_terkirim', $acara->id);
    }

    public function selesai(Request $r, string $kode, AcaraBukuTamuService $service)
    {
        $acara = $this->acara($kode);
        if ($r->session()->pull('acara_terkirim') !== $acara->id) {
            return redirect()->route('kunjungi-acara.show', $kode);
        }

        return response()->view('acara-buku-tamu.publik', ['acara' => $acara, 'tiket' => [], 'sukses' => true], 200, self::HEADERS);
    }

    public function banner(Request $r, string $kode)
    {
        $acara = AcaraBukuTamu::where('kode_publik', $kode)->firstOrFail();
        if ($acara->status === 'draf') {
            app(AcaraBukuTamuService::class)->akses($r->user());
        }
        abort_unless($acara->banner && Storage::disk('local')->exists($acara->banner), 404);
        $mime = Storage::disk('local')->mimeType($acara->banner);
        abort_unless(in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true), 404);

        return response()->file(Storage::disk('local')->path($acara->banner), self::HEADERS + ['Content-Type' => $mime]);
    }
}
