<?php

namespace App\Http\Controllers;

use App\Models\PerangkatMonitoring;
use App\Services\Monitoring\AksesMonitorTv;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PerangkatMonitoringController extends Controller
{
    public function __construct(private readonly AksesMonitorTv $akses) {}

    private function admin(Request $request): void
    {
        abort_unless($request->user()?->aktif && $request->user()->administrator()
            && ! $request->user()->akunSiswa() && ! $request->user()->akunOrangTua(), 403);
    }

    public function index(Request $request)
    {
        $this->admin($request);

        return response()->view('perangkat-monitoring.index', [
            'saatIni' => $this->akses->perangkat($request),
            'daftar' => PerangkatMonitoring::latest('id')->paginate(20),
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function store(Request $request)
    {
        $this->admin($request);
        $data = $request->validate(['nama' => ['required', 'string', 'max:100'], 'konfirmasi' => ['required', 'accepted']]);
        $token = Str::random(80);
        DB::transaction(function () use ($request, $data, $token) {
            $this->akses->perangkat($request)?->update(['dicabut_pada' => now(), 'dicabut_oleh' => $request->user()->id,
                'alasan_pencabutan' => 'Digantikan aktivasi baru pada browser yang sama.']);
            PerangkatMonitoring::create(['nama' => $data['nama'], 'token_hash' => hash('sha256', $token), 'diaktifkan_oleh' => $request->user()->id]);
        });

        return redirect()->route('perangkat-monitoring.index')->with('berhasil', 'Layar monitoring berhasil diaktifkan.')
            ->withCookie($this->akses->cookie($request, $token));
    }

    public function cabut(Request $request, PerangkatMonitoring $perangkat)
    {
        $this->admin($request);
        $data = $request->validate(['alasan' => ['required', 'string', 'min:5', 'max:500']]);
        PerangkatMonitoring::whereKey($perangkat->id)->whereNull('dicabut_pada')->update([
            'dicabut_pada' => now(), 'dicabut_oleh' => $request->user()->id, 'alasan_pencabutan' => $data['alasan'],
        ]);
        $response = redirect()->route('perangkat-monitoring.index')->with('berhasil', 'Akses layar monitoring dicabut.');
        if (($token = $this->akses->token($request)) && hash_equals($perangkat->token_hash, hash('sha256', $token))) {
            $response->withCookie($this->akses->cookie($request, '')->withExpires(1));
        }

        return $response;
    }
}
