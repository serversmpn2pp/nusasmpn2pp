<?php

namespace App\Services\Humas;

use App\Models\DokumenHumas;
use App\Models\Pengguna;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KlasifikasiDokumenHumasService
{
    public static function dariKategori(?string $kategori): array
    {
        return ['urusan' => match ($kategori) {
            'sop' => 'kedinasan', 'komite' => 'komite', 'hubungan_orang_tua' => 'orang_tua', 'kemitraan' => 'kemitraan',
            'publikasi', 'prestasi', 'kliping_media', 'aset_digital' => 'publikasi', 'alumni' => 'alumni', default => null,
        }, 'jenis_dokumen' => match ($kategori) {
            'program_kerja', 'sop', 'surat_masuk', 'surat_keluar', 'notulen', 'laporan_kegiatan' => $kategori,
            'iasp' => 'portofolio_akreditasi', 'kliping_media' => 'kliping', default => null,
        }];
    }

    public function aturanFilter(): array
    {
        return ['urusan' => ['nullable', Rule::in(array_keys(DokumenHumas::URUSAN))],
            'jenis_dokumen' => ['nullable', Rule::in(array_keys(DokumenHumas::JENIS_DOKUMEN))],
            'klasifikasi' => ['nullable', Rule::in(['perlu_tinjau', 'diperiksa'])]];
    }

    public function filter(Builder $query, array $filter): Builder
    {
        foreach (['urusan', 'jenis_dokumen'] as $kolom) {
            $query->when($filter[$kolom] ?? null, fn ($q, $v) => $q->where($kolom, $v));
        }
        if (($filter['klasifikasi'] ?? '') === 'perlu_tinjau') {
            $query->where(fn ($q) => $q->whereNull('urusan')->orWhereNull('jenis_dokumen')->orWhereNull('klasifikasi_diperiksa_pada'));
        } elseif (($filter['klasifikasi'] ?? '') === 'diperiksa') {
            $query->whereNotNull('urusan')->whereNotNull('jenis_dokumen')->whereNotNull('klasifikasi_diperiksa_pada');
        }

        return $query;
    }

    public function data(array $data, ?DokumenHumas $dokumen, Pengguna $akun): array
    {
        if (isset($data['urusan'], $data['jenis_dokumen'])) {
            $data['sumber_klasifikasi'] = 'manual';
            $data['klasifikasi_diperiksa_pada'] = now();
            $data['klasifikasi_pemeriksa_id'] = $akun->id;
        } else {
            unset($data['urusan'], $data['jenis_dokumen']);
            if (! $dokumen || $dokumen->sumber_klasifikasi !== 'manual') {
                $data += self::dariKategori($data['kategori'] ?? $dokumen?->kategori);
                $data['sumber_klasifikasi'] = 'kategori_lama';
            }
        }
        $data['kategori'] ??= $dokumen?->kategori ?: (isset(DokumenHumas::KATEGORI[$data['jenis_dokumen'] ?? ''])
            ? $data['jenis_dokumen'] : ['orang_tua' => 'hubungan_orang_tua', 'kedinasan' => 'lainnya'][$data['urusan'] ?? ''] ?? $data['urusan'] ?? 'lainnya');
        if (! isset(DokumenHumas::KATEGORI[$data['kategori']])) {
            $data['kategori'] = 'lainnya';
        }

        return $data;
    }

    public function snapshot(DokumenHumas $d): array
    {
        return $d->only(['urusan', 'jenis_dokumen', 'sumber_klasifikasi', 'klasifikasi_pemeriksa_id'])
            + ['klasifikasi_diperiksa_pada' => $d->klasifikasi_diperiksa_pada?->toIso8601String()];
    }

    public function catat(DokumenHumas $d, string $aksi, ?array $sebelum, array $sesudah, string $alasan, ?Pengguna $akun = null): void
    {
        $d->riwayatKlasifikasi()->create(['aksi' => $aksi, 'sebelum' => $sebelum, 'sesudah' => $sesudah,
            'alasan' => $alasan, 'pengguna_id' => $akun?->id, 'created_at' => now()]);
    }

    public function kolektif(Request $request): int
    {
        $akun = $request->user();
        abort_unless($akun->aktif && ! $akun->akunSiswa() && ! $akun->akunOrangTua() && $akun->memilikiIzin('dokumen_humas.kelola'), 403);
        $data = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:50'], 'ids.*' => ['required', 'integer', 'distinct'],
            'sidik' => ['required', 'array', 'max:50'], 'sidik.*' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D'],
            'urusan' => ['required', Rule::in(array_keys(DokumenHumas::URUSAN))],
            'jenis_dokumen' => ['required', Rule::in(array_keys(DokumenHumas::JENIS_DOKUMEN))],
            'alasan' => ['required', 'string', 'min:5', 'max:1000']]);

        return DB::transaction(function () use ($data, $akun) {
            $dokumen = DokumenHumas::whereIn('id', $data['ids'])->orderBy('id')->lockForUpdate()->get();
            abort_unless($dokumen->count() === count($data['ids']), 404);
            foreach ($dokumen as $d) {
                $sidik = $data['sidik'][$d->id] ?? null;
                if (! is_string($sidik) || ! hash_equals(app(KelolaDokumenHumasService::class)->sidik($d), $sidik)) {
                    throw ValidationException::withMessages(['ids' => 'Ada dokumen yang sudah berubah. Muat ulang daftar sebelum mengonfirmasi klasifikasi.']);
                }
            }
            foreach ($dokumen as $d) {
                $sebelum = $this->snapshot($d);
                $d->forceFill($this->data(['urusan' => $data['urusan'], 'jenis_dokumen' => $data['jenis_dokumen']], $d, $akun)
                    + ['diubah_oleh_pengguna_id' => $akun->id, 'versi_metadata' => $d->versi_metadata + 1])->save();
                $this->catat($d, 'klasifikasi_kolektif', $sebelum, $this->snapshot($d), $data['alasan'], $akun);
            }

            return $dokumen->count();
        });
    }

    public function usulan(DokumenHumas $d): array
    {
        $lama = self::dariKategori($d->kategori);
        $urusan = array_filter([$lama['urusan']]);
        $jenis = array_filter([$lama['jenis_dokumen']]);
        foreach ($d->agenda()->pluck('jenis') as $kode) {
            $urusan[] = match ($kode) {
                'orang_tua', 'komite', 'kemitraan' => $kode, default => null
            };
        }
        if (DB::table('kerja_sama_humas')->where('dokumen_humas_id', $d->id)->exists()) {
            $urusan[] = 'kemitraan';
            $jenis[] = 'mou';
        }
        $versi = DB::table('riwayat_dokumen_humas')->where('dokumen_humas_id', $d->id)->select('id');
        if (DB::table('periode_komite_humas')->whereIn('riwayat_dokumen_humas_id', clone $versi)->exists()) {
            $urusan[] = 'komite';
            $jenis[] = 'sk';
        }
        if (DB::table('kliping_berita_humas')->whereIn('riwayat_dokumen_humas_id', clone $versi)->exists()) {
            $urusan[] = 'publikasi';
            $jenis[] = 'kliping';
        }
        foreach (DB::table('dokumen_pendukung_kemitraan')->whereIn('riwayat_dokumen_humas_id', clone $versi)->whereNull('dilepas_pada')->pluck('jenis') as $pendukung) {
            $urusan[] = 'kemitraan';
            $jenis[] = match ($pendukung) {
                'proposal', 'berita_acara', 'dokumentasi' => $pendukung, 'laporan' => 'laporan_kegiatan', default => null,
            };
        }
        if (DB::table('aset_promosi_humas')->whereIn('riwayat_dokumen_humas_id', clone $versi)->exists()
            || DB::table('prestasi_sekolah')->whereIn('riwayat_dokumen_humas_id', clone $versi)->exists()) {
            $urusan[] = 'publikasi';
        }
        foreach ($d->program()->pluck('bidang') as $kode) {
            $urusan[] = isset(DokumenHumas::URUSAN[$kode]) ? $kode : null;
        }
        if ($d->programKomite()->exists()) {
            $urusan[] = 'komite';
        }
        $bidangLaporan = DB::table('bukti_laporan_humas as b')->join('laporan_pelaksanaan_humas as l', 'l.id', '=', 'b.laporan_pelaksanaan_humas_id')
            ->join('program_kerja_humas as p', 'p.id', '=', 'l.program_kerja_humas_id')->whereNull('b.dilepas_pada')
            ->whereIn('b.riwayat_dokumen_humas_id', clone $versi)->pluck('p.bidang');
        foreach ($bidangLaporan as $kode) {
            $urusan[] = isset(DokumenHumas::URUSAN[$kode]) ? $kode : null;
        }
        $urusan = array_values(array_unique(array_filter($urusan)));
        $jenis = array_values(array_unique(array_filter($jenis)));

        return ['urusan' => count($urusan) === 1 ? $urusan[0] : null,
            'jenis_dokumen' => count($jenis) === 1 ? $jenis[0] : null,
            'konflik' => count($urusan) > 1 || count($jenis) > 1];
    }

    public function tataArsipLama(bool $terapkan = false): array
    {
        $hasil = ['diperiksa' => 0, 'berubah' => 0, 'konflik' => 0, 'tetap_perlu_tinjau' => 0];
        DokumenHumas::whereNull('klasifikasi_diperiksa_pada')->orderBy('id')->chunkById(100, function ($daftar) use (&$hasil, $terapkan) {
            foreach ($daftar as $awal) {
                DB::transaction(function () use ($awal, &$hasil, $terapkan) {
                    $d = DokumenHumas::lockForUpdate()->findOrFail($awal->id);
                    if ($d->klasifikasi_diperiksa_pada || $d->sumber_klasifikasi === 'manual') {
                        return;
                    }
                    $hasil['diperiksa']++;
                    $usulan = $this->usulan($d);
                    $hasil['konflik'] += (int) $usulan['konflik'];
                    $hasil['tetap_perlu_tinjau']++;
                    $baru = ['urusan' => $usulan['urusan'], 'jenis_dokumen' => $usulan['jenis_dokumen']];
                    if ($d->only(array_keys($baru)) === $baru) {
                        return;
                    }
                    $hasil['berubah']++;
                    if ($terapkan) {
                        $sebelum = $this->snapshot($d);
                        $d->forceFill($baru + ['sumber_klasifikasi' => 'kategori_dan_kaitan', 'versi_metadata' => $d->versi_metadata + 1])->save();
                        $this->catat($d, 'penataan_arsip_lama', $sebelum, $this->snapshot($d),
                            'Pemetaan kategori dan hubungan data yang pasti. Belum dikonfirmasi oleh petugas.');
                    }
                });
            }
        });

        return $hasil;
    }
}
