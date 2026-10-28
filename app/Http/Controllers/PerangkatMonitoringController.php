<?php

namespace App\Http\Controllers;

use App\Models\AcaraBukuTamu;
use App\Models\KegiatanUjianCbt;
use App\Models\PerangkatMonitoring;
use App\Services\Monitoring\AksesMonitorTv;
use App\Services\Monitoring\MonitoringPameranService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

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
            'daftar' => PerangkatMonitoring::with('kegiatanUjianCbt:id,nama')->latest('id')->paginate(20),
            'kegiatan' => KegiatanUjianCbt::where('status', '!=', 'nonaktif')->latest('tanggal_mulai')->get(['id', 'nama']),
            'acaraPameran' => AcaraBukuTamu::where('status', '!=', 'draf')->orderByDesc('mulai_pada')->get(['id', 'nama']),
            'batasTanggalPameran' => app(MonitoringPameranService::class)->batasTanggal(),
            'cakupanAwal' => in_array($request->query('jenis'), ['ujian', 'pameran'], true) ? $request->query('jenis') : 'siswa',
        ])->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function store(Request $request)
    {
        $this->admin($request);
        $request->mergeIfMissing(['cakupan' => 'siswa']);
        $data = $request->validate(['nama' => ['required', 'string', 'max:100'], 'konfirmasi' => ['required', 'accepted'],
            'cakupan' => ['required', Rule::in(array_keys(PerangkatMonitoring::CAKUPAN))],
            'kegiatan_ujian_cbt_id' => ['nullable', 'required_if:cakupan,ujian,keduanya', 'integer',
                Rule::exists('kegiatan_ujian_cbt', 'id')->where(fn ($q) => $q->where('status', '!=', 'nonaktif'))],
            'acara_buku_tamu_id' => ['exclude_unless:cakupan,pameran', 'required', 'integer', Rule::exists('acara_buku_tamu', 'id')->where(fn ($q) => $q->where('status', '!=', 'draf'))],
            'pameran_tanggal' => ['exclude_unless:cakupan,pameran', 'required', ...app(MonitoringPameranService::class)->aturanTanggal()],
            'pameran_identitas' => ['exclude_unless:cakupan,pameran', 'sometimes', 'boolean'],
        ]);
        $token = Str::random(80);
        DB::transaction(function () use ($request, $data, $token) {
            $this->akses->perangkat($request)?->update(['dicabut_pada' => now(), 'dicabut_oleh' => $request->user()->id,
                'alasan_pencabutan' => 'Digantikan aktivasi baru pada browser yang sama.']);
            PerangkatMonitoring::create(['nama' => $data['nama'], 'token_hash' => hash('sha256', $token), 'diaktifkan_oleh' => $request->user()->id,
                'cakupan' => $data['cakupan'], 'kegiatan_ujian_cbt_id' => in_array($data['cakupan'], ['ujian', 'keduanya'], true) ? $data['kegiatan_ujian_cbt_id'] : null,
                'acara_buku_tamu_id' => $data['acara_buku_tamu_id'] ?? null, 'pameran_tanggal' => $data['pameran_tanggal'] ?? null,
                'pameran_identitas' => $data['pameran_identitas'] ?? false]);
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
