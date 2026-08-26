<?php

namespace App\Services\Nilai;

use App\Models\SkemaBobotNilai;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SkemaBobotNilaiService
{
    public function __construct(private readonly PublikasiNilaiService $publikasiNilai) {}

    public function tambah(array $data): SkemaBobotNilai
    {
        $data = $this->rapikanData($data);
        $this->pastikanTotalBobot100($data);
        $this->pastikanScopeBelumAda($data);

        return DB::transaction(function () use ($data) {
            $skema = SkemaBobotNilai::create($data);
            $this->tandaiPublikasiDraf($skema);

            return $skema;
        });
    }

    public function ubah(SkemaBobotNilai $skema, array $data): void
    {
        $data = $this->rapikanData($data);
        $this->pastikanTotalBobot100($data);
        $this->pastikanScopeBelumAda($data, $skema);

        DB::transaction(function () use ($skema, $data) {
            $cakupanLama = [
                'tahun_pelajaran_id' => (int) $skema->tahun_pelajaran_id,
                'semester' => $skema->semester,
                'tingkat' => $skema->tingkat,
            ];
            $skema->update($data);
            $this->publikasiNilai->tandaiDrafUntukSkema(
                $cakupanLama['tahun_pelajaran_id'],
                $cakupanLama['semester'],
                $cakupanLama['tingkat'],
            );
            $this->tandaiPublikasiDraf($skema);
        });
    }

    public function nonaktifkan(SkemaBobotNilai $skema): void
    {
        DB::transaction(function () use ($skema) {
            $skema->update(['aktif' => false]);
            $this->tandaiPublikasiDraf($skema);
        });
    }

    private function rapikanData(array $data): array
    {
        return [
            'tahun_pelajaran_id' => (int) $data['tahun_pelajaran_id'],
            'semester' => $data['semester'],
            'tingkat' => filled($data['tingkat'] ?? null) ? (int) $data['tingkat'] : null,
            'bobot_formatif' => (int) $data['bobot_formatif'],
            'bobot_sumatif' => (int) $data['bobot_sumatif'],
            'bobot_sts' => (int) $data['bobot_sts'],
            'bobot_sas_saj' => (int) $data['bobot_sas_saj'],
            'aktif' => (bool) $data['aktif'],
            'keterangan' => filled($data['keterangan'] ?? null)
                ? trim((string) $data['keterangan'])
                : null,
        ];
    }

    private function pastikanTotalBobot100(array $data): void
    {
        $total = $data['bobot_formatif']
            + $data['bobot_sumatif']
            + $data['bobot_sts']
            + $data['bobot_sas_saj'];

        if ($total !== 100) {
            throw ValidationException::withMessages([
                'bobot_formatif' => 'Total bobot harus tepat 100%. Saat ini totalnya '.$total.'%.',
            ]);
        }
    }

    private function pastikanScopeBelumAda(
        array $data,
        ?SkemaBobotNilai $skema = null,
    ): void {
        $sudahAda = SkemaBobotNilai::query()
            ->where('tahun_pelajaran_id', $data['tahun_pelajaran_id'])
            ->where('semester', $data['semester'])
            ->when(
                $data['tingkat'],
                fn ($query, $tingkat) => $query->where('tingkat', $tingkat),
                fn ($query) => $query->whereNull('tingkat'),
            )
            ->when($skema, fn ($query, $skema) => $query->whereKeyNot($skema->id))
            ->exists();

        if ($sudahAda) {
            throw ValidationException::withMessages([
                'tingkat' => 'Skema untuk tahun pelajaran, semester, dan tingkat tersebut sudah ada.',
            ]);
        }
    }

    private function tandaiPublikasiDraf(SkemaBobotNilai $skema): void
    {
        $this->publikasiNilai->tandaiDrafUntukSkema(
            (int) $skema->tahun_pelajaran_id,
            $skema->semester,
            $skema->tingkat,
        );
    }
}
