<?php

namespace App\Services\Humas;

use App\Models\DokumenHumas;
use App\Models\PeriodeKomiteHumas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class KelolaKomiteHumasService
{
    public function kunciPeriode(): void
    {
        // A stable row serializes period changes, including simultaneous first activations.
        abort_unless(DB::table('izin')->where('kode', 'komite_humas.kelola')->lockForUpdate()->first(), 403);
    }

    public function sk(Request $request, PeriodeKomiteHumas $periode, array $data, ?array $file): void
    {
        if ($data['metode'] === 'tetap') {
            return;
        }
        if (in_array($data['metode'], ['tanpa', 'lepas'], true)) {
            $periode->forceFill(['riwayat_dokumen_humas_id' => null]);

            return;
        }
        abort_unless($request->user()->memilikiIzin(['dokumen_humas.lihat', 'dokumen_humas.kelola']), 403);
        if ($data['metode'] === 'unggah') {
            abort_unless($request->user()->memilikiIzin('dokumen_humas.kelola'), 403);
            $dokumen = DokumenHumas::create($file + ['kategori' => 'komite', 'judul' => 'SK Komite - '.$periode->nama, 'nomor_dokumen' => $periode->nomor_sk,
                'berlaku_mulai' => $periode->tanggal_mulai, 'berlaku_sampai' => $periode->tanggal_selesai, 'status' => 'aktif', 'ingatkan_hari_sebelum' => 30,
                'dibuat_oleh_pengguna_id' => $request->user()->id, 'diubah_oleh_pengguna_id' => $request->user()->id]);
            $berkas = $dokumen->riwayat()->create($file + ['versi' => 1, 'catatan' => $data['catatan_perubahan'] ?? 'SK kepengurusan komite diunggah.',
                'diunggah_oleh_pengguna_id' => $request->user()->id, 'diunggah_pada' => now()]);
        } else {
            $dokumen = DokumenHumas::lockForUpdate()->findOrFail($data['dokumen_humas_id']);
            if ($dokumen->status !== 'aktif' || $dokumen->kategori !== 'komite') {
                throw ValidationException::withMessages(['dokumen_humas_id' => 'Pilih dokumen komite yang aktif.']);
            }
            $berkas = $dokumen->riwayat()->first();
        }
        if (! $berkas || ! in_array($berkas->tipe_file, PeriodeKomiteHumas::MIME_SK, true) || ! Storage::disk('local')->exists($berkas->lokasi_file)) {
            throw ValidationException::withMessages(['berkas' => 'Berkas SK PDF/gambar tidak tersedia. Pilih atau unggah berkas yang valid.']);
        }
        $periode->forceFill(['riwayat_dokumen_humas_id' => $berkas->id]);
    }

    public function pengurus(PeriodeKomiteHumas $periode, array $rows): bool
    {
        $lama = $periode->pengurus()->get()->keyBy('id');
        $ids = collect($rows)->pluck('id')->filter()->map(fn ($id) => (int) $id);
        if ($ids->unique()->count() !== $ids->count() || $ids->diff($lama->keys())->isNotEmpty()) {
            throw ValidationException::withMessages(['pengurus' => 'Data pengurus tidak sesuai dengan periode ini. Muat ulang halaman.']);
        }
        if ($lama->keys()->diff($ids)->isNotEmpty()) {
            throw ValidationException::withMessages(['pengurus' => 'Pengurus yang sudah tersimpan tidak boleh dihapus. Ubah statusnya menjadi Tidak aktif agar riwayat tetap terjaga.']);
        }
        $berubah = false;
        $aktif = collect($rows)->where('aktif', true);
        foreach (['ketua', 'wakil_ketua', 'sekretaris', 'bendahara'] as $jabatan) {
            if ($aktif->where('jabatan', $jabatan)->count() > 1) {
                throw ValidationException::withMessages(['pengurus' => 'Setiap jabatan inti hanya boleh memiliki satu pengurus aktif. Nonaktifkan pengurus lama sebelum menggantinya.']);
            }
        }
        foreach ($rows as $row) {
            $member = empty($row['id']) ? $periode->pengurus()->make() : $lama[(int) $row['id']];
            $member->fill(collect($row)->only(['nama', 'jabatan', 'nomor_telepon', 'aktif'])->all());
            if (! $member->exists || $member->isDirty()) {
                $member->save();
                $berubah = true;
            }
        }

        return $berubah;
    }

    public function pastikanAktif(PeriodeKomiteHumas $periode): void
    {
        app(KelolaProgramKomiteHumasService::class)->pastikanJadwalPeriode($periode);
        if ($periode->exists && $periode->status === 'draf' && $periode->program()->whereIn('status', ['berjalan', 'selesai'])->exists()) {
            throw ValidationException::withMessages(['status' => 'Kepengurusan memiliki program yang telah berjalan atau selesai. Arsipkan untuk menyimpan riwayat, bukan mengubahnya menjadi draf.']);
        }
        if ($periode->status !== 'aktif') {
            return;
        }
        if (! $periode->nomor_sk || ! $periode->tanggal_sk || ! $periode->riwayat_dokumen_humas_id) {
            throw ValidationException::withMessages(['status' => 'Isi nomor dan tanggal SK serta lampirkan berkas SK sebelum mengaktifkan kepengurusan.']);
        }
        $berkas = $periode->berkas()->first();
        if (! $berkas || ! Storage::disk('local')->exists($berkas->lokasi_file)) {
            throw ValidationException::withMessages(['berkas' => 'Berkas SK tidak tersedia. Unggah atau pilih SK yang tersedia.']);
        }
        if (! $periode->pengurus()->where('aktif', true)->where('jabatan', 'ketua')->exists()) {
            throw ValidationException::withMessages(['pengurus' => 'Tambahkan satu ketua aktif sebelum mengaktifkan kepengurusan.']);
        }
        if (PeriodeKomiteHumas::where('status', 'aktif')->when($periode->exists, fn ($q) => $q->where('id', '<>', $periode->id))
            ->whereDate('tanggal_mulai', '<=', $periode->tanggal_selesai)->whereDate('tanggal_selesai', '>=', $periode->tanggal_mulai)->exists()) {
            throw ValidationException::withMessages(['tanggal_mulai' => 'Masa bakti bertumpang tindih dengan periode aktif lainnya. Perbaiki tanggal atau arsipkan periode yang digantikan.']);
        }
    }

    public function catat(PeriodeKomiteHumas $periode, Request $request, string $aksi, ?string $catatan = null): void
    {
        $periode->riwayat()->create(['versi' => $periode->versi, 'aksi' => $aksi, 'snapshot' => $periode->snapshot(),
            'riwayat_dokumen_humas_id' => $periode->riwayat_dokumen_humas_id, 'catatan_perubahan' => $catatan, 'pengguna_id' => $request->user()->id, 'created_at' => now()]);
    }
}
