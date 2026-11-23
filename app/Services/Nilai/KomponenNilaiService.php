<?php

namespace App\Services\Nilai;

use App\Models\GuruMataPelajaran;
use App\Models\KomponenNilai;
use App\Models\Pengguna;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KomponenNilaiService
{
    public function __construct(private readonly PublikasiNilaiService $publikasiNilai) {}

    public function tambah(Pengguna $pengguna, array $data): KomponenNilai
    {
        $data = $this->rapikanData($data);
        $this->pastikanBolehAksesGuruMataPelajaran(
            $pengguna,
            $data['guru_mata_pelajaran_id'],
        );

        return DB::transaction(function () use ($data) {
            $this->kunciPenugasan([$data['guru_mata_pelajaran_id']]);
            $this->pastikanNamaUnik($data);
            $this->pastikanStsDanSasTunggal($data);

            return $this->buatKomponen($data);
        });
    }

    public function tambahKolektif(Pengguna $pengguna, array $data): array
    {
        return DB::transaction(function () use ($pengguna, $data) {
            $ids = $data['guru_mata_pelajaran_ids'];
            $penugasan = $this->queryPenugasanUntukTambah($pengguna)
                ->whereKey($ids)
                ->with('kelas:id,nama')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            abort_unless($penugasan->count() === count($ids), 403, 'Ada kelas yang tidak dapat dipilih dari penugasan Anda.');

            $konteks = $penugasan->groupBy(fn (GuruMataPelajaran $item) => implode(':', [
                $item->tahun_pelajaran_id, $item->mata_pelajaran_id, $item->pegawai_id,
            ]));
            if ($konteks->count() !== 1 || $penugasan->unique('kelas_id')->count() !== $penugasan->count()) {
                throw ValidationException::withMessages([
                    'guru_mata_pelajaran_ids' => 'Pilih kelas untuk guru, mata pelajaran, dan tahun pelajaran yang sama.',
                ]);
            }

            $hasil = ['dibuat' => [], 'dilewati' => []];
            foreach ($penugasan->sortBy('kelas.nama', SORT_NATURAL | SORT_FLAG_CASE) as $item) {
                $rincian = ['kelas_id' => (int) $item->kelas_id, 'kelas' => $item->kelas->nama];
                $dataKomponen = $this->rapikanData(['guru_mata_pelajaran_id' => $item->id] + $data);
                try {
                    $this->pastikanNamaUnik($dataKomponen);
                    $this->pastikanStsDanSasTunggal($dataKomponen);
                } catch (ValidationException $exception) {
                    $hasil['dilewati'][] = $rincian + ['alasan' => $exception->validator->errors()->first()];

                    continue;
                }

                $komponen = $this->buatKomponen($dataKomponen);
                $hasil['dibuat'][] = $rincian + ['komponen_nilai_id' => (int) $komponen->id];
            }

            return $hasil;
        });
    }

    public function pesanHasilKolektif(array $hasil): string
    {
        $dibuat = count($hasil['dibuat']);
        $pesan = $dibuat > 0
            ? 'Komponen dibuat untuk '.$dibuat.' kelas: '.implode(', ', array_column($hasil['dibuat'], 'kelas')).'.'
            : 'Tidak ada komponen baru yang dibuat.';

        if ($hasil['dilewati'] !== []) {
            $pesan .= ' Dilewati: '.collect($hasil['dilewati'])
                ->groupBy('alasan')
                ->map(fn ($kelas, $alasan) => $kelas->pluck('kelas')->implode(', ').' ('.rtrim($alasan, '.').')')
                ->implode('; ').'.';
        }

        return $pesan;
    }

    public function ubah(
        Pengguna $pengguna,
        KomponenNilai $komponen,
        array $data,
    ): void {
        $data = $this->rapikanData($data, (int) $komponen->urutan);
        $this->pastikanBolehAksesKomponen($pengguna, $komponen);
        $this->pastikanBolehAksesGuruMataPelajaran(
            $pengguna,
            $data['guru_mata_pelajaran_id'],
        );

        DB::transaction(function () use ($komponen, $data) {
            $this->kunciPenugasan([$komponen->guru_mata_pelajaran_id, $data['guru_mata_pelajaran_id']]);
            $this->pastikanNamaUnik($data, $komponen);
            $this->pastikanStsDanSasTunggal($data, $komponen);

            $cakupanLama = [
                'guru_mata_pelajaran_id' => (int) $komponen->guru_mata_pelajaran_id,
                'semester' => $komponen->semester,
            ];
            $komponen->update($data);
            if ($komponen->wasChanged(array_keys($data))) {
                $komponen->batalkanFinalisasiStsManual();
            }
            $this->publikasiNilai->tandaiDraf(
                $cakupanLama['guru_mata_pelajaran_id'],
                $cakupanLama['semester'],
            );
            $this->tandaiPublikasiDraf($komponen);
        });
    }

    public function nonaktifkan(Pengguna $pengguna, KomponenNilai $komponen): void
    {
        $this->pastikanBolehAksesKomponen($pengguna, $komponen);

        DB::transaction(function () use ($komponen) {
            $komponen->update(['aktif' => false]);
            $komponen->batalkanFinalisasiStsManual();
            $this->tandaiPublikasiDraf($komponen);
        });
    }

    public function queryKomponenDalamCakupan(Pengguna $pengguna): Builder
    {
        return KomponenNilai::query()
            ->when(
                $this->membatasiCakupanGuruMapel($pengguna),
                fn (Builder $query) => $query->whereHas(
                    'guruMataPelajaran',
                    fn (Builder $query) => $query->where(
                        'pegawai_id',
                        $pengguna->pegawai_id ?: 0,
                    ),
                ),
            );
    }

    public function queryGuruMataPelajaranDalamCakupan(Pengguna $pengguna): Builder
    {
        return GuruMataPelajaran::query()
            ->when(
                $this->membatasiCakupanGuruMapel($pengguna),
                fn (Builder $query) => $query->where(
                    'pegawai_id',
                    $pengguna->pegawai_id ?: 0,
                ),
            );
    }

    public function queryPenugasanUntukTambah(Pengguna $pengguna): Builder
    {
        return $this->queryGuruMataPelajaranDalamCakupan($pengguna)
            ->where('aktif', true)
            ->whereHas('kelas', fn (Builder $query) => $query->where('aktif', true)
                ->whereColumn('kelas.tahun_pelajaran_id', 'guru_mata_pelajaran.tahun_pelajaran_id'));
    }

    public function pastikanBolehAksesKomponen(
        Pengguna $pengguna,
        KomponenNilai $komponen,
    ): void {
        if (! $this->membatasiCakupanGuruMapel($pengguna)) {
            return;
        }

        $komponen->loadMissing('guruMataPelajaran:id,pegawai_id');
        abort_unless(
            (int) $komponen->guruMataPelajaran?->pegawai_id === (int) $pengguna->pegawai_id,
            403,
        );
    }

    private function pastikanBolehAksesGuruMataPelajaran(
        Pengguna $pengguna,
        int $guruMataPelajaranId,
    ): void {
        if (! $this->membatasiCakupanGuruMapel($pengguna)) {
            return;
        }

        abort_unless(
            GuruMataPelajaran::query()
                ->whereKey($guruMataPelajaranId)
                ->where('pegawai_id', $pengguna->pegawai_id ?: 0)
                ->exists(),
            403,
        );
    }

    private function rapikanData(array $data, int $urutanLama = 0): array
    {
        return [
            'guru_mata_pelajaran_id' => (int) $data['guru_mata_pelajaran_id'],
            'semester' => $data['semester'],
            'jenis_komponen' => $data['jenis_komponen'],
            'nama' => trim((string) $data['nama']),
            'tanggal_penilaian' => filled($data['tanggal_penilaian'] ?? null)
                ? $data['tanggal_penilaian']
                : null,
            'urutan' => (int) ($data['urutan'] ?? $urutanLama),
            'aktif' => (bool) $data['aktif'],
            'keterangan' => filled($data['keterangan'] ?? null)
                ? trim((string) $data['keterangan'])
                : null,
        ];
    }

    private function kunciPenugasan(array $ids): void
    {
        GuruMataPelajaran::query()->whereKey($ids)->orderBy('id')->lockForUpdate()->get(['id']);
    }

    private function buatKomponen(array $data): KomponenNilai
    {
        $komponen = KomponenNilai::create($data);
        $this->tandaiPublikasiDraf($komponen);

        return $komponen;
    }

    private function pastikanNamaUnik(
        array $data,
        ?KomponenNilai $komponen = null,
    ): void {
        $sudahAda = KomponenNilai::query()
            ->where('guru_mata_pelajaran_id', $data['guru_mata_pelajaran_id'])
            ->where('semester', $data['semester'])
            ->where('jenis_komponen', $data['jenis_komponen'])
            ->where('nama', $data['nama'])
            ->when($komponen, fn (Builder $query) => $query->whereKeyNot($komponen->id))
            ->exists();

        if ($sudahAda) {
            throw ValidationException::withMessages([
                'nama' => 'Nama komponen sudah digunakan pada penugasan, semester, dan jenis yang sama.',
            ]);
        }
    }

    private function pastikanStsDanSasTunggal(
        array $data,
        ?KomponenNilai $komponen = null,
    ): void {
        if (! $data['aktif'] || ! in_array($data['jenis_komponen'], ['sts', 'sas_saj'], true)) {
            return;
        }

        $sudahAda = KomponenNilai::query()
            ->where('guru_mata_pelajaran_id', $data['guru_mata_pelajaran_id'])
            ->where('semester', $data['semester'])
            ->where('jenis_komponen', $data['jenis_komponen'])
            ->where('aktif', true)
            ->when($komponen, fn (Builder $query) => $query->whereKeyNot($komponen->id))
            ->exists();

        if ($sudahAda) {
            $label = $data['jenis_komponen'] === 'sts' ? 'STS' : 'SAS/SAJ';
            throw ValidationException::withMessages([
                'jenis_komponen' => $label.' hanya boleh dibuat satu kali untuk guru mapel dan semester yang sama.',
            ]);
        }
    }

    private function tandaiPublikasiDraf(KomponenNilai $komponen): void
    {
        $this->publikasiNilai->tandaiDraf(
            (int) $komponen->guru_mata_pelajaran_id,
            $komponen->semester,
        );
    }

    private function membatasiCakupanGuruMapel(Pengguna $pengguna): bool
    {
        if ($pengguna->administrator() || ! $pengguna->memilikiPeran('guru_mapel')) {
            return false;
        }

        return ! $pengguna->memilikiPeran(['pimpinan', 'wakil_pimpinan_kurikulum']);
    }
}
