<?php

namespace App\Services\Tahfidz;

use App\Models\KelompokTahfidz;
use App\Models\Pengguna;
use App\Models\PertemuanTahfidz;
use App\Models\SetoranTahfidz;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SetoranTahfidzService
{
    public function __construct(private readonly AksesTahfidz $akses, private readonly NotifikasiTahfidzService $notifikasi) {}

    public function peserta(KelompokTahfidz $kelompok): HasMany
    {
        return $kelompok->anggotaAktif()->whereHas('siswa', fn ($q) => $q->where('aktif', true));
    }

    public function buka(Pengguna $pengguna, KelompokTahfidz $kelompok, string $tanggal): PertemuanTahfidz
    {
        return DB::transaction(function () use ($pengguna, $kelompok, $tanggal) {
            $kelompok = $this->kunciKelompok($pengguna, $kelompok);
            $tahun = $kelompok->tahunPelajaran;
            if (($tahun->tanggal_mulai && $tanggal < $tahun->tanggal_mulai->toDateString())
                || ($tahun->tanggal_selesai && $tanggal > $tahun->tanggal_selesai->toDateString())) {
                throw ValidationException::withMessages(['tanggal' => 'Tanggal pertemuan harus berada dalam tahun pelajaran kelompok.']);
            }

            return $kelompok->pertemuan()->whereDate('tanggal', $tanggal)->first()
                ?? $kelompok->pertemuan()->create(['tanggal' => $tanggal]);
        });
    }

    public function simpan(Pengguna $pengguna, KelompokTahfidz $kelompok, int $pertemuanId, array $data): SetoranTahfidz
    {
        return DB::transaction(function () use ($pengguna, $kelompok, $pertemuanId, $data) {
            $kelompok = $this->kunciKelompok($pengguna, $kelompok);
            $pertemuan = $kelompok->pertemuan()->findOrFail($pertemuanId);
            $token = strtolower($data['token_setoran']);
            $catatan = $data['catatan'] ?? null;
            $nilai = number_format((float) $data['nilai'], 2, '.', '');
            $anggotaId = (int) $data['anggota_tahfidz_id'];
            $surah = (int) $data['nomor_surah'];

            // Retry formulir yang sama tidak membuat percobaan hafalan baru.
            $lama = $pertemuan->setoran()->where('token_setoran', $token)->first();
            if ($lama) {
                if ((int) $lama->penilai_id !== (int) $pengguna->id || (int) $lama->anggota_tahfidz_id !== $anggotaId
                    || (int) $lama->nomor_surah !== $surah || $lama->nilai !== $nilai || $lama->catatan !== $catatan) {
                    throw ValidationException::withMessages(['token_setoran' => 'Formulir ini sudah digunakan. Muat ulang pertemuan untuk setoran baru.']);
                }

                return $lama;
            }
            if ($pertemuan->selesai_pada) {
                throw ValidationException::withMessages(['pertemuan' => 'Pertemuan sudah selesai. Setoran baru tidak dapat ditambahkan.']);
            }
            if (! $this->peserta($kelompok)->whereKey($anggotaId)->exists()) {
                throw ValidationException::withMessages(['anggota_tahfidz_id' => 'Pilih anggota aktif dari kelompok ini.']);
            }

            return $pertemuan->setoran()->create([
                'anggota_tahfidz_id' => $anggotaId, 'nomor_surah' => $surah, 'nilai' => $nilai,
                'catatan' => $catatan, 'penilai_id' => $pengguna->id, 'token_setoran' => $token,
            ]);
        });
    }

    public function selesaikan(Pengguna $pengguna, KelompokTahfidz $kelompok, int $pertemuanId): void
    {
        DB::transaction(function () use ($pengguna, $kelompok, $pertemuanId) {
            $kelompok = $this->kunciKelompok($pengguna, $kelompok);
            $pertemuan = $kelompok->pertemuan()->findOrFail($pertemuanId);
            if ($pertemuan->selesai_pada) {
                return;
            }
            if (! $pertemuan->setoran()->exists()) {
                throw ValidationException::withMessages(['pertemuan' => 'Belum ada setoran untuk dibagikan pada pertemuan ini.']);
            }
            $pertemuan->update(['selesai_pada' => now(), 'diselesaikan_oleh' => $pengguna->id]);
            $this->notifikasi->pertemuan($pertemuan);
        });
    }

    private function kunciKelompok(Pengguna $pengguna, KelompokTahfidz $kelompok): KelompokTahfidz
    {
        $kelompok = KelompokTahfidz::lockForUpdate()->findOrFail($kelompok->id);
        abort_unless($this->akses->catat($pengguna, $kelompok), 403);

        return $kelompok;
    }
}
