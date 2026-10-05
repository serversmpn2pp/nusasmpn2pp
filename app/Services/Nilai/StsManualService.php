<?php

namespace App\Services\Nilai;

use App\Models\AnggotaKelas;
use App\Models\JadwalUjianCbt;
use App\Models\KomponenNilai;
use App\Models\NilaiSiswa;
use App\Models\Pengguna;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StsManualService
{
    public function konteks(KomponenNilai $komponen, ?Collection $anggota = null, ?Collection $nilai = null): array
    {
        $komponen->loadMissing('guruMataPelajaran.kelas', 'guruMataPelajaran.mataPelajaran');
        $guru = $komponen->guruMataPelajaran;
        $anggota ??= $guru ? AnggotaKelas::where('kelas_id', $guru->kelas_id)->where('status_keanggotaan', 'aktif')->orderBy('id')->get() : collect();
        $nilai ??= NilaiSiswa::where('komponen_nilai_id', $komponen->id)->whereIn('siswa_id', $anggota->pluck('siswa_id'))->get();
        $nilai = $nilai->keyBy('siswa_id');
        $sidik = $this->sidik($komponen, $anggota, $nilai);
        $hambatan = [];
        $menggunakanCbt = false;
        if (! $komponen->aktif || $komponen->jenis_komponen !== 'sts' || ! in_array($komponen->semester, ['ganjil', 'genap'], true)
            || ! $guru?->aktif || ! $guru->kelas?->aktif
            || (int) $guru->tahun_pelajaran_id !== (int) $guru->kelas->tahun_pelajaran_id
            || $guru->mataPelajaran?->menggunakanPredikat()) {
            $hambatan[] = 'Finalisasi manual hanya untuk komponen STS angka pada penugasan dan kelas aktif.';
        }
        if ($guru) {
            $jumlahKomponen = KomponenNilai::where('aktif', true)->where('jenis_komponen', 'sts')->where('semester', $komponen->semester)
                ->whereHas('guruMataPelajaran', fn ($q) => $q->where('aktif', true)->where('kelas_id', $guru->kelas_id)
                    ->where('tahun_pelajaran_id', $guru->tahun_pelajaran_id)->where('mata_pelajaran_id', $guru->mata_pelajaran_id))->count();
            if ($jumlahKomponen !== 1) {
                $hambatan[] = 'Komponen STS aktif untuk kelas dan mapel ini perlu diperiksa.';
            }
            $jadwalCbt = JadwalUjianCbt::where('status', '!=', 'dibatalkan')->where('mata_pelajaran_id', $guru->mata_pelajaran_id)
                ->whereHas('kelas', fn ($q) => $q->where('kelas.id', $guru->kelas_id))
                ->whereHas('kegiatanUjianCbt', fn ($q) => $q->where('tahun_pelajaran_id', $guru->tahun_pelajaran_id)
                    ->where('semester', $komponen->semester)->whereHas('jenisUjianCbt', fn ($q) => $q->where('kode', 'STS')))->exists();
            $menggunakanCbt = $jadwalCbt || $komponen->kelasUjianCbt()->exists();
            if ($menggunakanCbt) {
                $hambatan[] = 'Mapel atau komponen ini menggunakan CBT. Finalisasikan melalui hasil ujian CBT.';
            }
        }
        $terisi = $nilai->filter(fn ($n) => $n->nilai !== null);
        if ($anggota->isEmpty() || $terisi->isEmpty()) {
            $hambatan[] = 'Belum ada nilai siswa aktif yang dapat difinalisasi.';
        }
        if ($terisi->contains(fn ($n) => ! is_numeric($n->nilai) || ! is_finite((float) $n->nilai) || (float) $n->nilai < 0 || (float) $n->nilai > 100)) {
            $hambatan[] = 'Ada nilai di luar rentang 0 sampai 100. Periksa nilai sebelum finalisasi.';
        }
        $berubah = $komponen->sts_manual_difinalisasi_pada !== null
            && ! hash_equals((string) $komponen->sts_manual_sidik_final, $sidik);

        return [
            'sidik' => $sidik, 'hambatan' => $hambatan, 'dapat_finalisasi' => $hambatan === [],
            'menggunakan_cbt' => $menggunakanCbt,
            'difinalisasi' => $hambatan === [] && $komponen->sts_manual_difinalisasi_pada !== null && ! $berubah,
            'berubah' => $berubah, 'difinalisasi_pada' => $komponen->sts_manual_difinalisasi_pada?->toIso8601String(),
            'jumlah_siswa' => $anggota->count(), 'jumlah_terisi' => $terisi->count(), 'nilai' => $nilai,
        ];
    }

    public function sidik(KomponenNilai $komponen, Collection $anggota, Collection $nilai): string
    {
        $komponen->loadMissing('guruMataPelajaran');
        $nilai = $nilai->keyBy('siswa_id');
        $guru = $komponen->guruMataPelajaran;
        $data = [
            'komponen' => $komponen->only(['id', 'guru_mata_pelajaran_id', 'semester', 'jenis_komponen', 'nama', 'aktif', 'tanggal_penilaian', 'keterangan']),
            'penugasan' => $guru?->only(['id', 'tahun_pelajaran_id', 'kelas_id', 'mata_pelajaran_id', 'pegawai_id', 'aktif']),
            'siswa' => $anggota->sortBy('id')->map(function ($a) use ($nilai) {
                $n = $nilai->get($a->siswa_id);

                return [(int) $a->id, (int) $a->siswa_id, $n?->nilai === null ? null : number_format((float) $n->nilai, 2, '.', ''), $n?->predikat, $n?->catatan];
            })->values()->all(),
        ];

        return hash('sha256', json_encode($data, JSON_THROW_ON_ERROR));
    }

    public function tetapkan(Pengguna $pengguna, KomponenNilai $komponen, string $sidik, bool $final): array
    {
        abort_unless($pengguna->aktif && ! $pengguna->akunSiswa() && ! $pengguna->akunOrangTua() && $pengguna->memilikiIzin('nilai.input'), 403);

        return DB::transaction(function () use ($pengguna, $komponen, $sidik, $final) {
            $komponen = KomponenNilai::lockForUpdate()->findOrFail($komponen->id);
            $komponen->load('guruMataPelajaran.kelas', 'guruMataPelajaran.mataPelajaran');
            app(KomponenNilaiService::class)->pastikanBolehAksesKomponen($pengguna, $komponen);
            if ($komponen->jenis_komponen !== 'sts' || ! $komponen->aktif) {
                throw ValidationException::withMessages(['sts_manual' => 'Pilih komponen STS aktif.']);
            }
            $k = $this->konteks($komponen);
            if (! hash_equals($k['sidik'], $sidik)) {
                throw ValidationException::withMessages(['sidik' => 'Nilai atau daftar siswa telah berubah. Muat ulang dan periksa sebelum finalisasi.']);
            }
            if ($final) {
                if ($k['hambatan']) {
                    throw ValidationException::withMessages(['sts_manual' => implode(' ', $k['hambatan'])]);
                }
                if (! $k['difinalisasi']) {
                    $komponen->forceFill([
                        'sts_manual_difinalisasi_pada' => now(),
                        'sts_manual_difinalisasi_oleh_pengguna_id' => $pengguna->id,
                        'sts_manual_sidik_final' => $sidik,
                    ])->save();
                }
            } else {
                $komponen->batalkanFinalisasiStsManual();
            }

            return $this->konteks($komponen->refresh());
        });
    }

    public function respons(array $konteks): array
    {
        return array_diff_key($konteks, ['nilai' => true]);
    }
}
