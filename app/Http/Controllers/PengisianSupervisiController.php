<?php

namespace App\Http\Controllers;

use App\Models\LampiranSupervisi;
use App\Models\SesiSupervisi;
use App\Services\Supervisi\InstrumenSupervisiService;
use App\Services\Supervisi\PascaobservasiSupervisiService;
use App\Services\Supervisi\PengisianSupervisiService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use JsonException;

class PengisianSupervisiController extends Controller
{
    public function __construct(private PengisianSupervisiService $service, private InstrumenSupervisiService $instrumen) {}

    public function praShow(Request $r, SesiSupervisi $sesi)
    {
        return $this->halaman($r, $sesi, 'praobservasi');
    }

    public function nilaiShow(Request $r, SesiSupervisi $sesi)
    {
        return $this->halaman($r, $sesi, 'penilaian');
    }

    private function halaman(Request $r, SesiSupervisi $s, string $view)
    {
        $this->service->lihat($r->user(), $s);
        $data = [
            'sesi' => [...$s->only(['id', 'versi', 'status', 'konteks', 'topik', 'program_supervisi_id', 'guru_pegawai_id', 'supervisor_pegawai_id']),
                'supervisor' => $s->supervisor->nama_lengkap, 'program' => $s->program->nama,
                'waktu_mulai' => $s->waktu_mulai->toIso8601String(), 'waktu_selesai' => $s->waktu_selesai->toIso8601String()],
            'praobservasi' => $this->service->dataPra($r->user(), $s),
            'penilaian' => $this->service->dataPenilaian($r->user(), $s),
            'pascaobservasi' => ['status' => $s->pascaobservasi?->status ?? 'belum_diisi', 'hasil_terbaru' => app(PascaobservasiSupervisiService::class)->sejalan($s)],
            'hak' => $this->service->hak($r->user(), $s),
            'label_isian' => PengisianSupervisiService::ISIAN,
            'instrumen' => $this->instrumen->data($s->instrumen),
        ];

        return ($r->routeIs('api.*') ? response()->json(['data' => $data]) : response()->view('supervisi.'.$view, $data))
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }

    public function praSimpan(Request $r, SesiSupervisi $sesi)
    {
        $this->service->simpanPra($r->user(), $sesi, $r->all());

        return $this->tersimpan($r, $sesi, 'praobservasi', 'Draf praobservasi disimpan.');
    }

    public function praTindakan(Request $r, SesiSupervisi $sesi, string $aksiPra)
    {
        $this->service->tindakanPra($r->user(), $sesi, $aksiPra, $r->all());

        return $this->tersimpan($r, $sesi, 'praobservasi', $aksiPra === 'kirim' ? 'Praobservasi dikirim kepada supervisor.' : 'Praobservasi dikembalikan kepada guru untuk diperbaiki.');
    }

    public function nilaiSimpan(Request $r, SesiSupervisi $sesi)
    {
        if (! $r->routeIs('api.*') && $r->has('nilai_json')) {
            $r->validate(['nilai_json' => ['required', 'string', 'max:2000000']]);
            try {
                $r->merge(['nilai' => json_decode($r->input('nilai_json'), true, 16, JSON_THROW_ON_ERROR)]);
            } catch (JsonException) {
                throw ValidationException::withMessages(['nilai' => 'Data penilaian tidak valid. Muat ulang formulir.']);
            }
        }
        $this->service->simpanNilai($r->user(), $sesi, $r->all());

        return $this->tersimpan($r, $sesi, 'penilaian', 'Draf penilaian disimpan.');
    }

    public function nilaiTindakan(Request $r, SesiSupervisi $sesi, string $aksiNilai)
    {
        $this->service->tindakanNilai($r->user(), $sesi, $aksiNilai, $r->all());

        return $this->tersimpan($r, $sesi, 'penilaian', $aksiNilai === 'finalisasi' ? 'Hasil difinalisasi dan dapat dilihat guru.' : 'Hasil ditarik untuk koreksi. Supervisor harus memfinalisasi ulang agar hasil tampil kepada guru.');
    }

    public function lampiranSimpan(Request $r, SesiSupervisi $sesi)
    {
        $r->validate(['berkas' => ['required', 'file']]);
        $this->service->unggah($r->user(), $sesi, $r->all(), $r->file('berkas'));

        return $this->tersimpan($r, $sesi, 'praobservasi', 'Perangkat ajar diunggah.');
    }

    public function lampiranHapus(Request $r, SesiSupervisi $sesi, LampiranSupervisi $lampiran)
    {
        $this->service->hapusLampiran($r->user(), $sesi, $lampiran, $r->all());

        return $this->tersimpan($r, $sesi, 'praobservasi', 'Lampiran dihapus.');
    }

    public function lampiranUnduh(Request $r, SesiSupervisi $sesi, LampiranSupervisi $lampiran)
    {
        return $this->service->unduh($r->user(), $sesi, $lampiran);
    }

    private function tersimpan(Request $r, SesiSupervisi $s, string $bagian, string $pesan)
    {
        if (! $r->routeIs('api.*')) {
            return redirect()->route('supervisi.'.$bagian.'.show', $s)->with('berhasil', $pesan);
        }
        $s = $s->fresh();
        $data = $bagian === 'praobservasi' ? $this->service->dataPra($r->user(), $s) : $this->service->dataPenilaian($r->user(), $s);

        return response()->json(['data' => [...$data, 'versi_sesi' => $s->versi, 'hak' => $this->service->hak($r->user(), $s)], 'message' => $pesan])
            ->header('Cache-Control', 'private, no-store, max-age=0');
    }
}
