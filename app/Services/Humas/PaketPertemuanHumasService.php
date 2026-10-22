<?php

namespace App\Services\Humas;

use App\Models\AgendaHumas;
use App\Models\Pengguna;
use App\Models\UmpanBalikHumas;
use Illuminate\Support\Facades\Storage;

class PaketPertemuanHumasService
{
    public function data(AgendaHumas $a, Pengguna $u): array
    {
        abort_unless($u->aktif && ! $u->akunSiswa() && ! $u->akunOrangTua() && $u->memilikiIzin(['agenda_humas.lihat', 'agenda_humas.kelola']), 403);
        $rekap = $a->rekapPresensi();
        $jumlah = array_sum($rekap);
        $belum = $rekap['belum_dicatat'] ?? 0;
        $bolehDokumen = $u->memilikiIzin(['dokumen_humas.lihat', 'dokumen_humas.kelola']);
        $dokumen = $bolehDokumen ? $a->dokumen()->where('status', 'aktif')->with(['riwayat' => fn ($q) => $q->limit(1)])->orderBy('judul')->limit(200)->get() : collect();
        $lampiran = $dokumen->map(function ($d) {
            $v = $d->riwayat->first();

            return ['id' => $d->id, 'judul' => $d->judul, 'jenis_dokumen' => $d->jenis_dokumen, 'versi' => $v?->versi,
                'berkas_tersedia' => $v && Storage::disk('local')->exists($v->lokasi_file), 'url' => route('dokumen-humas.show', $d)];
        });
        $undangan = $lampiran->where('jenis_dokumen', 'undangan')->where('berkas_tersedia', true)->count();
        $foto = $lampiran->where('jenis_dokumen', 'dokumentasi')->where('berkas_tersedia', true)->count();
        $umpan = $u->memilikiIzin(['umpan_balik_humas.lihat', 'umpan_balik_humas.kelola'])
            ? UmpanBalikHumas::where('agenda_humas_id', $a->id)->where('status', '<>', 'draf')->count() : null;
        $step = fn ($kode, $label, $siap, $ringkasan, $tab) => ['kode' => $kode, 'label' => $label, 'status' => $siap === null ? 'terbatas' : ($siap ? 'siap' : 'belum'),
            'ringkasan' => $ringkasan, 'url' => $tab ? route('agenda-humas.show', [$a, 'tab' => $tab]) : null];
        $komponen = [
            $step('undangan', 'Undangan & surat pertemuan', $bolehDokumen ? $undangan > 0 : null, $bolehDokumen ? $undangan.' berkas undangan tersedia' : 'Akses arsip dokumen dibatasi', $bolehDokumen ? 'dokumen' : null),
            $step('peserta', 'Daftar peserta', $jumlah > 0, $jumlah.' peserta tercatat', 'peserta'),
            $step('presensi', 'Daftar hadir & presensi', $jumlah > 0 && ! $belum, $belum.' peserta belum dicatat', 'peserta'),
            $step('notulen', 'Notulen & keputusan', filled($a->pembahasan) && filled($a->keputusan), filled($a->pembahasan) && filled($a->keputusan) ? 'Pembahasan dan keputusan tersedia' : 'Pembahasan atau keputusan belum lengkap', 'notulen'),
            $step('dokumentasi', 'Dokumentasi kegiatan', $bolehDokumen ? $foto > 0 : null, $bolehDokumen ? $foto.' dokumen tersedia' : 'Akses arsip dokumen dibatasi', $bolehDokumen ? 'dokumen' : null),
            $step('evaluasi', 'Evaluasi pertemuan', $umpan === null ? null : $umpan > 0, $umpan === null ? 'Akses evaluasi dibatasi' : $umpan.' formulir telah dibuka', null),
        ];
        $hambatan = app(BundelPertemuanHumasService::class)->hambatan($a, $jumlah, $belum);

        return ['agenda' => ['id' => $a->id, 'judul' => $a->judul, 'status' => $a->status], 'komponen' => $komponen,
            'lampiran' => $lampiran->values()->all(), 'hambatan_bundel' => $hambatan, 'siap_bundel' => ! $hambatan,
            'hak_akses' => ['dapat_kelola' => $u->memilikiIzin('agenda_humas.kelola') && $a->status !== 'dibatalkan',
                'dapat_unggah' => $u->memilikiIzin('agenda_humas.kelola') && $u->memilikiIzin('dokumen_humas.kelola') && $a->status !== 'dibatalkan',
                'dapat_unduh_bundel' => ! $hambatan && $u->memilikiIzin('agenda_humas.bundel')]];
    }
}
