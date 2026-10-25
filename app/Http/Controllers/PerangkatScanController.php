<?php

namespace App\Http\Controllers;

use App\Models\PerangkatScanPresensi;
use App\Services\Absensi\AksesPerangkatScan;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PerangkatScanController extends Controller
{
    public function __construct(private readonly AksesPerangkatScan $akses) {}

    private function admin(Request $r): void
    {
        abort_unless($r->user()?->aktif && $r->user()->administrator() && ! $r->user()->akunSiswa() && ! $r->user()->akunOrangTua(), 403);
    }

    public function index(Request $r)
    {
        $this->admin($r);

        return response()->view('perangkat-scan.index', [
            'perangkatSaatIni' => $this->akses->perangkat($r),
            'daftar' => PerangkatScanPresensi::with('pengaktif:id,nama')->orderByRaw('CASE WHEN dicabut_pada IS NULL THEN 0 ELSE 1 END')->latest('id')->paginate(20),
            'cakupanAwal' => in_array($r->query('jenis'), ['siswa', 'pegawai'], true) ? $r->query('jenis') : 'keduanya',
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function store(Request $r)
    {
        $this->admin($r);
        $data = $r->validate(['nama' => ['required', 'string', 'max:100'], 'cakupan' => ['required', Rule::in(array_keys(PerangkatScanPresensi::CAKUPAN))], 'konfirmasi' => ['required', 'accepted']]);
        $token = Str::random(80);
        DB::transaction(function () use ($r, $data, $token) {
            if ($lama = $this->akses->perangkat($r)) {
                $lama->forceFill(['dicabut_pada' => now(), 'dicabut_oleh' => $r->user()->id, 'alasan_pencabutan' => 'Digantikan oleh aktivasi baru pada browser yang sama.'])->save();
            }
            PerangkatScanPresensi::create(['nama' => $data['nama'], 'cakupan' => $data['cakupan'], 'token_hash' => hash('sha256', $token),
                'diaktifkan_oleh' => $r->user()->id, 'browser' => Str::limit($r->userAgent() ?? '', 500, ''), 'ip_terakhir' => $r->ip()]);
        });

        return redirect()->route('perangkat-scan.index')->with('berhasil', 'Browser scanner berhasil diaktifkan. Halaman scan tidak lagi bergantung pada login akun.')
            ->withCookie($this->akses->cookie($r, $token));
    }

    public function cabut(Request $r, PerangkatScanPresensi $perangkat)
    {
        $this->admin($r);
        $data = $r->validate(['alasan' => ['required', 'string', 'min:5', 'max:500']]);
        PerangkatScanPresensi::whereKey($perangkat->id)->whereNull('dicabut_pada')->update(['dicabut_pada' => now(), 'dicabut_oleh' => $r->user()->id, 'alasan_pencabutan' => $data['alasan']]);
        $response = redirect()->route('perangkat-scan.index')->with('berhasil', 'Akses perangkat scanner telah dicabut.');
        if (($token = $this->akses->credential($r)) && hash_equals($perangkat->token_hash, hash('sha256', $token))) {
            $response->withCookie($this->akses->lupa($r));
        }

        return $response;
    }
}
