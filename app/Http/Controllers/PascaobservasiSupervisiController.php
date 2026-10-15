<?php

namespace App\Http\Controllers;

use App\Models\LampiranTindakLanjutSupervisi;
use App\Models\SesiSupervisi;
use App\Models\TindakLanjutSupervisi;
use App\Services\Supervisi\PascaobservasiSupervisiService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PascaobservasiSupervisiController extends Controller
{
    public function __construct(private PascaobservasiSupervisiService $service) {}

    private function data(Request $r, SesiSupervisi $s): array
    {
        $this->service->lihat($r->user(), $s);

        return ['sesi' => [...$s->only(['id', 'versi', 'status', 'konteks', 'program_supervisi_id']), 'supervisor' => $s->supervisor->nama_lengkap,
            'waktu_mulai' => $s->waktu_mulai->toIso8601String(), 'waktu_selesai' => $s->waktu_selesai->toIso8601String()],
            'praobservasi' => ['status' => $s->praobservasi?->status ?? 'belum_diisi'], 'penilaian' => ['status' => $s->penilaian?->status ?? 'belum_diisi'],
            'pascaobservasi' => $this->service->data($r->user(), $s), 'label_rencana' => PascaobservasiSupervisiService::RENCANA, 'token' => (string) Str::uuid()];
    }

    public function show(Request $r, SesiSupervisi $sesi)
    {
        return $this->halaman($r, 'pascaobservasi', $this->data($r, $sesi));
    }

    public function tugasShow(Request $r, SesiSupervisi $sesi, TindakLanjutSupervisi $tindak)
    {
        $t = $this->service->dataTugas($r->user(), $sesi, $tindak);

        return $this->halaman($r, 'tindak-lanjut', [...$this->data($r, $sesi), 'rencana' => $t]);
    }

    public function refleksiSimpan(Request $r, SesiSupervisi $sesi)
    {
        $this->service->simpanRefleksi($r->user(), $sesi, $r->all());

        return $this->tersimpan($r, $sesi, 'Draf refleksi disimpan.');
    }

    public function refleksiTindakan(Request $r, SesiSupervisi $sesi, string $aksiRefleksi)
    {
        $this->service->tindakanRefleksi($r->user(), $sesi, $aksiRefleksi, $r->all());

        return $this->tersimpan($r, $sesi, match ($aksiRefleksi) {
            'kirim' => 'Refleksi dikirim kepada supervisor.', 'terima' => 'Refleksi diterima. Rencana tindak lanjut dapat disusun.',
            'kembalikan' => 'Refleksi dikembalikan kepada guru.', default => 'Refleksi disesuaikan dengan hasil terbaru. Tinjau jawaban dan kirim ulang.',
        });
    }

    public function tutup(Request $r, SesiSupervisi $sesi, string $aksiPasca)
    {
        $this->service->tutup($r->user(), $sesi, $aksiPasca, $r->all());

        return $this->tersimpan($r, $sesi, $aksiPasca === 'selesaikan' ? 'Pascaobservasi dinyatakan selesai.' : 'Pascaobservasi dibuka ulang.');
    }

    public function rencanaSimpan(Request $r, SesiSupervisi $sesi, ?TindakLanjutSupervisi $tindak = null)
    {
        $t = $this->service->simpanRencana($r->user(), $sesi, $r->all(), $tindak?->exists ? $tindak : null);

        return $this->tersimpan($r, $sesi, 'Draf rencana tindak lanjut disimpan.', $t);
    }

    public function terbitkan(Request $r, SesiSupervisi $sesi)
    {
        $this->service->terbitkan($r->user(), $sesi, $r->all());

        return $this->tersimpan($r, $sesi, 'Rencana terpilih diterbitkan dan guru diberi notifikasi.');
    }

    public function pelaksanaanSimpan(Request $r, SesiSupervisi $sesi, TindakLanjutSupervisi $tindak)
    {
        $this->service->simpanPelaksanaan($r->user(), $sesi, $tindak, $r->all());

        return $this->tersimpan($r, $sesi, 'Draf laporan pelaksanaan disimpan.', $tindak);
    }

    public function tugasTindakan(Request $r, SesiSupervisi $sesi, TindakLanjutSupervisi $tindak, string $aksiTugas)
    {
        $this->service->tindakanTugas($r->user(), $sesi, $tindak, $aksiTugas, $r->all());

        return $this->tersimpan($r, $sesi, match ($aksiTugas) {
            'ajukan' => 'Laporan diajukan untuk verifikasi.', 'verifikasi' => 'Pelaksanaan tindak lanjut diverifikasi selesai.',
            'kembalikan' => 'Laporan dikembalikan untuk diperbaiki.', 'batalkan' => 'Rencana dibatalkan. Riwayat tetap tersimpan.', default => 'Laporan dibuka untuk perbaikan.',
        }, $tindak);
    }

    public function unggah(Request $r, SesiSupervisi $sesi, TindakLanjutSupervisi $tindak)
    {
        $r->validate(['berkas' => ['required', 'file']]);
        $this->service->unggah($r->user(), $sesi, $tindak, $r->all(), $r->file('berkas'));

        return $this->tersimpan($r, $sesi, 'Bukti pelaksanaan diunggah.', $tindak);
    }

    public function unduh(Request $r, SesiSupervisi $sesi, TindakLanjutSupervisi $tindak, LampiranTindakLanjutSupervisi $bukti)
    {
        return $this->service->unduh($r->user(), $sesi, $tindak, $bukti);
    }

    public function hapus(Request $r, SesiSupervisi $sesi, TindakLanjutSupervisi $tindak, LampiranTindakLanjutSupervisi $bukti)
    {
        $this->service->hapus($r->user(), $sesi, $tindak, $bukti, $r->all());

        return $this->tersimpan($r, $sesi, 'Bukti pelaksanaan dihapus.', $tindak);
    }

    private function halaman(Request $r, string $view, array $data)
    {
        return ($r->routeIs('api.*') ? response()->json(['data' => $data]) : response()->view('supervisi.'.$view, $data))->header('Cache-Control', 'private, no-store, max-age=0');
    }

    private function tersimpan(Request $r, SesiSupervisi $s, string $pesan, ?TindakLanjutSupervisi $t = null)
    {
        if (! $r->routeIs('api.*')) {
            return redirect()->route($t ? 'supervisi.tindak-lanjut.show' : 'supervisi.pascaobservasi.show', $t ? [$s, $t] : [$s])->with('berhasil', $pesan);
        }
        $s = $s->fresh();
        $data = $t ? $this->service->dataTugas($r->user(), $s, $t->fresh()) : $this->service->data($r->user(), $s);

        return response()->json(['data' => [...$data, 'versi_sesi' => $s->versi], 'message' => $pesan])->header('Cache-Control', 'private, no-store, max-age=0');
    }
}
