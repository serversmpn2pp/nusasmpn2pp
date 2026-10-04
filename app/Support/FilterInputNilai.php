<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FilterInputNilai
{
    public const JENIS = ['formatif' => 'Formatif', 'sumatif' => 'Sumatif', 'sts' => 'STS', 'sas_saj' => 'SAS/SAJ'];

    public static function aturan(): array
    {
        return [
            'tahun_pelajaran_id' => ['nullable', 'integer', 'exists:tahun_pelajaran,id'],
            'semester' => ['nullable', Rule::in(['semua', 'ganjil', 'genap'])],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'jenis_komponen' => ['nullable', Rule::in(['semua', ...array_keys(self::JENIS)])],
        ];
    }

    public static function validasi(Request $request, bool $dariForm = false): array
    {
        if (! $dariForm) {
            return $request->validate(self::aturan());
        }

        $aturan = ['filter' => ['sometimes', 'array:'.implode(',', array_keys(self::aturan()))]];
        foreach (self::aturan() as $key => $rules) {
            $aturan['filter.'.$key] = $rules;
        }

        return array_map(fn ($value) => $value ?? '', $request->validate($aturan)['filter'] ?? []);
    }
}
