<?php

namespace App\Services\Tahfidz;

use App\Models\KelompokTahfidz;
use App\Models\Pengguna;
use App\Models\PertemuanTahfidz;
use App\Models\SetoranTahfidz;
use App\Models\SurahTahfidz;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
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

    public function simpanKolektif(Pengguna $pengguna, KelompokTahfidz $kelompok, int $pertemuanId, array $data): int
    {
        return DB::transaction(function () use ($pengguna, $kelompok, $pertemuanId, $data) {
            // Kunci yang sama dengan input tunggal, penugasan, dan penyelesaian pertemuan.
            $kelompok = $this->kunciKelompok($pengguna, $kelompok);
            $pertemuan = $kelompok->pertemuan()->findOrFail($pertemuanId);
            $surah = SurahTahfidz::whereBetween('nomor', [78, 114])->whereIn('nomor', $data['surah'])->pluck('nomor')->all();
            $baris = [];
            foreach ($data['nilai'] as $anggotaId => $daftar) {
                if (! preg_match('/^[1-9]\d*$/D', (string) $anggotaId)) {
                    throw ValidationException::withMessages(['nilai' => 'Daftar siswa tidak valid. Muat ulang tabel.']);
                }
                foreach ($daftar as $nomor => $nilai) {
                    if (! preg_match('/^[1-9]\d*$/D', (string) $nomor) || ! in_array((int) $nomor, $surah, true)) {
                        throw ValidationException::withMessages(['nilai' => 'Nilai hanya boleh diisi untuk surah Juz 30 yang dipilih.']);
                    }
                    if ($nilai === null || (is_string($nilai) && trim($nilai) === '')) {
                        continue;
                    }
                    $baris[] = [
                        'anggota_tahfidz_id' => (int) $anggotaId,
                        'nomor_surah' => (int) $nomor,
                        'nilai' => number_format((float) $nilai, 2, '.', ''),
                        'penilai_id' => $pengguna->id,
                    ];
                }
            }
            if ($baris === []) {
                throw ValidationException::withMessages(['nilai' => 'Isi minimal satu nilai untuk disimpan. Nilai kosong tidak dicatat sebagai setoran.']);
            }

            usort($baris, fn ($a, $b) => [$a['anggota_tahfidz_id'], $a['nomor_surah']] <=> [$b['anggota_tahfidz_id'], $b['nomor_surah']]);
            $sidik = hash('sha256', json_encode($baris, JSON_THROW_ON_ERROR));
            $token = strtolower($data['token_batch']);
            $batch = DB::table('batch_setoran_tahfidz')->where('pertemuan_tahfidz_id', $pertemuan->id)->where('token', $token)->first();
            if ($batch) {
                if ((int) $batch->penilai_id !== (int) $pengguna->id || ! hash_equals($batch->sidik, $sidik)) {
                    throw ValidationException::withMessages(['token_batch' => 'Formulir ini sudah disimpan dengan isian berbeda. Muat ulang tabel untuk setoran baru.']);
                }

                return (int) $batch->jumlah;
            }
            if ($pertemuan->selesai_pada) {
                throw ValidationException::withMessages(['pertemuan' => 'Pertemuan sudah selesai. Setoran baru tidak dapat ditambahkan.']);
            }
            $pesertaIds = array_map('intval', array_keys($data['nilai']));
            if ($this->peserta($kelompok)->whereKey($pesertaIds)->count() !== count($pesertaIds)) {
                throw ValidationException::withMessages(['nilai' => 'Daftar siswa berubah. Pilih anggota aktif dari kelompok ini dan muat ulang tabel.']);
            }
            if (! empty($data['siswa']) && collect($baris)->contains(fn ($row) => $row['anggota_tahfidz_id'] !== (int) $data['siswa'])) {
                throw ValidationException::withMessages(['nilai' => 'Isian tidak sesuai dengan siswa yang dipilih.']);
            }

            $pertemuan->setoran()->createMany(array_map(fn ($row) => $row + ['token_setoran' => (string) Str::uuid()], $baris));
            DB::table('batch_setoran_tahfidz')->insert([
                'pertemuan_tahfidz_id' => $pertemuan->id, 'penilai_id' => $pengguna->id,
                'token' => $token, 'sidik' => $sidik, 'jumlah' => count($baris), 'created_at' => now(),
            ]);

            return count($baris);
        });
    }

    private function kunciKelompok(Pengguna $pengguna, KelompokTahfidz $kelompok): KelompokTahfidz
    {
        $kelompok = KelompokTahfidz::lockForUpdate()->findOrFail($kelompok->id);
        abort_unless($this->akses->catat($pengguna, $kelompok), 403);

        return $kelompok;
    }
}
