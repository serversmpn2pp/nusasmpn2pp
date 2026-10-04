<?php

namespace App\Services\Humas;

use App\Models\DokumenHumas;
use App\Models\KlipingBeritaHumas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class KelolaKlipingBeritaHumasService
{
    public function bukti(Request $request, KlipingBeritaHumas $kliping, array $data, ?array $file): void
    {
        if ($data['metode'] === 'tetap') {
            return;
        }
        if (in_array($data['metode'], ['tanpa', 'hapus'])) {
            $kliping->forceFill(['riwayat_dokumen_humas_id' => null]);

            return;
        }
        abort_unless($request->user()->memilikiIzin(['dokumen_humas.lihat', 'dokumen_humas.kelola']), 403);
        if ($data['metode'] === 'unggah') {
            abort_unless($request->user()->memilikiIzin('dokumen_humas.kelola'), 403);
            $dokumen = DokumenHumas::create($file + ['kategori' => 'kliping_media', 'judul' => $kliping->judul, 'status' => 'aktif', 'ingatkan_hari_sebelum' => 30,
                'dibuat_oleh_pengguna_id' => $request->user()->id, 'diubah_oleh_pengguna_id' => $request->user()->id]);
            $berkas = $dokumen->riwayat()->create($file + ['versi' => 1, 'catatan' => $data['catatan_perubahan'] ?? 'Bukti kliping berita diunggah.',
                'diunggah_oleh_pengguna_id' => $request->user()->id, 'diunggah_pada' => now()]);
        } else {
            $dokumen = DokumenHumas::lockForUpdate()->findOrFail($data['dokumen_humas_id']);
            if ($dokumen->status !== 'aktif') {
                throw ValidationException::withMessages(['dokumen_humas_id' => 'Pilih dokumen Humas yang aktif.']);
            }
            $berkas = $dokumen->riwayat()->first();
        }
        if (! $berkas || ! in_array($berkas->tipe_file, KlipingBeritaHumas::MIME_BUKTI) || ! Storage::disk('local')->exists($berkas->lokasi_file)) {
            throw ValidationException::withMessages(['berkas' => 'Pilih bukti PDF/gambar yang berkasnya tersedia.']);
        }
        $kliping->forceFill(['riwayat_dokumen_humas_id' => $berkas->id]);
    }

    public function pastikanSumber(KlipingBeritaHumas $kliping): void
    {
        if (! $kliping->tautan && ! $kliping->riwayat_dokumen_humas_id) {
            throw ValidationException::withMessages(['tautan' => 'Isi tautan berita atau lampirkan bukti PDF/gambar sebelum menyimpan kliping.']);
        }
    }

    public function catat(Request $request, KlipingBeritaHumas $kliping, string $aksi, ?string $catatan = null): void
    {
        $kliping->riwayat()->create(['versi' => $kliping->versi, 'aksi' => $aksi, 'snapshot' => $kliping->snapshot(),
            'riwayat_dokumen_humas_id' => $kliping->riwayat_dokumen_humas_id, 'catatan_perubahan' => $catatan, 'pengguna_id' => $request->user()->id, 'created_at' => now()]);
    }
}
