<?php

namespace App\Services\Humas;

use App\Models\AsetPromosiHumas;
use App\Models\DokumenHumas;
use App\Models\Pengguna;
use App\Models\PublikasiHumas;
use App\Models\RiwayatDokumenHumas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class KelolaAsetPromosiHumasService
{
    public function catat(AsetPromosiHumas $aset, Pengguna $pengguna, string $aksi, ?string $catatan = null): void
    {
        $aset->riwayat()->create(['versi' => $aset->versi, 'aksi' => $aksi, 'snapshot' => $aset->snapshot(),
            'riwayat_dokumen_humas_id' => $aset->riwayat_dokumen_humas_id,
            'catatan' => $catatan, 'pengguna_id' => $pengguna->id, 'created_at' => now()]);
    }

    public function sumber(Request $request, array $data, ?array $file, ?AsetPromosiHumas $aset): array
    {
        $metode = $data['metode'];
        if ($metode === 'tetap') {
            if (! $aset) {
                throw ValidationException::withMessages(['metode' => 'Pilih sumber aset.']);
            }

            return [];
        }
        if ($metode === 'tautan') {
            return ['sumber' => 'tautan', 'tautan' => $data['tautan'], 'riwayat_dokumen_humas_id' => null];
        }
        abort_unless($request->user()->memilikiIzin(['dokumen_humas.lihat', 'dokumen_humas.kelola']), 403);
        if ($metode === 'unggah') {
            abort_unless($request->user()->memilikiIzin('dokumen_humas.kelola'), 403);
            $dokumen = DokumenHumas::create($file + ['kategori' => 'aset_digital', 'judul' => $data['nama'], 'status' => 'aktif', 'ingatkan_hari_sebelum' => 30,
                'dibuat_oleh_pengguna_id' => $request->user()->id, 'diubah_oleh_pengguna_id' => $request->user()->id]);
            $berkas = $dokumen->riwayat()->create($file + ['versi' => 1, 'catatan' => $data['catatan_revisi'] ?? 'Aset promosi pertama kali diunggah.',
                'diunggah_oleh_pengguna_id' => $request->user()->id, 'diunggah_pada' => now()]);
        } else {
            $dokumen = DokumenHumas::lockForUpdate()->findOrFail($data['dokumen_humas_id']);
            if ($dokumen->status !== 'aktif') {
                throw ValidationException::withMessages(['dokumen_humas_id' => 'Pilih dokumen aktif.']);
            }
            $berkas = $dokumen->riwayat()->first();
            if (! $berkas) {
                throw ValidationException::withMessages(['dokumen_humas_id' => 'Dokumen belum memiliki riwayat berkas. Perbarui dokumen sebelum menghubungkannya.']);
            }
        }
        $this->pastikanBerkas($berkas);

        return ['sumber' => 'berkas', 'tautan' => null, 'riwayat_dokumen_humas_id' => $berkas->id];
    }

    public function pastikanBerkas(?RiwayatDokumenHumas $berkas): void
    {
        if (! $berkas || ! Storage::disk('local')->exists($berkas->lokasi_file)) {
            throw ValidationException::withMessages(['berkas' => 'Berkas tidak ditemukan. Pulihkan atau pilih berkas lain.']);
        }
    }

    public function sinkronkan(PublikasiHumas $publikasi, Request $request, array $data): bool
    {
        if (! isset($data['aset_dikirim']) && ! array_key_exists('aset_ids', $data) && ! array_key_exists('perbarui_aset', $data)) {
            return false;
        }
        abort_unless($request->user()->memilikiIzin(AsetPromosiHumas::IZIN_LIHAT), 403);
        $ids = array_map('intval', $data['aset_ids'] ?? []);
        $perbarui = array_map('intval', $data['perbarui_aset'] ?? []);
        if (array_diff($perbarui, $ids)) {
            throw ValidationException::withMessages(['perbarui_aset' => 'Versi baru hanya dapat dipilih untuk aset yang digunakan.']);
        }
        $lama = $publikasi->aset()->get()->keyBy('aset_promosi_humas_id');
        $berubah = count(array_diff($lama->keys()->all(), $ids)) > 0;
        $aset = AsetPromosiHumas::whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get();
        foreach ($aset as $item) {
            if ($lama->has($item->id) && ! in_array($item->id, $perbarui)) {
                continue;
            }
            if ($item->status !== 'aktif') {
                throw ValidationException::withMessages(['aset_ids' => 'Aset diarsipkan tidak dapat digunakan untuk pilihan atau versi baru.']);
            }
            if ($item->sumber === 'berkas') {
                abort_unless($request->user()->memilikiIzin(['dokumen_humas.lihat', 'dokumen_humas.kelola']), 403);
                $item->load('berkas.dokumen');
                $this->pastikanBerkas($item->berkas);
                if ($item->berkas->dokumen->status !== 'aktif') {
                    throw ValidationException::withMessages(['aset_ids' => 'Dokumen aset telah diarsipkan. Pilih aset aktif lainnya.']);
                }
            }
            $snapshot = $item->snapshot();
            if ($lama->has($item->id) && $lama[$item->id]->snapshot === $snapshot && $lama[$item->id]->riwayat_dokumen_humas_id === $item->riwayat_dokumen_humas_id) {
                continue;
            }
            $publikasi->aset()->updateOrCreate(['aset_promosi_humas_id' => $item->id], ['riwayat_dokumen_humas_id' => $item->riwayat_dokumen_humas_id, 'snapshot' => $snapshot]);
            $berubah = true;
        }
        $publikasi->aset()->whereNotIn('aset_promosi_humas_id', $ids)->delete();

        return $berubah;
    }
}
