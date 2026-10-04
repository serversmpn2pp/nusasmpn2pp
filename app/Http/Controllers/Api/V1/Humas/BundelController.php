<?php

namespace App\Http\Controllers\Api\V1\Humas;

use App\Models\AgendaHumas;
use App\Models\RiwayatBundelPertemuanHumas;
use App\Services\Humas\BundelPertemuanHumasService;
use App\Services\Mobile\HumasMobileService;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class BundelController extends HumasController
{
    public function __construct(private readonly BundelPertemuanHumasService $kelola, private readonly HumasMobileService $mobile) {}

    private function konteks(Request $r, AgendaHumas $a): array
    {
        $this->staff($r, ['agenda_humas.lihat', 'agenda_humas.kelola']);

        return $this->kelola->konteks($a, $r->user());
    }

    public function show(Request $r, AgendaHumas $agendaHumas)
    {
        $k = $this->konteks($r, $agendaHumas);

        return $this->json([
            'agenda_id' => $agendaHumas->id, 'judul' => $agendaHumas->judul, 'sidik' => $k['sidik'],
            'hambatan' => $k['hambatan'], 'rekap_presensi' => $k['rekapPresensi'],
            'hak_akses' => ['dapat_unduh' => $r->user()->memilikiIzin('agenda_humas.bundel'), 'dapat_lihat_dokumen' => $k['bolehDokumen'], 'dapat_lihat_umpan_balik' => $k['bolehUmpan']],
            'siap_unduh' => ! $k['hambatan'] && $r->user()->memilikiIzin('agenda_humas.bundel'),
            'jumlah_dokumen_aktif' => $k['dokumen']->where('status', 'aktif')->count(),
            'jumlah_formulir' => $k['formulir']->count(),
            'batas' => ['lampiran' => BundelPertemuanHumasService::MAKS_BERKAS, 'total_byte' => BundelPertemuanHumasService::MAKS_BYTE, 'formulir' => 20],
        ]);
    }

    public function dokumen(Request $r, AgendaHumas $agendaHumas)
    {
        $this->staff($r, ['dokumen_humas.lihat', 'dokumen_humas.kelola']);
        $k = $this->konteks($r, $agendaHumas);
        $items = $k['dokumen']->where('status', 'aktif')->filter(fn ($d) => $d->riwayat->isNotEmpty())->values();

        return $this->daftar($r, $items, fn ($d) => [
            'id' => $d->id, 'judul' => $d->judul, 'kategori' => $d->kategori, 'id_versi' => $d->riwayat->first()->id,
            'versi' => $d->riwayat->first()->versi, 'nama' => $d->riwayat->first()->nama_file_asli,
            'tipe' => $d->riwayat->first()->tipe_file, 'ukuran_byte' => $d->riwayat->first()->ukuran_file,
            'url' => route('api.v1.humas.dokumen.riwayat.unduh', [$d->id, $d->riwayat->first()->id]),
        ], $k['sidik']);
    }

    public function formulir(Request $r, AgendaHumas $agendaHumas)
    {
        $this->staff($r, ['umpan_balik_humas.lihat', 'umpan_balik_humas.kelola']);
        $k = $this->konteks($r, $agendaHumas);

        return $this->daftar($r, $k['formulir'], fn ($f) => $this->mobile->formulir($f) + ['jumlah_sasaran' => $f->sasaran_count, 'jumlah_respons' => $f->respons_count], $k['sidik']);
    }

    private function daftar(Request $r, $items, callable $map, string $sidik)
    {
        $p = $this->halaman($r);
        $halaman = (int) ($p['halaman'] ?? 1);
        $jumlah = (int) ($p['per_halaman'] ?? 20);
        $paginator = new LengthAwarePaginator($items->slice(($halaman - 1) * $jumlah, $jumlah)->values(), $items->count(), $jumlah, $halaman);

        return $this->json($this->mobile->paginasi($paginator, $map) + ['sidik' => $sidik]);
    }

    public function riwayat(Request $r, AgendaHumas $agendaHumas)
    {
        $k = $this->konteks($r, $agendaHumas);
        $p = $this->halaman($r);
        $q = RiwayatBundelPertemuanHumas::where('agenda_humas_id', $agendaHumas->id)->orderByDesc('id');

        return $this->json($this->mobile->paginasi($q->paginate($p['per_halaman'] ?? 15, ['*'], 'halaman', $p['halaman'] ?? 1), function ($h) use ($k) {
            $s = $h->ringkasan;
            $data = ['id' => $h->id, 'dibuat_pada' => $h->created_at->toIso8601String(), 'ukuran_byte' => $h->ukuran_byte, 'sha256' => $h->sha256,
                'peserta' => $s['peserta'] ?? 0, 'hadir' => $s['hadir'] ?? 0];
            if ($k['bolehDokumen']) {
                $data['jumlah_lampiran'] = count($s['lampiran'] ?? []);
            }
            if ($k['bolehUmpan']) {
                $data['jumlah_formulir'] = count($s['formulir'] ?? []);
            }

            return $data;
        }));
    }

    public function unduh(Request $r, AgendaHumas $agendaHumas)
    {
        $this->staff($r, ['agenda_humas.lihat', 'agenda_humas.kelola']);
        $this->staff($r, 'agenda_humas.bundel');
        [$path, $nama] = $this->kelola->export($r, $agendaHumas);

        return response()->download($path, $nama, ['Content-Type' => 'application/zip', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'])->deleteFileAfterSend(true);
    }
}
