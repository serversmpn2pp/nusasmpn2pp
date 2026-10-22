<?php

namespace App\Services\Humas;

use App\Models\DokumenHumas;
use App\Models\DokumenPendukungKemitraan;
use App\Models\KerjaSamaHumas;
use App\Models\MitraHumas;
use App\Models\Pengguna;
use App\Models\RiwayatDokumenHumas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DokumenPendukungKemitraanService
{
    public function akses(Pengguna $u, bool $kelola = false): void
    {
        abort_unless($u->aktif && ! $u->akunSiswa() && ! $u->akunOrangTua()
            && $u->memilikiIzin($kelola ? 'kemitraan_humas.kelola' : ['kemitraan_humas.lihat', 'kemitraan_humas.kelola'])
            && $u->memilikiIzin(['dokumen_humas.lihat', 'dokumen_humas.kelola']), 403);
    }

    public function pilihan(Request $r)
    {
        $this->akses($r->user(), true);
        $data = $r->validate(['kata_kunci' => ['nullable', 'string', 'max:120']]);

        return DokumenHumas::where('status', 'aktif')->whereHas('riwayat')
            ->when($data['kata_kunci'] ?? null, fn ($q, $kata) => $q->where(fn ($q) => $q->whereLike('judul', '%'.$kata.'%')->orWhereLike('nomor_dokumen', '%'.$kata.'%')))
            ->with(['riwayat' => fn ($q) => $q->limit(1)])->orderByDesc('id')->limit(30)->get()
            ->filter(fn ($d) => Storage::disk('local')->exists($d->riwayat->first()->lokasi_file))->values();
    }

    public function ubah(Request $r, KerjaSamaHumas $mou, ?DokumenPendukungKemitraan $lampiran = null): void
    {
        $this->akses($r->user(), true);
        $data = $r->validate(['versi' => ['required', 'integer', 'min:0'], 'catatan' => ['required', 'string', 'min:5', 'max:1000']]
            + ($lampiran ? [] : ['id_versi' => ['required', 'integer'], 'jenis' => ['required', Rule::in(array_keys(DokumenPendukungKemitraan::JENIS))]]));
        DB::transaction(function () use ($r, $data, $mou, $lampiran) {
            $mitra = MitraHumas::lockForUpdate()->findOrFail($mou->mitra_humas_id);
            $m = $mitra->kerjaSama()->lockForUpdate()->findOrFail($mou->id);
            app(KelolaKemitraanHumasService::class)->pastikanVersi((int) $data['versi'], $m->versi);
            if ($lampiran) {
                $l = DokumenPendukungKemitraan::where('kerja_sama_humas_id', $m->id)->whereNull('dilepas_pada')->findOrFail($lampiran->id);
                $l->forceFill(['dilepas_pada' => now(), 'dilepas_oleh_pengguna_id' => $r->user()->id])->save();
                $d = DokumenHumas::lockForUpdate()->findOrFail($l->berkas->dokumen_humas_id);
            } else {
                app(KelolaKemitraanHumasService::class)->pastikanMitraAktif($mitra);
                if ($m->status === 'diakhiri') {
                    throw ValidationException::withMessages(['id_versi' => 'Perjanjian diakhiri. Dokumen pendukung baru tidak dapat ditambahkan.']);
                }
                $v = RiwayatDokumenHumas::findOrFail($data['id_versi']);
                $d = DokumenHumas::lockForUpdate()->findOrFail($v->dokumen_humas_id);
                if ($d->status !== 'aktif' || (int) $d->riwayat()->max('versi') !== $v->versi) {
                    throw ValidationException::withMessages(['id_versi' => 'Pilih versi terkini dari dokumen aktif.']);
                }
                app(PortofolioAkreditasiHumasService::class)->berkas($v);
                if (DokumenPendukungKemitraan::where('kerja_sama_humas_id', $m->id)->where('riwayat_dokumen_humas_id', $v->id)->whereNull('dilepas_pada')->exists()) {
                    return;
                }
                $l = DokumenPendukungKemitraan::create(['kerja_sama_humas_id' => $m->id, 'riwayat_dokumen_humas_id' => $v->id,
                    'jenis' => $data['jenis'], 'catatan' => $data['catatan'], 'pengguna_id' => $r->user()->id]);
            }
            $m->forceFill(['versi' => $m->versi + 1, 'diubah_oleh_pengguna_id' => $r->user()->id])->save();
            $d->forceFill(['versi_metadata' => $d->versi_metadata + 1, 'diubah_oleh_pengguna_id' => $r->user()->id])->save();
            app(KlasifikasiDokumenHumasService::class)->catat($d, $lampiran ? 'kaitan_lepas' : 'kaitan_tambah', null,
                ['jenis_kaitan' => 'kemitraan_pendukung', 'target_id' => $m->id], $data['catatan'], $r->user());
            app(KelolaKemitraanHumasService::class)->catat($mitra, $m, $lampiran ? 'Pendukung dilepas' : 'Pendukung ditambahkan', null,
                ['pendukung_id' => $l->id, 'id_versi' => $l->riwayat_dokumen_humas_id, 'catatan' => $data['catatan']], $r->user());
        });
    }
}
