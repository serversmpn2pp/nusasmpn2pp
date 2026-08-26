<?php

namespace App\Services\Nilai;

use App\Models\AnggotaKelas;
use App\Models\GuruMataPelajaran;
use App\Models\KomponenNilai;
use App\Models\MataPelajaran;
use App\Models\NilaiSiswa;
use App\Models\Pengguna;
use App\Models\PublikasiNilaiSiswa;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class InputNilaiService
{
    public function __construct(
        private readonly KomponenNilaiService $komponenNilai,
        private readonly PublikasiNilaiService $publikasiNilai,
    ) {}

    public function queryKomponenDalamCakupan(Pengguna $pengguna): Builder
    {
        return $this->komponenNilai->queryKomponenDalamCakupan($pengguna);
    }

    public function queryGuruMataPelajaranDalamCakupan(Pengguna $pengguna): Builder
    {
        return $this->komponenNilai->queryGuruMataPelajaranDalamCakupan($pengguna);
    }

    public function ambilKomponenDalamCakupan(
        Pengguna $pengguna,
        int|string $komponenNilaiId,
    ): KomponenNilai {
        return $this->queryKomponenDalamCakupan($pengguna)
            ->with([
                'guruMataPelajaran.tahunPelajaran',
                'guruMataPelajaran.kelas',
                'guruMataPelajaran.mataPelajaran',
                'guruMataPelajaran.pegawai',
            ])
            ->where('aktif', true)
            ->whereKey($komponenNilaiId)
            ->firstOrFail();
    }

    public function aturanValidasi(KomponenNilai $komponenNilai): array
    {
        $menggunakanPredikat = $komponenNilai
            ->guruMataPelajaran?->mataPelajaran?->menggunakanPredikat() ?? false;

        $aturan = [
            'komponen_nilai_id' => ['required', 'integer', Rule::exists('komponen_nilai', 'id')],
            'nilai' => ['nullable', 'array'],
            'predikat' => ['nullable', 'array'],
            'catatan' => ['nullable', 'array'],
            'catatan.*' => ['nullable', 'string', 'max:255'],
        ];

        if ($menggunakanPredikat) {
            $aturan['predikat.*'] = ['nullable', Rule::in(MataPelajaran::PREDIKAT_NILAI)];
        } else {
            $aturan['nilai.*'] = ['nullable', 'numeric', 'min:0', 'max:100'];
        }

        return $aturan;
    }

    public function pesanValidasi(): array
    {
        return [
            'nilai.*.numeric' => 'Nilai harus berupa angka.',
            'nilai.*.min' => 'Nilai minimal 0.',
            'nilai.*.max' => 'Nilai maksimal 100.',
            'predikat.*.in' => 'Predikat harus SB, B, C, atau K.',
        ];
    }

    public function simpan(
        Pengguna $pengguna,
        KomponenNilai $komponenNilai,
        array $data,
    ): bool {
        $this->komponenNilai->pastikanBolehAksesKomponen($pengguna, $komponenNilai);
        abort_unless($komponenNilai->aktif, 404);
        $komponenNilai->loadMissing('guruMataPelajaran.mataPelajaran');

        $menggunakanPredikat = $komponenNilai
            ->guruMataPelajaran?->mataPelajaran?->menggunakanPredikat() ?? false;
        $kelasId = $komponenNilai->guruMataPelajaran?->kelas_id;
        $anggotaKelas = $kelasId ? $this->ambilAnggotaKelas((int) $kelasId) : collect();
        $siswaIds = $anggotaKelas->pluck('siswa_id')->map(fn ($id) => (int) $id);
        $idsDikirim = collect(array_keys($data['nilai'] ?? []))
            ->merge(array_keys($data['predikat'] ?? []))
            ->merge(array_keys($data['catatan'] ?? []))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($idsDikirim->diff($siswaIds)->isNotEmpty()) {
            throw ValidationException::withMessages([
                'komponen_nilai_id' => 'Ada data siswa yang tidak sesuai dengan kelas komponen nilai ini.',
            ]);
        }

        DB::transaction(function () use ($komponenNilai, $siswaIds, $data, $menggunakanPredikat) {
            foreach ($siswaIds as $siswaId) {
                $nilaiMentah = $data['nilai'][$siswaId] ?? $data['nilai'][(string) $siswaId] ?? null;
                $predikatMentah = $data['predikat'][$siswaId] ?? $data['predikat'][(string) $siswaId] ?? null;
                $catatanMentah = $data['catatan'][$siswaId] ?? $data['catatan'][(string) $siswaId] ?? '';
                $catatan = trim((string) $catatanMentah);
                $nilai = $menggunakanPredikat || $nilaiMentah === null || $nilaiMentah === ''
                    ? null
                    : round((float) $nilaiMentah, 2);
                $predikat = ! $menggunakanPredikat || blank($predikatMentah)
                    ? null
                    : mb_strtoupper(trim((string) $predikatMentah));

                if ($nilai === null && $predikat === null && $catatan === '') {
                    NilaiSiswa::query()
                        ->where('komponen_nilai_id', $komponenNilai->id)
                        ->where('siswa_id', $siswaId)
                        ->delete();

                    continue;
                }

                NilaiSiswa::query()->updateOrCreate(
                    [
                        'komponen_nilai_id' => $komponenNilai->id,
                        'siswa_id' => $siswaId,
                    ],
                    [
                        'nilai' => $nilai,
                        'predikat' => $predikat,
                        'catatan' => $catatan ?: null,
                    ],
                );
            }
        });

        return $this->publikasiNilai->tandaiDraf(
            (int) $komponenNilai->guru_mata_pelajaran_id,
            $komponenNilai->semester,
        );
    }

    public function publikasikan(
        Pengguna $pengguna,
        GuruMataPelajaran $guruMataPelajaran,
        string $semester,
    ): PublikasiNilaiSiswa {
        $this->pastikanSemesterValid($semester);
        $this->pastikanBolehAksesGuruMataPelajaran($pengguna, $guruMataPelajaran);

        if (! $guruMataPelajaran->aktif) {
            throw ValidationException::withMessages([
                'publikasi' => 'Nilai dari penugasan guru mapel yang tidak aktif tidak dapat dipublikasikan.',
            ]);
        }

        $komponenIds = KomponenNilai::query()
            ->where('guru_mata_pelajaran_id', $guruMataPelajaran->id)
            ->where('semester', $semester)
            ->where('aktif', true)
            ->pluck('id');

        if ($komponenIds->isEmpty()) {
            throw ValidationException::withMessages([
                'publikasi' => 'Belum ada komponen nilai aktif untuk semester ini.',
            ]);
        }

        $memilikiNilai = NilaiSiswa::query()
            ->whereIn('komponen_nilai_id', $komponenIds)
            ->where(function (Builder $query) {
                $query->whereNotNull('nilai')->orWhereNotNull('predikat');
            })
            ->exists();

        if (! $memilikiNilai) {
            throw ValidationException::withMessages([
                'publikasi' => 'Belum ada nilai siswa yang dapat dipublikasikan.',
            ]);
        }

        return $this->publikasiNilai->publikasikan(
            (int) $guruMataPelajaran->id,
            $semester,
            $pengguna->id,
        );
    }

    public function jadikanDraf(
        Pengguna $pengguna,
        GuruMataPelajaran $guruMataPelajaran,
        string $semester,
    ): bool {
        $this->pastikanSemesterValid($semester);
        $this->pastikanBolehAksesGuruMataPelajaran($pengguna, $guruMataPelajaran);

        return $this->publikasiNilai->tandaiDraf(
            (int) $guruMataPelajaran->id,
            $semester,
        );
    }

    public function ambilAnggotaKelas(int $kelasId)
    {
        return AnggotaKelas::query()
            ->with('siswa')
            ->where('kelas_id', $kelasId)
            ->where('status_keanggotaan', 'aktif')
            ->orderByRaw('nomor_absen IS NULL')
            ->orderBy('nomor_absen')
            ->orderBy('id')
            ->get();
    }

    private function pastikanBolehAksesGuruMataPelajaran(
        Pengguna $pengguna,
        GuruMataPelajaran $guruMataPelajaran,
    ): void {
        abort_unless(
            $this->queryGuruMataPelajaranDalamCakupan($pengguna)
                ->whereKey($guruMataPelajaran->id)
                ->exists(),
            403,
        );
    }

    private function pastikanSemesterValid(string $semester): void
    {
        abort_unless(in_array($semester, ['ganjil', 'genap'], true), 404);
    }
}
